<?php
class Dashboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function obtenerMetricas() {

        // ---------------------------------------------------------------
        // Métricas existentes (se mantienen exactamente igual, mismo
        // nombre de clave, para no romper nada que ya las consuma).
        // ---------------------------------------------------------------

        $qVentasMes = "SELECT IFNULL(SUM(total), 0) AS total
                       FROM ventas
                       WHERE MONTH(fecha) = MONTH(CURDATE())
                         AND YEAR(fecha) = YEAR(CURDATE())
                         AND estado != 'ANULADA'";
        $ventasMes = $this->conn->query($qVentasMes)->fetch(PDO::FETCH_ASSOC);

        $qCriticos = "SELECT COUNT(*) AS total_criticos
                      FROM productos
                      WHERE stock_actual <= stock_minimo AND estado = 1";
        $criticos = $this->conn->query($qCriticos)->fetch(PDO::FETCH_ASSOC);

        $qTop = "SELECT p.nombre, SUM(dv.cantidad) as unidades
                 FROM detalle_ventas dv
                 JOIN productos p ON dv.id_producto = p.id_producto
                 GROUP BY dv.id_producto ORDER BY unidades DESC LIMIT 5";
        $topProductos = $this->conn->query($qTop)->fetchAll(PDO::FETCH_ASSOC);

        // ---------------------------------------------------------------
        // Métricas nuevas para el rediseño del Dashboard (todas de solo
        // lectura, ninguna toca datos ni reglas de negocio existentes).
        // ---------------------------------------------------------------

        $qVentasDia = "SELECT IFNULL(SUM(total), 0) AS total
                       FROM ventas
                       WHERE DATE(fecha) = CURDATE() AND estado != 'ANULADA'";
        $ventasDia = $this->conn->query($qVentasDia)->fetch(PDO::FETCH_ASSOC);

        $qComprasDia = "SELECT IFNULL(SUM(total), 0) AS total
                        FROM compras
                        WHERE DATE(fecha) = CURDATE() AND estado != 'ANULADA'";
        $comprasDia = $this->conn->query($qComprasDia)->fetch(PDO::FETCH_ASSOC);

        $qStockTotal = "SELECT COUNT(*) AS total FROM productos WHERE estado = 1";
        $stockTotal = $this->conn->query($qStockTotal)->fetch(PDO::FETCH_ASSOC);

        $qAgotados = "SELECT COUNT(*) AS total
                      FROM productos
                      WHERE stock_actual = 0 AND estado = 1";
        $agotados = $this->conn->query($qAgotados)->fetch(PDO::FETCH_ASSOC);

        $qReservasActivas = "SELECT COUNT(*) AS total
                             FROM reservas
                             WHERE estado IN ('PENDIENTE', 'CONFIRMADA')";
        $reservasActivas = $this->conn->query($qReservasActivas)->fetch(PDO::FETCH_ASSOC);

        $qReservasPorVencer = "SELECT COUNT(*) AS total
                               FROM reservas
                               WHERE estado = 'PENDIENTE'
                                 AND fecha_expiracion BETWEEN NOW() AND (NOW() + INTERVAL 2 DAY)";
        $reservasPorVencer = $this->conn->query($qReservasPorVencer)->fetch(PDO::FETCH_ASSOC);

        // Ventas de los últimos 7 días, para la gráfica semanal. Se
        // completan los días sin ventas con 0 para que la gráfica
        // siempre muestre la semana completa (Lun...Dom).
        $qVentasSemana = "SELECT DATE(fecha) AS dia, SUM(total) AS total
                          FROM ventas
                          WHERE fecha >= CURDATE() - INTERVAL 6 DAY
                            AND estado != 'ANULADA'
                          GROUP BY DATE(fecha)";
        $ventasSemanaRaw = $this->conn->query($qVentasSemana)->fetchAll(PDO::FETCH_ASSOC);

        $ventasPorDia = [];
        foreach ($ventasSemanaRaw as $fila) {
            $ventasPorDia[$fila['dia']] = (float) $fila['total'];
        }

        $nombresDias = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $ventasSemana = [];
        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-{$i} days"));
            $ventasSemana[] = [
                'dia' => $nombresDias[(int) date('w', strtotime($fecha))],
                'total' => $ventasPorDia[$fecha] ?? 0
            ];
        }

        // Últimos 5 movimientos de inventario (mismo criterio que usa
        // Movimiento::listar(), solo que limitado a 5 registros).
        $qMovimientos = "SELECT
                            m.fecha,
                            p.nombre AS producto,
                            tm.nombre AS concepto,
                            CASE
                                WHEN m.stock_nuevo >= m.stock_anterior THEN 'ENTRADA'
                                ELSE 'SALIDA'
                            END AS tipo,
                            m.cantidad
                          FROM movimientos m
                          INNER JOIN productos p ON m.id_producto = p.id_producto
                          INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                          ORDER BY m.fecha DESC, m.id_movimiento DESC
                          LIMIT 5";
        $movimientosRecientes = $this->conn->query($qMovimientos)->fetchAll(PDO::FETCH_ASSOC);

        return [
            // Existentes — sin cambios.
            "ventas_mes" => $ventasMes['total'],
            "productos_criticos" => $criticos['total_criticos'],
            "top_productos" => $topProductos,

            // Nuevos, para el rediseño del Dashboard.
            "ventas_dia" => $ventasDia['total'],
            "compras_dia" => $comprasDia['total'],
            "productos_stock_total" => (int) $stockTotal['total'],
            "reservas_activas" => (int) $reservasActivas['total'],
            "ventas_semana" => $ventasSemana,
            "alertas" => [
                "stock_bajo" => (int) $criticos['total_criticos'],
                "productos_agotados" => (int) $agotados['total'],
                "reservas_por_vencer" => (int) $reservasPorVencer['total']
            ],
            "movimientos_recientes" => $movimientosRecientes
        ];
    }
}
