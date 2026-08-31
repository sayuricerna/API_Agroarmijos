<?php

require_once __DIR__ . '/../controllers/VentaController.php';

$ventaController = new VentaController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'VENDEDOR', 'GERENTE']);
            $ventaController->listar();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'crear':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, ['ADMIN', 'VENDEDOR']);
            $ventaController->procesar($input_data, $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'anular':
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, ['ADMIN']);
            $idVenta = (int) ($segments[2] ?? 0);
            $ventaController->anular($idVenta, (int) $currentUser['id_usuario'], $input_data['motivo'] ?? '');
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de ventas no válido.", null, 404);
        break;
}