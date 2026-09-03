<?php
require_once __DIR__ . '/../controllers/DashboardController.php';

$dashboardController = new DashboardController($db);
$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

if ($segments[1] === 'kpis' && $method === 'GET') {
    $auth->checkRole($currentUser, ['ADMIN', 'GERENTE', 'VENDEDOR', 'BODEGA']);
    $dashboardController->mostrarKPIs();
} elseif ($segments[1] === 'ventas-periodo' && $method === 'GET') {
    $auth->checkRole($currentUser, ['ADMIN', 'GERENTE', 'VENDEDOR', 'BODEGA']);
    $dashboardController->mostrarVentasPeriodo();
} else {
    Response::json("error", "Endpoint de dashboard no válido.", null, 404);
}