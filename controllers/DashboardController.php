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
}