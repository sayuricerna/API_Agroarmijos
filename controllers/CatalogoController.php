<?php
require_once __DIR__ . '/../models/Catalogo.php';
require_once __DIR__ . '/../helpers/Response.php';

class CatalogoController {
    private $model;

    public function __construct($db) {
        $this->model = new Catalogo($db);
    }

    public function listar($tabla, $orden, $estadoFiltro = 'activo') {
        $res = $this->model->listarTodo($tabla, $orden, $estadoFiltro);
        Response::json("success", "Datos cargados correctamente.", $res);
    }

    public function guardar($tabla, $data, $idUsuario) {
        if ($tabla === 'clientes') {

            $documento = $data['numero_documento'] ?? '';
            $telefono = $data['telefono'] ?? '';

            if ($this->model->existeDocumentoCliente($documento)) {
    Response::json("error", "Ya existe un cliente con esa cédula o RUC.", null, 409);
}
            if (!preg_match('/^[0-9]{10}$|^[0-9]{13}$/', $documento)) {
                Response::json(
                    "error",
                    "El documento debe tener 10 dígitos para cédula o 13 dígitos para RUC.",
                    null,
                    400
                );
            }

            if (!empty($telefono) && !preg_match('/^[0-9]{1,10}$/', $telefono)) {
                Response::json(
                    "error",
                    "El teléfono solo debe contener números y máximo 10 dígitos.",
                    null,
                    400
                );
            }

            $data['tipo_documento'] = strlen($documento) === 13 ? 'RUC' : 'CEDULA';
        }
        try {
            if ($this->model->insertar($tabla, $data, $idUsuario)) {
                Response::json("success", "Registro almacenado con éxito.", null, 201);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                Response::json("error", "El identificador (RUC/Cédula/Nombre) ya se encuentra registrado.", null, 409);
            }
            Response::json("error", "Error interno: " . $e->getMessage(), null, 500);
        }
    }

    public function eliminar($tabla, $pk, $id, $idUsuario) {
        if ($this->model->eliminarLogico($tabla, $pk, $id, $idUsuario)) {
            Response::json("success", "Registro dado de baja correctamente.");
        } else {
            Response::json("error", "No se pudo eliminar el registro.", null, 400);
        }
    }

    public function reactivar($tabla, $pk, $id, $idUsuario) {
        if ($this->model->reactivarLogico($tabla, $pk, $id, $idUsuario)) {
            Response::json("success", "Registro reactivado correctamente.");
        } else {
            Response::json("error", "No se pudo reactivar el registro.", null, 400);
        }
    }
    public function actualizar($tabla, $pk, $id, $data, $idUsuario) {
        if ($tabla === 'clientes') {

    $documento = $data['numero_documento'] ?? '';
    $telefono = $data['telefono'] ?? '';

    if ($this->model->existeDocumentoCliente($documento, $id)) {
    Response::json("error", "Ya existe otro cliente con esa cédula o RUC.", null, 409);
}
    if (!preg_match('/^[0-9]{10}$|^[0-9]{13}$/', $documento)) {
        Response::json("error", "El documento debe tener 10 dígitos para cédula o 13 dígitos para RUC.", null, 400);
    }

    if (!empty($telefono) && !preg_match('/^[0-9]{1,10}$/', $telefono)) {
        Response::json("error", "El teléfono solo debe contener números y máximo 10 dígitos.", null, 400);
    }

    $data['tipo_documento'] = strlen($documento) === 13 ? 'RUC' : 'CEDULA';
}
    try {
        if ($this->model->actualizar($tabla, $pk, $id, $data, $idUsuario)) {
            Response::json("success", "Registro actualizado correctamente.", null, 200);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            Response::json("error", "El identificador ya se encuentra registrado.", null, 409);
        }

        Response::json("error", "Error interno: " . $e->getMessage(), null, 500);
    }
}
}