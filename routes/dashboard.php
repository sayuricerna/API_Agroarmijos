<?php
require_once __DIR__ . '/../controllers/DashboardController.php';

$dashboardController = new DashboardController($db);
$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

if ($segments[1] === 'kpis' && $method === 'GET') {
    $auth->checkRole($currentUser, ['ADMIN', 'GERENTE', 'VENDEDOR', 'BODEGA']);
    $dashboardController->mostrarKPIs();
} else {
    Response::json("error", "Endpoint de dashboard no válido.", null, 404);
}