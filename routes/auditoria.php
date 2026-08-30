<?php

require_once __DIR__ . '/../controllers/AuditoriaController.php';

$controller = new AuditoriaController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

// Auditoría es información administrativa sensible (quién hizo qué en
// todo el sistema): mismo criterio que Usuarios/Roles, solo ADMIN.
// El backend valida el rol aquí — el frontend oculta el menú, pero eso
// nunca es suficiente por sí solo.
$auth->checkRole($currentUser, ['ADMIN', 'administrador', 'Administrador']);

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $filtros = [
                'id_usuario' => $_GET['usuario'] ?? null,
                'accion'     => $_GET['accion'] ?? null,
                'modulo'     => $_GET['modulo'] ?? null,
                'desde'      => $_GET['desde'] ?? null,
                'hasta'      => $_GET['hasta'] ?? null,
                'buscar'     => $_GET['buscar'] ?? null,
                'page'       => $_GET['page'] ?? 1,
                'per_page'   => $_GET['per_page'] ?? 20,
            ];

            $controller->listar($filtros);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'detalle':
        if ($method === 'GET') {
            $id = isset($segments[2]) ? $segments[2] : null;
            $controller->detalle($id);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'filtros':
        if ($method === 'GET') {
            $controller->filtros();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de auditoría no válido.", null, 404);
        break;
}
