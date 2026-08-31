<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../config/Jwt.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Auditor.php';

class AuthController {
    private $db;
    private $usuarioModel;
    private $jwtHandler;

    public function __construct($db) {
        $this->db = $db;
        $this->usuarioModel = new Usuario($db);
        $this->jwtHandler = new JwtHandler();
    }

    public function login($data) {
        if (!isset($data['usuario']) || !isset($data['password'])) {
            Response::json("error", "Faltan datos obligatorios.", null, 400);
        }

        $user = $this->usuarioModel->login($data['usuario']);

        if (!$user) {
            Response::json("error", "Usuario o correo electrónico incorrectos.", null, 401);
        }

        if ($user['estado'] != 1) {
            Response::json("error", "Su usuario se encuentra inactivo.", null, 403);
        }

        if (password_verify($data['password'], $user['password'])) {
            $this->usuarioModel->updateLastLogin($user['id_usuario']);

            $token = $this->jwtHandler->generateToken(
                $user['id_usuario'], 
                $user['usuario'], 
                $user['rol_nombre']
            );

            Auditor::registrarSeguro(
                $this->db, $user['id_usuario'], 'Autenticación', 'usuarios', (int) $user['id_usuario'], 'INICIAR_SESION',
                "Inició sesión como \"{$user['usuario']}\"."
            );

            Response::json("success", "Autenticación correcta.", [
                "token" => $token,
                "usuario" => [
                    "id_usuario" => $user['id_usuario'],
                    "nombres"    => $user['nombres'],
                    "apellidos"  => $user['apellidos'],
                    "rol"        => $user['rol_nombre'],
                    "foto"       => $user['foto']
                ]
            ], 200);
        } else {
            Response::json("error", "Usuario o contraseña incorrectos.", null, 401);
        }
    }

    public function logout($id_usuario) {
        Auditor::registrarSeguro(
            $this->db, $id_usuario, 'Autenticación', 'usuarios', (int) $id_usuario, 'CERRAR_SESION',
            "Cerró sesión."
        );
        Response::json("success", "Sesión cerrada.", null, 200);
    }
}