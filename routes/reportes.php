<?php

require_once __DIR__ . '/../controllers/ReporteController.php';

$controller = new ReporteController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'ventas':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE', 'VENDEDOR']);
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $controller->ventas($desde, $hasta);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'compras':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE']);
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $controller->compras($desde, $hasta);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'stock-critico':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE']);
            $controller->stockCritico();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'productos':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE']);
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $controller->productos($desde, $hasta);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'clientes':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE']);
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $controller->clientes($desde, $hasta);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'proveedores':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE']);
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $controller->proveedores($desde, $hasta);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'kardex':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, ['ADMIN', 'GERENTE']);
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';
            $controller->kardex($desde, $hasta);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de reportes no válido.", null, 404);
        break;
}