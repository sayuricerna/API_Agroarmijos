<?php

class Movimiento {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listar() {
        $query = "SELECT 
                    m.id_movimiento,
                    m.fecha,
                    p.nombre AS producto,
                    tm.nombre AS concepto,
                    CASE 
                        WHEN m.stock_nuevo >= m.stock_anterior THEN 'ENTRADA'
                        ELSE 'SALIDA'
                    END AS tipo,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario,
                    m.observacion
                  FROM movimientos m
                  INNER JOIN productos p ON m.id_producto = p.id_producto
                  INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                  INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                  ORDER BY m.fecha DESC, m.id_movimiento DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}