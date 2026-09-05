<?php

require_once __DIR__ . '/../controllers/UsuarioController.php';

$controller = new UsuarioController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {

    case 'listar':
        if ($method === 'GET') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $controller->listar();
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'crear':
        if ($method === 'POST') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $controller->crear($input_data, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'actualizar':
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $id = $segments[2] ?? 0;
            $controller->actualizar($id, $input_data, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'estado':
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $id = $segments[2] ?? 0;
            $estado = $input_data['estado'] ?? 1;
            $controller->cambiarEstado($id, $estado, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    case 'password':
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, [Roles::ADMIN]);
            $id = $segments[2] ?? 0;
            $password = $input_data['password'] ?? '';
            $controller->cambiarPassword($id, $password, (int) $currentUser['id_usuario']);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
        }
        break;

    default:
        Response::json("error", "Endpoint de usuarios no válido.", null, 404);
        break;
}