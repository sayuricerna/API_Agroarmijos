<?php
require_once __DIR__ . '/../models/Venta.php';
require_once __DIR__ . '/../helpers/Response.php';

class VentaController {
    private $ventaModel;

    public function __construct($db) {
        $this->ventaModel = new Venta($db);
    }

    public function procesar($data, $id_usuario) {
        if (empty($data['id_cliente']) || empty($data['productos'])) {
            Response::json("error", "Datos de venta incompletos.", null, 400);
        }
        try {
            $id = $this->ventaModel->crearVenta($data, $id_usuario);
            Response::json("success", "Venta efectuada con éxito.", ["id_venta" => $id], 201);
        } catch (Exception $e) {
            Response::json("error", $e->getMessage(), null, 400);
        }
    }
    public function listar() {
    try {
        $ventas = $this->ventaModel->listarVentas();
        Response::json("success", "Ventas cargadas correctamente.", $ventas);
    } catch (Exception $e) {
        Response::json("error", $e->getMessage(), null, 500);
    }
}
}