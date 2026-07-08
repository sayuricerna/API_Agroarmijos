<?php
class Venta {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function crearVenta($data, $id_usuario) {
        try {
            $this->conn->beginTransaction();

            $query = "INSERT INTO ventas (id_cliente, id_usuario, fecha, subtotal, descuento, iva, total, observacion, estado) 
                      VALUES (:id_cliente, :id_usuario, CURDATE(), :subtotal, :descuento, :iva, :total, :observacion, 'PAGADA')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id_cliente", $data['id_cliente']);
            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":subtotal", $data['subtotal']);
            $stmt->bindParam(":descuento", $data['descuento']);
            $stmt->bindParam(":iva", $data['iva']);
            $stmt->bindParam(":total", $data['total']);
            $stmt->bindParam(":observacion", $data['observacion']);
            $stmt->execute();
            
            $id_venta = $this->conn->lastInsertId();

            foreach ($data['productos'] as $prod) {
                // Bloqueo de fila para control de concurrencia
                $qStock = "SELECT stock_actual FROM productos WHERE id_producto = :id FOR UPDATE";
                $stStock = $this->conn->prepare($qStock);
                $stStock->bindParam(":id", $prod['id_producto']);
                $stStock->execute();
                $pActual = $stStock->fetch(PDO::FETCH_ASSOC);

                if (!$pActual || $pActual['stock_actual'] < $prod['cantidad']) {
                    throw new Exception("Stock insuficiente para el producto ID: " . $prod['id_producto']);
                }

                $stock_anterior = $pActual['stock_actual'];
                $stock_nuevo = $stock_anterior - $prod['cantidad'];

                $qUpdate = "UPDATE productos SET stock_actual = :sn, stock_disponible = :sn - stock_reservado WHERE id_producto = :id";
                $stUpdate = $this->conn->prepare($qUpdate);
                $stUpdate->bindParam(":sn", $stock_nuevo);
                $stUpdate->bindParam(":id", $prod['id_producto']);
                $stUpdate->execute();

                $qDetalle = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal) 
                             VALUES (:id_venta, :id_producto, :cantidad, :precio, :descuento, :subtotal)";
                $stDetalle = $this->conn->prepare($qDetalle);
                $stDetalle->bindParam(":id_venta", $id_venta);
                $stDetalle->bindParam(":id_producto", $prod['id_producto']);
                $stDetalle->bindParam(":cantidad", $prod['cantidad']);
                $stDetalle->bindParam(":precio", $prod['precio_unitario']);
                $stDetalle->bindParam(":descuento", $prod['descuento']);
                $stDetalle->bindParam(":subtotal", $prod['subtotal']);
                $stDetalle->execute();

                $qKardex = "CALL sp_registrar_movimiento(:id_prod, 2, :id_user, NULL, :id_venta, NULL, :cant, :ant, :nue, 'Salida por Venta')";
                $stKardex = $this->conn->prepare($qKardex);
                $stKardex->bindParam(":id_prod", $prod['id_producto']);
                $stKardex->bindParam(":id_user", $id_usuario);
                $stKardex->bindParam(":id_venta", $id_venta);
                $stKardex->bindParam(":cant", $prod['cantidad']);
                $stKardex->bindParam(":ant", $stock_anterior);
                $stKardex->bindParam(":nue", $stock_nuevo);
                $stKardex->execute();
            }

            $this->conn->commit();
            return $id_venta;
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