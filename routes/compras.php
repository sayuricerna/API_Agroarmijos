<?php
require_once __DIR__ . '/../controllers/CompraController.php';

$compraController = new CompraController($db);
$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

if ($segments[1] === 'crear' && $method === 'POST') {
    $auth->checkRole($currentUser, ['ADMIN', 'COMPRAS']);
    $compraController->procesar($input_data, $currentUser['id_usuario']);
} else {
    Response::json("error", "Endpoint de compras no válido.", null, 404);
}