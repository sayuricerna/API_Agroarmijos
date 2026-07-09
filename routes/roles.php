<?php

require_once __DIR__ . '/../controllers/RolController.php';

$controller = new RolController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {
    case 'listar':
        if ($method === 'GET') {
            $controller->listar();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de roles no válido.", null, 404);
        break;
}