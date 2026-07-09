<?php

require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../helpers/Response.php';

class RolController {
    private $model;

    public function __construct($db) {
        $this->model = new Rol($db);
    }

    public function listar() {
        $data = $this->model->listar();
        Response::json("success", "Roles cargados correctamente.", $data);
    }
}