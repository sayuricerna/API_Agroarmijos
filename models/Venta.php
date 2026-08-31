<?php
require_once __DIR__ . '/../helpers/Auditor.php';

class Venta {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function crearVenta($data, $id_usuario) {
        try {
            $this->conn->beginTransaction();

            /*
             * FIX (GCS — fix/venta-calculo-servidor): antes esta función
             * guardaba tal cual el precio_unitario/subtotal/total que
             * mandaba el frontend, sin comparar contra el precio real del
             * producto. Eso permitía registrar una venta con precios
             * inventados (por ejemplo, mandando la petición directo con
             * Postman en vez de usar la app). Ahora el precio de cada
             * línea se toma SIEMPRE de la tabla productos, y el
             * descuento por línea (que sí es una decisión legítima del
             * vendedor) se acota para que no sea negativo ni mayor al
             * subtotal de esa línea. El subtotal/total de la venta ya no
             * se reciben del frontend: se recalculan sumando las líneas
             * validadas.
             */
            $lineas = [];
            $subtotal_general = 0.0;
            $descuento_general = 0.0;

            foreach ($data['productos'] as $prod) {
                // Bloqueo de fila para control de concurrencia — ahora también
                // trae el precio_venta real, no solo el stock.
                $qStock = "SELECT stock_actual, precio_venta FROM productos WHERE id_producto = :id FOR UPDATE";
                $stStock = $this->conn->prepare($qStock);
                $stStock->bindParam(":id", $prod['id_producto']);
                $stStock->execute();
                $pActual = $stStock->fetch(PDO::FETCH_ASSOC);

                if (!$pActual) {
                    throw new Exception("Producto ID " . $prod['id_producto'] . " no existe o no está disponible.");
                }

                if ($pActual['stock_actual'] < $prod['cantidad']) {
                    throw new Exception("Stock insuficiente para el producto ID: " . $prod['id_producto']);
                }

                $stock_anterior = $pActual['stock_actual'];
                $stock_nuevo = $stock_anterior - $prod['cantidad'];

                $precio_real = (float) $pActual['precio_venta'];
                $cantidad = (float) $prod['cantidad'];
                $subtotal_bruto_linea = $precio_real * $cantidad;

                $descuento_linea = (float) ($prod['descuento'] ?? 0);
                if ($descuento_linea < 0) {
                    $descuento_linea = 0;
                }
                if ($descuento_linea > $subtotal_bruto_linea) {
                    $descuento_linea = $subtotal_bruto_linea;
                }

                $subtotal_linea = $subtotal_bruto_linea - $descuento_linea;

                $subtotal_general += $subtotal_bruto_linea;
                $descuento_general += $descuento_linea;

                $lineas[] = [
                    "id_producto"     => $prod['id_producto'],
                    "cantidad"        => $cantidad,
                    "precio_unitario" => $precio_real,
                    "descuento"       => $descuento_linea,
                    "subtotal"        => $subtotal_linea,
                    "stock_anterior"  => $stock_anterior,
                    "stock_nuevo"     => $stock_nuevo,
                ];
            }

            // IVA: se sigue aceptando el valor calculado por el frontend
            // (no conozco la tasa/regla exacta que usa el negocio), pero
            // acotado dentro de un rango razonable para que no llegue
            // negativo ni absurdamente alto respecto al subtotal real.
            $iva = (float) ($data['iva'] ?? 0);
            if ($iva < 0) {
                $iva = 0;
            }
            $iva_maximo = $subtotal_general * 0.5;
            if ($iva > $iva_maximo) {
                $iva = $iva_maximo;
            }

            $total_general = $subtotal_general - $descuento_general + $iva;

            $query = "INSERT INTO ventas (id_cliente, id_usuario, fecha, subtotal, descuento, iva, total, observacion, estado)
                      VALUES (:id_cliente, :id_usuario, CURDATE(), :subtotal, :descuento, :iva, :total, :observacion, 'PAGADA')";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_cliente", $data['id_cliente']);
            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":subtotal", $subtotal_general);
            $stmt->bindParam(":descuento", $descuento_general);
            $stmt->bindParam(":iva", $iva);
            $stmt->bindParam(":total", $total_general);
            $stmt->bindParam(":observacion", $data['observacion']);
            $stmt->execute();

