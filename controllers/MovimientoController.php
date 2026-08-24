<?php

require_once __DIR__ . '/../models/Movimiento.php';
require_once __DIR__ . '/../helpers/Response.php';

class MovimientoController {
    private $model;

    public function __construct($db) {
        $this->model = new Movimiento($db);
    }

    public function listar($filtros = []) {
        try {
            $resultado = $this->model->listar($filtros);
            Response::json("success", "Movimientos cargados correctamente.", $resultado);
        } catch (Exception $e) {
            error_log("[MovimientoController::listar] " . $e->getMessage());
            Response::json("error", "No se pudieron cargar los movimientos.", null, 500);
        }
    }

    public function detalle($id) {
        if (empty($id) || !is_numeric($id)) {
            Response::json("error", "ID de movimiento no válido.", null, 400);
        }

        try {
            $mov = $this->model->detalle((int) $id);

            if (!$mov) {
                Response::json("error", "El movimiento solicitado no existe.", null, 404);
            }

            Response::json("success", "Detalle del movimiento cargado correctamente.", $mov);
        } catch (Exception $e) {
            error_log("[MovimientoController::detalle] " . $e->getMessage());
            Response::json("error", "No se pudo cargar el detalle del movimiento.", null, 500);
        }
    }

    public function filtros() {
        try {
            $data = $this->model->filtrosDisponibles();
            Response::json("success", "Filtros cargados correctamente.", $data);
        } catch (Exception $e) {
            error_log("[MovimientoController::filtros] " . $e->getMessage());
            Response::json("error", "No se pudieron cargar los filtros.", null, 500);
        }
    }
}
