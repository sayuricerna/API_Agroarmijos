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

    default:
        Response::json("error", "Endpoint de reportes no válido.", null, 404);
        break;
}