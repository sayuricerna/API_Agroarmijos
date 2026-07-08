<?php
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

            $this->conn->commit();
            return $id_compra;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}