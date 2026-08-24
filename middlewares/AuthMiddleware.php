<?php
require_once __DIR__ . '/../config/Jwt.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthMiddleware {
    private $jwtHandler;

    public function __construct() {
        $this->jwtHandler = new JwtHandler();
    }

    public function checkAuth() {
        $authHeader = $this->obtenerAuthorizationHeader();

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

    /*
     * apache_request_headers() no siempre entrega el header Authorization
     * de forma confiable (depende de cómo XAMPP/Apache esté sirviendo
     * PHP, y de si hay más de una petición casi simultánea a la vez —
     * exactamente lo que pasaba al navegar rápido entre módulos, donde
     * ngOnInit()+ionViewWillEnter() disparaban la misma petición dos
     * veces). Por eso ahora se revisan también las variables de $_SERVER
     * que PHP/Apache suelen poblar como respaldo, antes de rendirse.
     */
    private function obtenerAuthorizationHeader() {
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();

            foreach ($headers as $nombre => $valor) {
                if (strtolower($nombre) === 'authorization' && !empty($valor)) {
                    return $valor;
                }
            }
        }

        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            foreach ($headers as $nombre => $valor) {
                if (strtolower($nombre) === 'authorization' && !empty($valor)) {
                    return $valor;
                }
            }
        }

        return '';
    }
}