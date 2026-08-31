<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../helpers/Response.php';

class UsuarioController {
    private $model;

    public function __construct($db) {
        $this->model = new Usuario($db);
    }

    public function listar() {
        $data = $this->model->listar();
        Response::json("success", "Usuarios cargados correctamente.", $data);
    }

    public function crear($data, $idActor) {
        if (empty($data['id_rol']) || empty($data['nombres']) || empty($data['apellidos']) || empty($data['usuario']) || empty($data['password'])) {
            Response::json("error", "Faltan campos obligatorios.", null, 400);
        }

        try {
            $data['cedula'] = $data['cedula'] ?? '';
            $data['telefono'] = $data['telefono'] ?? '';
            $data['correo'] = $data['correo'] ?? '';
            $data['foto'] = $data['foto'] ?? '';

            $this->model->crear($data, $idActor);
            Response::json("success", "Usuario registrado correctamente.", null, 201);

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                Response::json("error", "Usuario, correo o cédula ya registrados.", null, 409);
            }

            Response::json("error", "Error interno: " . $e->getMessage(), null, 500);
        }
    }

    public function actualizar($id, $data, $idActor) {
        if (empty($id)) {
            Response::json("error", "ID no recibido.", null, 400);
        }

        if (empty($data['id_rol']) || empty($data['nombres']) || empty($data['apellidos']) || empty($data['usuario'])) {
            Response::json("error", "Faltan campos obligatorios.", null, 400);
        }

        try {
            $data['cedula'] = $data['cedula'] ?? '';
            $data['telefono'] = $data['telefono'] ?? '';
            $data['correo'] = $data['correo'] ?? '';
            $data['foto'] = $data['foto'] ?? '';

            $this->model->actualizar($id, $data, $idActor);
            Response::json("success", "Usuario actualizado correctamente.", null, 200);

        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                Response::json("error", "Usuario, correo o cédula ya registrados.", null, 409);
            }

            Response::json("error", "Error interno: " . $e->getMessage(), null, 500);
        }
    }

    public function cambiarEstado($id, $estado, $idActor) {
        $this->model->cambiarEstado($id, $estado, $idActor);
        Response::json("success", "Estado actualizado correctamente.", null, 200);
    }

    public function cambiarPassword($id, $password, $idActor) {
        if (empty($password) || strlen($password) < 4) {
            Response::json("error", "La contraseña debe tener mínimo 4 caracteres.", null, 400);
        }

        $this->model->cambiarPassword($id, $password, $idActor);
        Response::json("success", "Contraseña actualizada correctamente.", null, 200);
    }
}