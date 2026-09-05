<?php

require_once __DIR__ . '/../controllers/MovimientoController.php';

$controller = new MovimientoController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$rolesPermitidos = [Roles::ADMIN, Roles::BODEGA, Roles::GERENTE];

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, $rolesPermitidos);

            $filtros = [
                'desde'              => $_GET['desde'] ?? null,
                'hasta'              => $_GET['hasta'] ?? null,
                'fecha'              => $_GET['fecha'] ?? null,
                'id_tipo_movimiento' => $_GET['id_tipo_movimiento'] ?? null,
                'id_producto'        => $_GET['id_producto'] ?? null,
                'id_usuario'         => $_GET['id_usuario'] ?? null,
                'q'                  => $_GET['q'] ?? null,
                'page'               => $_GET['page'] ?? 1,
                'per_page'           => $_GET['per_page'] ?? 20,
            ];

            $controller->listar($filtros);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'detalle':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, $rolesPermitidos);
            $id = isset($segments[2]) ? $segments[2] : null;
            $controller->detalle($id);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'filtros':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, $rolesPermitidos);
            $controller->filtros();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de movimientos no válido.", null, 404);
        break;
}
