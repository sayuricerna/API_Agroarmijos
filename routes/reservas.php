<?php

require_once __DIR__ . '/../controllers/ReservaController.php';

$controller = new ReservaController($db);

$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth();

$action = $segments[1] ?? '';

switch ($action) {
    case 'listar':
        if ($method !== 'GET') {
            Response::json(
                "error",
                "Método no permitido.",
                null,
                405
            );
        }

        $auth->checkRole(
            $currentUser,
            [
                'ADMIN',
                'Administrador',
                'administrador',
                'VENDEDOR',
                'vendedor',
                'BODEGA',
                'bodega',
                'GERENTE',
                'gerente'
            ]
        );

        $controller->listar();
        break;

    case 'crear':
        if ($method !== 'POST') {
            Response::json(
                "error",
                "Método no permitido.",
                null,
                405
            );
        }

        $auth->checkRole(
            $currentUser,
            [
                'ADMIN',
                'Administrador',
                'administrador',
                'VENDEDOR',
                'vendedor'
            ]
        );

        $controller->crear(
            $input_data,
            (int) $currentUser['id_usuario']
        );
        break;

    case 'cancelar':
        if ($method !== 'PUT') {
            Response::json(
                "error",
                "Método no permitido.",
                null,
                405
            );
        }

        $auth->checkRole(
            $currentUser,
            [
                'ADMIN',
                'Administrador',
                'administrador',
                'VENDEDOR',
                'vendedor'
            ]
        );

        $idReserva = (int) ($segments[2] ?? 0);
        $controller->cancelar($idReserva, (int) $currentUser['id_usuario']);
        break;

    case 'confirmar':
        if ($method !== 'PUT') {
            Response::json(
                "error",
                "Método no permitido.",
                null,
                405
            );
        }

        $auth->checkRole(
            $currentUser,
            [
                'ADMIN',
                'Administrador',
                'administrador',
                'VENDEDOR',
                'vendedor'
            ]
        );

        $idReserva = (int) ($segments[2] ?? 0);
        $controller->confirmar($idReserva, (int) $currentUser['id_usuario']);
        break;

    default:
        Response::json(
            "error",
            "Endpoint de reservas no válido.",
            null,
            404
        );
}