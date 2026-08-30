<?php

require_once __DIR__ . '/../models/Auditoria.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuditoriaController {
    private $model;

    public function __construct($db) {
        $this->model = new Auditoria($db);
    }

    public function listar($filtros = []) {
        try {
            $resultado = $this->model->listar($filtros);
            Response::json("success", "Auditoría cargada correctamente.", $resultado);
        } catch (Exception $e) {
            error_log("[AuditoriaController::listar] " . $e->getMessage());
            Response::json("error", "No se pudo cargar la auditoría.", null, 500);
        }
    }

    public function detalle($id) {
        if (empty($id) || !is_numeric($id)) {
            Response::json("error", "ID de registro no válido.", null, 400);
        }

        try {
            $registro = $this->model->detalle((int) $id);

            if (!$registro) {
                Response::json("error", "El registro de auditoría solicitado no existe.", null, 404);
            }

            Response::json("success", "Detalle de auditoría cargado correctamente.", $registro);
        } catch (Exception $e) {
            error_log("[AuditoriaController::detalle] " . $e->getMessage());
            Response::json("error", "No se pudo cargar el detalle de auditoría.", null, 500);
        }
    }

    public function filtros() {
        try {
            $data = $this->model->filtrosDisponibles();
            Response::json("success", "Filtros cargados correctamente.", $data);
        } catch (Exception $e) {
            error_log("[AuditoriaController::filtros] " . $e->getMessage());
            Response::json("error", "No se pudieron cargar los filtros.", null, 500);
        }
    }
}