            $id_venta = $this->conn->lastInsertId();

            foreach ($lineas as $linea) {
                $qUpdate = "UPDATE productos SET stock_actual = :sn, stock_disponible = :sn - stock_reservado WHERE id_producto = :id";
                $stUpdate = $this->conn->prepare($qUpdate);
                $stUpdate->bindParam(":sn", $linea['stock_nuevo']);
                $stUpdate->bindParam(":id", $linea['id_producto']);
                $stUpdate->execute();

                $qDetalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal)
                             VALUES (:id_venta, :id_producto, :cantidad, :precio, :descuento, :subtotal)";
                $stDetalle = $this->conn->prepare($qDetalle);
                $stDetalle->bindParam(":id_venta", $id_venta);
                $stDetalle->bindParam(":id_producto", $linea['id_producto']);
                $stDetalle->bindParam(":cantidad", $linea['cantidad']);
                $stDetalle->bindParam(":precio", $linea['precio_unitario']);
                $stDetalle->bindParam(":descuento", $linea['descuento']);
                $stDetalle->bindParam(":subtotal", $linea['subtotal']);
                $stDetalle->execute();

                $qKardex = "CALL sp_registrar_movimiento(:id_prod, 2, :id_user, NULL, :id_venta, NULL, :cant, :ant, :nue, 'Salida por Venta')";
                $stKardex = $this->conn->prepare($qKardex);
                $stKardex->bindParam(":id_prod", $linea['id_producto']);
                $stKardex->bindParam(":id_user", $id_usuario);
                $stKardex->bindParam(":id_venta", $id_venta);
                $stKardex->bindParam(":cant", $linea['cantidad']);
                $stKardex->bindParam(":ant", $linea['stock_anterior']);
                $stKardex->bindParam(":nue", $linea['stock_nuevo']);
                $stKardex->execute();
            }

            // Auditoría (GCS — feature/auditoria-integracion): se registra
            // DENTRO de la misma transacción con Auditor::registrar() (no
            // "registrarSeguro"), a propósito: si por algún motivo no se
            // pudiera dejar constancia de la venta, es preferible que toda
            // la operación haga rollback a que quede una venta sin rastro
            // de auditoría. Usa $subtotal_general/$total_general (los
            // calculados en el backend, no los que mandó el frontend) y
            // count($lineas), no $data['productos'], para reflejar
            // exactamente lo que de verdad se guardó.
            Auditor::registrar(
                $this->conn,
                $id_usuario,
                'Ventas',
                'ventas',
                $id_venta,
                'REGISTRAR',
                "Registró la venta #$id_venta por $" . number_format($total_general, 2) . " (" . count($lineas) . " producto(s))."
            );

            $this->conn->commit();
            return $id_venta;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
    /*
     * Anula una venta PAGADA: restituye el stock de cada línea, deja
     * constancia en el Kárdex (tipo DEVOLUCION_VENTA, buscado por nombre
     * en vez de "quemar" su ID — mismo criterio que el ajuste manual de
     * Inventario) y marca la venta como ANULADA. Todo dentro de una sola
     * transacción: si algo falla, no debe quedar stock devuelto sin la
     * venta marcada como anulada, ni viceversa.
     */
    public function anular($idVenta, $idUsuario, $motivo) {
        try {
            $this->conn->beginTransaction();

            $queryVenta = "SELECT estado, total FROM ventas WHERE id_venta = :id FOR UPDATE";
            $stmtVenta = $this->conn->prepare($queryVenta);
            $stmtVenta->bindParam(":id", $idVenta);
            $stmtVenta->execute();
            $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

            if (!$venta) {
                throw new Exception("La venta no existe.");
            }

            if ($venta['estado'] === 'ANULADA') {
                throw new Exception("Esta venta ya está anulada.");
            }

            if ($venta['estado'] !== 'PAGADA') {
                throw new Exception("Solo se pueden anular ventas pagadas.");
            }

            $stmtTipo = $this->conn->prepare(
                "SELECT id_tipo_movimiento FROM tipos_movimiento WHERE nombre = 'DEVOLUCION_VENTA'"
            );
            $stmtTipo->execute();
            $idTipoDevolucion = $stmtTipo->fetchColumn();

            if (!$idTipoDevolucion) {
                throw new Exception("No se encontró el tipo de movimiento DEVOLUCION_VENTA.");
            }

            $queryDetalle = "SELECT id_producto, cantidad FROM detalle_ventas WHERE id_venta = :id_venta";
            $stmtDetalle = $this->conn->prepare($queryDetalle);
            $stmtDetalle->bindParam(":id_venta", $idVenta);
            $stmtDetalle->execute();
            $lineas = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lineas as $linea) {
                $idProducto = (int) $linea['id_producto'];
                $cantidad = (float) $linea['cantidad'];

                $qStock = "SELECT stock_actual FROM productos WHERE id_producto = :id FOR UPDATE";
                $stStock = $this->conn->prepare($qStock);
                $stStock->bindParam(":id", $idProducto);
                $stStock->execute();
                $producto = $stStock->fetch(PDO::FETCH_ASSOC);

                $stockAnterior = $producto ? (float) $producto['stock_actual'] : 0;
                $stockNuevo = $stockAnterior + $cantidad;

                $qUpdate = "UPDATE productos SET stock_actual = :sn, stock_disponible = :sn - stock_reservado WHERE id_producto = :id";
                $stUpdate = $this->conn->prepare($qUpdate);
                $stUpdate->bindParam(":sn", $stockNuevo);
                $stUpdate->bindParam(":id", $idProducto);
                $stUpdate->execute();

                $obsKardex = "Devolución por anulación de venta #$idVenta";

                $qKardex = "CALL sp_registrar_movimiento(:id_prod, :tipo, :id_user, NULL, :id_venta, NULL, :cant, :ant, :nue, :obs)";
                $stKardex = $this->conn->prepare($qKardex);
                $stKardex->bindParam(":id_prod", $idProducto);
                $stKardex->bindParam(":tipo", $idTipoDevolucion);
                $stKardex->bindParam(":id_user", $idUsuario);
                $stKardex->bindParam(":id_venta", $idVenta);
                $stKardex->bindParam(":cant", $cantidad);
                $stKardex->bindParam(":ant", $stockAnterior);
                $stKardex->bindParam(":nue", $stockNuevo);
                $stKardex->bindParam(":obs", $obsKardex);
                $stKardex->execute();
            }

            $queryAnular = "UPDATE ventas SET estado = 'ANULADA' WHERE id_venta = :id";
            $stmtAnular = $this->conn->prepare($queryAnular);
            $stmtAnular->bindParam(":id", $idVenta);
            $stmtAnular->execute();

            // Auditoría (GCS — feature/ventas-anular): dentro de la misma
            // transacción, mismo criterio que crearVenta().
            Auditor::registrar(
                $this->conn,
                $idUsuario,
                'Ventas',
                'ventas',
                (int) $idVenta,
                'ANULAR',
                "Anuló la venta #$idVenta por $" . number_format((float) $venta['total'], 2) . ". Motivo: $motivo.",
                ['estado' => $venta['estado']],
                ['estado' => 'ANULADA']
            );

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function listarVentas() {
    $query = "SELECT 
                v.id_venta,
                v.fecha,
                v.subtotal,
                v.descuento,
                v.iva,
                v.total,
                v.estado,
                v.observacion,
                c.nombres AS cliente,
                u.nombres AS vendedor
              FROM ventas v
              INNER JOIN clientes c ON v.id_cliente = c.id_cliente
              INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
              ORDER BY v.id_venta DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}