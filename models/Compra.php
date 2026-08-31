<?php
require_once __DIR__ . '/../helpers/Auditor.php';

class Compra {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function registrarCompra($data, $id_usuario) {
        try {
            $this->conn->beginTransaction();

            $query = "INSERT INTO compras (id_proveedor, id_usuario, numero_factura, fecha, subtotal, descuento, iva, total, observacion, estado) 
                      VALUES (:id_proveedor, :id_usuario, :numero_factura, CURDATE(), :subtotal, :descuento, :iva, :total, :observacion, 'RECIBIDA')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_proveedor", $data['id_proveedor']);
            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":numero_factura", $data['numero_factura']);
            $stmt->bindParam(":subtotal", $data['subtotal']);
            $stmt->bindParam(":descuento", $data['descuento']);
            $stmt->bindParam(":iva", $data['iva']);
            $stmt->bindParam(":total", $data['total']);
            $stmt->bindParam(":observacion", $data['observacion']);
            $stmt->execute();

            $id_compra = $this->conn->lastInsertId();

            foreach ($data['productos'] as $prod) {
                $qStock = "SELECT stock_actual FROM productos WHERE id_producto = :id FOR UPDATE";
                $stStock = $this->conn->prepare($qStock);
                $stStock->bindParam(":id", $prod['id_producto']);
                $stStock->execute();
                $pActual = $stStock->fetch(PDO::FETCH_ASSOC);

                $stock_anterior = $pActual ? $pActual['stock_actual'] : 0;
                $stock_nuevo = $stock_anterior + $prod['cantidad'];

                $qUpdate = "UPDATE productos SET stock_actual = :sn, stock_disponible = :sn - stock_reservado, precio_compra = :pc WHERE id_producto = :id";
                $stUpdate = $this->conn->prepare($qUpdate);
                $stUpdate->bindParam(":sn", $stock_nuevo);
                $stUpdate->bindParam(":pc", $prod['costo_unitario']);
                $stUpdate->bindParam(":id", $prod['id_producto']);
                $stUpdate->execute();

                $qDetalle = "INSERT INTO detalle_compras (id_compra, id_producto, cantidad, costo_unitario, subtotal) 
                             VALUES (:id_compra, :id_producto, :cantidad, :costo, :subtotal)";
                $stDetalle = $this->conn->prepare($qDetalle);
                $stDetalle->bindParam(":id_compra", $id_compra);
                $stDetalle->bindParam(":id_producto", $prod['id_producto']);
                $stDetalle->bindParam(":cantidad", $prod['cantidad']);
                $stDetalle->bindParam(":costo", $prod['costo_unitario']);
                $stDetalle->bindParam(":subtotal", $prod['subtotal']);
                $stDetalle->execute();

                $qKardex = "CALL sp_registrar_movimiento(:id_prod, 1, :id_user, :id_compra, NULL, NULL, :cant, :ant, :nue, 'Ingreso por Compra')";
                $stKardex = $this->conn->prepare($qKardex);
                $stKardex->bindParam(":id_prod", $prod['id_producto']);
                $stKardex->bindParam(":id_user", $id_usuario);
                $stKardex->bindParam(":id_compra", $id_compra);
                $stKardex->bindParam(":cant", $prod['cantidad']);
                $stKardex->bindParam(":ant", $stock_anterior);
                $stKardex->bindParam(":nue", $stock_nuevo);
                $stKardex->execute();
            }

            // Auditoría (GCS — feature/auditoria-integracion): dentro de la
            // misma transacción, mismo criterio que en Venta::crearVenta().
            Auditor::registrar(
                $this->conn,
                $id_usuario,
                'Compras',
                'compras',
                $id_compra,
                'REGISTRAR',
                "Registró la compra #$id_compra (factura {$data['numero_factura']}) por $" . number_format((float) $data['total'], 2) . "."
            );

