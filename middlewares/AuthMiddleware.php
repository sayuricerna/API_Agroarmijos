<?php
require_once __DIR__ . '/../config/Jwt.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    private $jwtHandler;

    public function __construct() {
        $this->jwtHandler = new JwtHandler();
    }

    public function checkAuth() {
        $headers = apache_request_headers();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

        if (empty($authHeader) && isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }

        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::json("error", "Acceso denegado. Token requerido.", null, 401);
        }

        $token = $matches[1];
        $userData = $this->jwtHandler->validateToken($token);

        if (!$userData) {
            Response::json("error", "Sesión inválida o expirada.", null, 401);
        }

        return $userData; 
    }

    public function checkRole($userData, $allowedRoles) {
        if (!in_array($userData['rol'], $allowedRoles)) {
            Response::json("error", "No tiene permisos para realizar esta acción.", null, 403);
        }
    }
}