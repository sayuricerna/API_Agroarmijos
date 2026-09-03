<?php
class Dashboard {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function obtenerMetricas() {

        // ---------------------------------------------------------------
        // Métricas existentes (mismo nombre de clave, para no romper nada
        // que ya las consuma). $qCriticos es la única excepción: su
        // criterio se ajustó en LB-019, ver comentario junto al query.
        // ---------------------------------------------------------------

        $qVentasMes = "SELECT IFNULL(SUM(total), 0) AS total
                       FROM ventas
                       WHERE MONTH(fecha) = MONTH(CURDATE())
                         AND YEAR(fecha) = YEAR(CURDATE())
                         AND estado != 'ANULADA'";
        $ventasMes = $this->conn->query($qVentasMes)->fetch(PDO::FETCH_ASSOC);

        /*
         * FIX (GCS — LB-019): antes este conteo incluía también los
         * productos agotados (stock_actual = 0), así que la tarjeta
         * "Stock bajo" y la alerta del mismo nombre mostraban un número
         * que ya traía mezclados los agotados — mientras que Productos
         * (Productos::estadoProducto()) siempre los mostró aparte. Se
         * agrega "stock_actual > 0" para que ambas pantallas cuenten
         * exactamente lo mismo bajo el mismo criterio.
         */
        $qCriticos = "SELECT COUNT(*) AS total_criticos
                      FROM productos
                      WHERE stock_actual > 0 AND stock_actual <= stock_minimo AND estado = 1";
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
        $ventasSemana = $this->calcularVentasSemana();

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

    /*
     * FEATURE (GCS — feature/dashboard-tarjetas-clicables-grafico-periodos):
     * serie de ventas de los últimos 7 días, en un formato uniforme
     * (etiqueta/total) que también usan las series de mes y año de
     * obtenerVentasPorPeriodo(). Se mantiene como método aparte (en vez
     * de generalizarlo a N días) porque la agrupación real es distinta
     * en cada caso: por día en la semana, por mes en el año, por año en
     * el histórico — no es el mismo query con un parámetro distinto.
     */
    private function calcularVentasSemana() {
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
                'etiqueta' => $nombresDias[(int) date('w', strtotime($fecha))],
                'total' => $ventasPorDia[$fecha] ?? 0
            ];
        }

        return $ventasSemana;
    }

    /*
     * Serie de ventas de los últimos 12 meses (mes calendario), para la
     * vista "Mensual" del gráfico. Se completan los meses sin ventas
     * con 0, igual criterio que la semana.
     */
    private function calcularVentasMensual() {
        $qVentasMes = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS periodo, SUM(total) AS total
                       FROM ventas
                       WHERE fecha >= (CURDATE() - INTERVAL 11 MONTH)
                         AND estado != 'ANULADA'
                       GROUP BY periodo";
        $ventasMesRaw = $this->conn->query($qVentasMes)->fetchAll(PDO::FETCH_ASSOC);

        $ventasPorMes = [];
        foreach ($ventasMesRaw as $fila) {
            $ventasPorMes[$fila['periodo']] = (float) $fila['total'];
        }

        $nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $ventasMensual = [];
        for ($i = 11; $i >= 0; $i--) {
            $referencia = strtotime(date('Y-m-01') . " -{$i} months");
            $periodo = date('Y-m', $referencia);
            $ventasMensual[] = [
                'etiqueta' => $nombresMeses[(int) date('n', $referencia) - 1],
                'total' => $ventasPorMes[$periodo] ?? 0
            ];
        }

        return $ventasMensual;
    }

    /*
     * Serie de ventas de los últimos 5 años (año calendario), para la
     * vista "Anual" del gráfico. Mismo criterio de completar con 0 los
     * años sin ventas registradas.
     */
    private function calcularVentasAnual() {
        $qVentasAnio = "SELECT YEAR(fecha) AS anio, SUM(total) AS total
                        FROM ventas
                        WHERE fecha >= (CURDATE() - INTERVAL 4 YEAR)
                          AND estado != 'ANULADA'
                        GROUP BY anio";
        $ventasAnioRaw = $this->conn->query($qVentasAnio)->fetchAll(PDO::FETCH_ASSOC);

        $ventasPorAnio = [];
        foreach ($ventasAnioRaw as $fila) {
            $ventasPorAnio[(int) $fila['anio']] = (float) $fila['total'];
        }

        $anioActual = (int) date('Y');
        $ventasAnual = [];
        for ($i = 4; $i >= 0; $i--) {
            $anio = $anioActual - $i;
            $ventasAnual[] = [
                'etiqueta' => (string) $anio,
                'total' => $ventasPorAnio[$anio] ?? 0
            ];
        }

        return $ventasAnual;
    }

    /*
     * Punto de entrada para el selector de periodo del gráfico de
     * ventas del Dashboard (semana/mes/año). Es un método nuevo y
     * separado de obtenerMetricas() a propósito: el payload de /kpis
     * que ya consume el Dashboard no cambia en nada, así que esto no
     * afecta el funcionamiento actual — solo se calcula cuando el
     * usuario cambia el selector en el frontend.
     */
    public function obtenerVentasPorPeriodo($periodo) {
        switch ($periodo) {
            case 'mes':
                return $this->calcularVentasMensual();
            case 'anio':
                return $this->calcularVentasAnual();
            case 'semana':
            default:
                return $this->calcularVentasSemana();
        }
    }
}
