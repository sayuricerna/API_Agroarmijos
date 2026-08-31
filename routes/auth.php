<?php
require_once __DIR__ . '/../controllers/AuthController.php';

$authController = new AuthController($db);
$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {
    case 'login':
        if ($method === 'POST') {
            $authController->login($input_data);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'logout':
        if ($method === 'POST') {
            $auth = new AuthMiddleware();
            $currentUser = $auth->checkAuth();
            $authController->logout((int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Acción de autenticación no encontrada.", null, 404);
        break;
}