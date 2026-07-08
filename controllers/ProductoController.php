<?php
require_once __DIR__ . '/../models/Producto.php';
require_once __DIR__ . '/../helpers/Response.php';

class ProductoController {
    private $db;
    private $productoModel;

    public function __construct($db) {
        $this->db = $db;
        $this->productoModel = new Producto($db);
    }

    public function listar($term = null) {
        if ($term) {
            $resultado = $this->productoModel->getBySearch($term);
        } else {
            $resultado = $this->productoModel->getAll();
        }
        Response::json("success", "Productos recuperados correctamente.", $resultado);
    }

    public function guardar($data) {
        // Validaciones básicas requeridas
        if (empty($data['nombre']) || empty($data['codigo_interno']) || empty($data['id_categoria']) || empty($data['id_marca']) || empty($data['id_ubicacion'])) {
            Response::json("error", "Faltan campos obligatorios para registrar el producto.", null, 400);
        }

        try {
            if ($this->productoModel->create($data)) {
                Response::json("success", "Producto registrado de manera exitosa.", null, 201);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                Response::json("error", "El código interno o de barras ya está registrado.", null, 409);
            }
            Response::json("error", "Error interno en el servidor: " . $e->getMessage(), null, 500);
        }
    }

    public function procesarAjuste($data, $id_usuario) {
        if (empty($data['id_producto']) || empty($data['id_tipo_movimiento']) || empty($data['cantidad'])) {
            Response::json("error", "Datos incompletas para procesar el ajuste de inventario.", null, 400);
        }

        try {
            $this->productoModel->ajustarStock(
                $data['id_producto'],
                $data['id_tipo_movimiento'],
                $id_usuario,
                $data['cantidad'],
                $data['observacion'] ?? 'Ajuste manual desde la aplicación'
            );
            Response::json("success", "Inventario actualizado y Kardex registrado.", null, 200);
        } catch (Exception $e) {
            Response::json("error", $e->getMessage(), null, 400);
        }
    }
}