<?php

require_once __DIR__ . '/../controllers/MovimientoController.php';

$controller = new MovimientoController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'Administrador', 'administrador', 'BODEGA', 'bodega', 'GERENTE', 'gerente']);
            $controller->listar();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de movimientos no válido.", null, 404);
        break;
}