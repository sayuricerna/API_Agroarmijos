<?php
require_once __DIR__ . '/../models/Compra.php';
require_once __DIR__ . '/../helpers/Response.php';

class CompraController {
    private $compraModel;

    public function __construct($db) {
        $this->compraModel = new Compra($db);
    }

    public function procesar($data, $id_usuario) {
        if (empty($data['id_proveedor']) || empty($data['numero_factura']) || empty($data['productos'])) {
            Response::json("error", "Datos de compra incompletos.", null, 400);
        }
        try {
            $id = $this->compraModel->registrarCompra($data, $id_usuario);
            Response::json("success", "Compra registrada e inventario aumentado.", ["id_compra" => $id], 201);
        } catch (Exception $e) {
            Response::json("error", $e->getMessage(), null, 400);
        }
    }

    public function listar() {
        try {
            $compras = $this->compraModel->listarCompras();
            Response::json("success", "Compras cargadas correctamente.", $compras);
        } catch (Exception $e) {
            Response::json("error", $e->getMessage(), null, 500);
        }
    }

    public function detalle($idCompra) {
        if (empty($idCompra) || $idCompra <= 0) {
            Response::json("error", "ID de compra no válido.", null, 400);
        }

        try {
            $lineas = $this->compraModel->obtenerLineas($idCompra);
            Response::json("success", "Detalle de compra cargado correctamente.", $lineas);
        } catch (Exception $e) {
            Response::json("error", $e->getMessage(), null, 500);
        }
    }

    public function anular($idCompra, $idUsuario, $motivo) {
        if (empty($idCompra) || $idCompra <= 0) {
            Response::json("error", "ID de compra no válido.", null, 400);
        }

        $motivo = trim((string) $motivo);

        if ($motivo === '') {
            Response::json("error", "Debe indicar un motivo para anular la compra.", null, 400);
        }

        try {
            $this->compraModel->anular($idCompra, $idUsuario, $motivo);
            Response::json("success", "Compra anulada y stock revertido.", null, 200);
        } catch (Exception $e) {
            Response::json("error", $e->getMessage(), null, 400);
        }
    }
}