            $this->conn->commit();
            return $id_compra;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function listarCompras() {
        $query = "SELECT
                    c.id_compra,
                    c.numero_factura,
                    c.fecha,
                    c.subtotal,
                    c.descuento,
                    c.iva,
                    c.total,
                    c.estado,
                    c.observacion,
                    c.motivo_anulacion,
                    p.razon_social AS proveedor,
                    u.nombres AS usuario
                  FROM compras c
                  INNER JOIN proveedores p ON c.id_proveedor = p.id_proveedor
                  INNER JOIN usuarios u ON c.id_usuario = u.id_usuario
                  ORDER BY c.id_compra DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Líneas de producto de una compra puntual (detalle_compras), para
     * mostrar "qué se compró" en el modal de detalle. Se pide bajo
     * demanda (no dentro de listarCompras()) para no traer todas las
     * líneas de todas las compras cuando solo se necesita ver el listado.
     */
    public function obtenerLineas($idCompra) {
        $query = "SELECT
                    dc.id_producto,
                    p.nombre,
                    dc.cantidad,
                    dc.costo_unitario,
                    dc.subtotal
                  FROM detalle_compras dc
                  INNER JOIN productos p ON dc.id_producto = p.id_producto
                  WHERE dc.id_compra = :id_compra
                  ORDER BY dc.id_detalle_compra";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_compra", $idCompra);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
     * Anula una compra RECIBIDA: revierte el stock que esa compra había
     * ingresado, deja constancia en el Kárdex (tipo DEVOLUCION_COMPRA,
     * buscado por nombre) y marca la compra como ANULADA. Misma
     * estructura que Venta::anular(), con una diferencia importante: acá
     * la reversión RESTA stock (en vez de sumarlo), así que si parte de
     * ese lote ya se vendió o se ajustó después de la compra, el stock
     * actual puede ser insuficiente para revertir — en ese caso se
     * aborta con un mensaje claro en vez de dejar el stock en negativo.
     */
    public function anular($idCompra, $idUsuario, $motivo) {
        try {
            $this->conn->beginTransaction();

            $queryCompra = "SELECT estado, total, numero_factura FROM compras WHERE id_compra = :id FOR UPDATE";
            $stmtCompra = $this->conn->prepare($queryCompra);
            $stmtCompra->bindParam(":id", $idCompra);
            $stmtCompra->execute();
            $compra = $stmtCompra->fetch(PDO::FETCH_ASSOC);

            if (!$compra) {
                throw new Exception("La compra no existe.");
            }

            if ($compra['estado'] === 'ANULADA') {
                throw new Exception("Esta compra ya está anulada.");
            }

            if ($compra['estado'] !== 'RECIBIDA') {
                throw new Exception("Solo se pueden anular compras recibidas.");
            }

            $stmtTipo = $this->conn->prepare(
                "SELECT id_tipo_movimiento FROM tipos_movimiento WHERE nombre = 'DEVOLUCION_COMPRA'"
            );
            $stmtTipo->execute();
            $idTipoDevolucion = $stmtTipo->fetchColumn();

            if (!$idTipoDevolucion) {
                throw new Exception("No se encontró el tipo de movimiento DEVOLUCION_COMPRA.");
            }

            $queryDetalle = "SELECT id_producto, cantidad FROM detalle_compras WHERE id_compra = :id_compra";
            $stmtDetalle = $this->conn->prepare($queryDetalle);
            $stmtDetalle->bindParam(":id_compra", $idCompra);
            $stmtDetalle->execute();
            $lineas = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            foreach ($lineas as $linea) {
                $idProducto = (int) $linea['id_producto'];
                $cantidad = (float) $linea['cantidad'];

                $qStock = "SELECT stock_actual, nombre FROM productos WHERE id_producto = :id FOR UPDATE";
                $stStock = $this->conn->prepare($qStock);
                $stStock->bindParam(":id", $idProducto);
                $stStock->execute();
                $producto = $stStock->fetch(PDO::FETCH_ASSOC);

                $stockAnterior = $producto ? (float) $producto['stock_actual'] : 0;

                if ($stockAnterior < $cantidad) {
                    $nombreProducto = $producto ? $producto['nombre'] : "ID $idProducto";
                    throw new Exception(
                        "No se puede anular: el producto \"$nombreProducto\" ya no tiene suficiente stock " .
                        "(quedan $stockAnterior, se necesitan $cantidad). Es probable que parte de este lote ya se haya vendido o ajustado."
                    );
                }

                $stockNuevo = $stockAnterior - $cantidad;

                $qUpdate = "UPDATE productos SET stock_actual = :sn, stock_disponible = :sn - stock_reservado WHERE id_producto = :id";
                $stUpdate = $this->conn->prepare($qUpdate);
                $stUpdate->bindParam(":sn", $stockNuevo);
                $stUpdate->bindParam(":id", $idProducto);
                $stUpdate->execute();

                $obsKardex = "Devolución por anulación de compra #$idCompra";

                $qKardex = "CALL sp_registrar_movimiento(:id_prod, :tipo, :id_user, :id_compra, NULL, NULL, :cant, :ant, :nue, :obs)";
                $stKardex = $this->conn->prepare($qKardex);
                $stKardex->bindParam(":id_prod", $idProducto);
                $stKardex->bindParam(":tipo", $idTipoDevolucion);
                $stKardex->bindParam(":id_user", $idUsuario);
                $stKardex->bindParam(":id_compra", $idCompra);
                $stKardex->bindParam(":cant", $cantidad);
                $stKardex->bindParam(":ant", $stockAnterior);
                $stKardex->bindParam(":nue", $stockNuevo);
                $stKardex->bindParam(":obs", $obsKardex);
                $stKardex->execute();
            }

            $queryAnular = "UPDATE compras SET estado = 'ANULADA', motivo_anulacion = :motivo WHERE id_compra = :id";
            $stmtAnular = $this->conn->prepare($queryAnular);
            $stmtAnular->bindParam(":motivo", $motivo);
            $stmtAnular->bindParam(":id", $idCompra);
            $stmtAnular->execute();

            // Auditoría (GCS — feature/compras-listar-anular): dentro de la
            // misma transacción, mismo criterio que Venta::anular().
            Auditor::registrar(
                $this->conn,
                $idUsuario,
                'Compras',
                'compras',
                (int) $idCompra,
                'ANULAR',
                "Anuló la compra #$idCompra (factura {$compra['numero_factura']}) por $" . number_format((float) $compra['total'], 2) . ". Motivo: $motivo.",
                ['estado' => $compra['estado']],
                ['estado' => 'ANULADA']
            );

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}