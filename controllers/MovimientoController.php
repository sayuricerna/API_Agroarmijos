<?php

require_once __DIR__ . '/../models/Movimiento.php';
require_once __DIR__ . '/../helpers/Response.php';

class MovimientoController {
    private $model;

    public function __construct($db) {
        $this->model = new Movimiento($db);
    }

    public function listar() {
        $data = $this->model->listar();
        Response::json("success", "Movimientos cargados correctamente.", $data);
    }
}