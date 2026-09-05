<?php

require_once __DIR__ . '/../controllers/ProductoController.php';

$productoController = new ProductoController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $productoController->listar($_GET['search'] ?? null);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'crear':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, [Roles::ADMIN, Roles::BODEGA]);
            $productoController->guardar($input_data, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'actualizar':
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, [Roles::ADMIN, Roles::BODEGA]);
            $id = $segments[2] ?? 0;
            $productoController->actualizar($id, $input_data, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'eliminar':
        if ($method === 'DELETE') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $id = $segments[2] ?? 0;
            $productoController->eliminar($id, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'ajustar':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, [Roles::ADMIN, Roles::BODEGA]);
            $productoController->procesarAjuste($input_data, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de productos no válido.", null, 404);
        break;
}