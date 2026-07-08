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
}