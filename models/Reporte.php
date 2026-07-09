<?php

class Reporte {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function ventasPorFecha($desde, $hasta) {
        $query = "SELECT 
                    v.id_venta,
                    v.fecha,
                    v.subtotal,
                    v.descuento,
                    v.iva,
                    v.total,
                    v.estado,
                    CONCAT(c.nombres) AS cliente,
                    CONCAT(u.nombres, ' ', u.apellidos) AS vendedor
                  FROM ventas v
                  INNER JOIN clientes c ON v.id_cliente = c.id_cliente
                  INNER JOIN usuarios u ON v.id_usuario = u.id_usuario
                  WHERE DATE(v.fecha) BETWEEN :desde AND :hasta
                    AND v.estado != 'ANULADA'
                  ORDER BY v.fecha DESC, v.id_venta DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":desde", $desde);
        $stmt->bindParam(":hasta", $hasta);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}