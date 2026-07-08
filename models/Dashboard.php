<?php
class Dashboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function obtenerMetricas() {
        $qVentas = "SELECT IFNULL(SUM(total), 0) AS total_ventas FROM ventas WHERE MONTH(fecha) = MONTH(CURDATE()) AND estado != 'ANULADA'";
        $stVentas = $this->conn->query($qVentas);
        $ventas = $stVentas->fetch(PDO::FETCH_ASSOC);

        $qCriticos = "SELECT COUNT(*) AS total_criticos FROM productos WHERE stock_actual <= stock_minimo AND estado = 1";
        $stCriticos = $this->conn->query($qCriticos);
        $criticos = $stCriticos->fetch(PDO::FETCH_ASSOC);

        $qTop = "SELECT p.nombre, SUM(dv.cantidad) as unidades 
                 FROM detalle_ventas dv 
                 JOIN productos p ON dv.id_producto = p.id_producto 
                 GROUP BY dv.id_producto ORDER BY unidades DESC LIMIT 5";
        $stTop = $this->conn->query($qTop);
        $topProductos = $stTop->fetchAll(PDO::FETCH_ASSOC);

        return [
            "ventas_mes" => $ventas['total_ventas'],
            "productos_criticos" => $criticos['total_criticos'],
            "top_productos" => $topProductos
        ];
    }
}