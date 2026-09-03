<?php
require_once __DIR__ . '/../models/Dashboard.php';
require_once __DIR__ . '/../helpers/Response.php';

class DashboardController {
    private $dashboardModel;

    public function __construct($db) {
        $this->dashboardModel = new Dashboard($db);
    }

    public function mostrarKPIs() {
        $metricas = $this->dashboardModel->obtenerMetricas();
        Response::json("success", "Métricas de AgroArmijos cargadas.", $metricas);
    }

    /*
     * Serie de ventas para el selector de periodo del gráfico
     * (semana/mes/año). Whitelist explícita del parámetro: cualquier
     * valor que no sea uno de los tres esperados cae en 'semana' por
     * defecto, en vez de dejar pasar algo raro al modelo.
     */
    public function mostrarVentasPeriodo() {
        $periodo = $_GET['periodo'] ?? 'semana';

        if (!in_array($periodo, ['semana', 'mes', 'anio'], true)) {
            $periodo = 'semana';
        }

        $datos = $this->dashboardModel->obtenerVentasPorPeriodo($periodo);
        Response::json("success", "Serie de ventas cargada.", $datos);
    }
}