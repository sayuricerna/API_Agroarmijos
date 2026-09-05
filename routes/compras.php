<?php
require_once __DIR__ . '/../controllers/CompraController.php';

$compraController = new CompraController($db);
$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, [Roles::ADMIN, Roles::BODEGA]);
            $compraController->listar();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'detalle':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, [Roles::ADMIN, Roles::BODEGA]);
            $idCompra = (int) ($segments[2] ?? 0);
            $compraController->detalle($idCompra);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'crear':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, [Roles::ADMIN, Roles::BODEGA]);
            $compraController->procesar($input_data, $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'anular':
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $idCompra = (int) ($segments[2] ?? 0);
            $compraController->anular($idCompra, (int) $currentUser['id_usuario'], $input_data['motivo'] ?? '');
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de compras no válido.", null, 404);
        break;
}