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

    public function compras($desde, $hasta) {
        if (empty($desde) || empty($hasta)) {
            Response::json("error", "Debe enviar fecha inicio y fecha fin.", null, 400);
        }

        $data = $this->model->comprasPorFecha($desde, $hasta);
        Response::json("success", "Reporte generado correctamente.", $data);
    }

    public function stockCritico() {
        $data = $this->model->stockCritico();
        Response::json("success", "Reporte generado correctamente.", $data);
    }

    public function productos($desde, $hasta) {
        if (empty($desde) || empty($hasta)) {
            Response::json("error", "Debe enviar fecha inicio y fecha fin.", null, 400);
        }

        $data = $this->model->productosPorFecha($desde, $hasta);
        Response::json("success", "Reporte generado correctamente.", $data);
    }

    public function clientes($desde, $hasta) {
        if (empty($desde) || empty($hasta)) {
            Response::json("error", "Debe enviar fecha inicio y fecha fin.", null, 400);
        }

        $data = $this->model->clientesPorFecha($desde, $hasta);
        Response::json("success", "Reporte generado correctamente.", $data);
    }

    public function proveedores($desde, $hasta) {
        if (empty($desde) || empty($hasta)) {
            Response::json("error", "Debe enviar fecha inicio y fecha fin.", null, 400);
        }

        $data = $this->model->proveedoresPorFecha($desde, $hasta);
        Response::json("success", "Reporte generado correctamente.", $data);
    }

    public function kardex($desde, $hasta) {
        if (empty($desde) || empty($hasta)) {
            Response::json("error", "Debe enviar fecha inicio y fecha fin.", null, 400);
        }

        $data = $this->model->kardexPorFecha($desde, $hasta);
        Response::json("success", "Reporte generado correctamente.", $data);
    }
}