<?php
require_once __DIR__ . '/../controllers/CatalogoController.php';

$controller = new CatalogoController($db);
$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth(); 


$tabla = $modulo; 
$pk = 'id_' . rtrim($tabla, 's'); 
$campo_orden = ($tabla === 'proveedores') ? 'razon_social' : 'nombre';
if ($tabla === 'clientes' || $tabla === 'usuarios') $campo_orden = 'nombres';

$action = isset($segments[1]) ? $segments[1] : '';

switch ($action) {
    case 'listar':
        if ($method === 'GET') {
            $controller->listar($tabla, $campo_orden);
        }
        break;

    case 'crear':
        if ($method === 'POST') {
            if ($tabla === 'clientes') {
                $auth->checkRole($currentUser, ['ADMIN', 'VENDEDOR']);
            } else {
                $auth->checkRole($currentUser, ['ADMIN', 'COMPRAS', 'BODEGA']);
            }
            $controller->guardar($tabla, $input_data);
        }
        break;

    case 'eliminar':
        if ($method === 'DELETE') {
            $auth->checkRole($currentUser, ['ADMIN']); 
            $id = isset($segments[2]) ? $segments[2] : 0;
            $controller->eliminar($tabla, $pk, $id);
        }
        break;
        case 'actualizar':
    if ($method === 'PUT') {
        if ($tabla === 'clientes') {
            $auth->checkRole($currentUser, ['ADMIN', 'VENDEDOR']);
        } else {
            $auth->checkRole($currentUser, ['ADMIN', 'COMPRAS', 'BODEGA']);
        }

        $id = isset($segments[2]) ? $segments[2] : 0;
        $controller->actualizar($tabla, $pk, $id, $input_data);
    } else {
        Response::json("error", "Método no permitido.", null, 405);
    }
    break;

    default:
        Response::json("error", "Operación no soportada en este catálogo.", null, 404);
        break;
}