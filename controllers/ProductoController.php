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

    public function guardar($data, $id_usuario) {
        if (
            empty($data['nombre']) ||
            empty($data['codigo_interno']) ||
            empty($data['codigo_fabrica']) ||
            empty($data['id_categoria']) ||
            empty($data['id_marca']) ||
            empty($data['id_ubicacion'])
        ) {
            Response::json("error", "Faltan campos obligatorios para registrar el producto.", null, 400);
        }

        // FEATURE (GCS — feature/iva-por-producto): la tarifa es opcional
        // en el payload (por defecto 15%, ver Producto::create()), pero si
        // viene debe ser un porcentaje válido.
        if (isset($data['iva_tarifa']) && $data['iva_tarifa'] !== '' && (!is_numeric($data['iva_tarifa']) || $data['iva_tarifa'] < 0 || $data['iva_tarifa'] > 100)) {
            Response::json("error", "La tarifa de IVA debe ser un porcentaje entre 0 y 100.", null, 400);
        }

        try {
            if ($this->productoModel->create($data, $id_usuario)) {
                Response::json("success", "Producto registrado de manera exitosa.", null, 201);
            }
        } catch (PDOException $e) {
            error_log("[ProductoController::guardar] " . $e->getMessage());

            if ($e->getCode() == 23000) {
                Response::json("error", "El código interno, código de barras o código de fábrica ya está registrado.", null, 409);
            }

            Response::json("error", "Ocurrió un error al registrar el producto. Intenta nuevamente.", null, 500);
        }
    }

    public function actualizar($id, $data, $id_usuario) {
        if (empty($id)) {
            Response::json("error", "ID de producto no recibido.", null, 400);
        }

        if (
            empty($data['nombre']) ||
            empty($data['codigo_interno']) ||
            empty($data['codigo_fabrica']) ||
            empty($data['id_categoria']) ||
            empty($data['id_marca']) ||
            empty($data['id_ubicacion'])
        ) {
            Response::json("error", "Faltan campos obligatorios para actualizar el producto.", null, 400);
        }

        // FEATURE (GCS — feature/iva-por-producto): ver comentario en guardar().
        if (isset($data['iva_tarifa']) && $data['iva_tarifa'] !== '' && (!is_numeric($data['iva_tarifa']) || $data['iva_tarifa'] < 0 || $data['iva_tarifa'] > 100)) {
            Response::json("error", "La tarifa de IVA debe ser un porcentaje entre 0 y 100.", null, 400);
        }

        try {
            if ($this->productoModel->update($id, $data, $id_usuario)) {
                Response::json("success", "Producto actualizado correctamente.", null, 200);
            }
        } catch (PDOException $e) {
            error_log("[ProductoController::actualizar] " . $e->getMessage());

            if ($e->getCode() == 23000) {
                Response::json("error", "El código interno, código de barras o código de fábrica ya está registrado.", null, 409);
            }

            Response::json("error", "Ocurrió un error al actualizar el producto. Intenta nuevamente.", null, 500);
        }
    }

    public function eliminar($id, $id_usuario) {
        if (empty($id)) {
            Response::json("error", "ID de producto no recibido.", null, 400);
        }

        try {
            if ($this->productoModel->deleteLogic($id, $id_usuario)) {
                Response::json("success", "Producto dado de baja correctamente.", null, 200);
            }
        } catch (Exception $e) {
            error_log("[ProductoController::eliminar] " . $e->getMessage());
            Response::json("error", "Ocurrió un error al dar de baja el producto. Intenta nuevamente.", null, 500);
        }
    }

    public function procesarAjuste($data, $id_usuario) {
        if (empty($data['id_producto']) || empty($data['id_tipo_movimiento']) || empty($data['cantidad'])) {
            Response::json("error", "Datos incompletos para procesar el ajuste de inventario.", null, 400);
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
