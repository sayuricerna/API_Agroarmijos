<?php
require_once __DIR__ . '/../controllers/ProductoController.php';

$productoController = new ProductoController($db);
$auth = new AuthMiddleware();

$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {
    case 'listar':
        if ($method === 'GET') {
            $term = isset($_GET['search']) ? $_GET['search'] : null;
            $productoController->listar($term);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'crear':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, ['ADMIN', 'BODEGA', 'COMPRAS']);
            $productoController->guardar($input_data);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'ajustar-stock':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, ['ADMIN', 'BODEGA']);
            $productoController->procesarAjuste($input_data, $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Acción del catálogo de productos no encontrada.", null, 404);
        break;
}