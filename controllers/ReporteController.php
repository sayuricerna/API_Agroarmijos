<?php

require_once __DIR__ . '/../models/Reporte.php';
require_once __DIR__ . '/../helpers/Response.php';

class ReporteController {
    private $model;

    public function __construct($db) {
        $this->model = new Reporte($db);
    }

    public function ventas($desde, $hasta) {
        if (empty($desde) || empty($hasta)) {
            Response::json("error", "Debe enviar fecha inicio y fecha fin.", null, 400);
        }

        $data = $this->model->ventasPorFecha($desde, $hasta);
        Response::json("success", "Reporte generado correctamente.", $data);
    }
}