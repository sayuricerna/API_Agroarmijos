<?php
require_once __DIR__ . '/../controllers/CatalogoController.php';

$controller = new CatalogoController($db);
$auth = new AuthMiddleware();
$currentUser = $auth->checkAuth(); 

$tabla = $modulo;

$primaryKeys = [
    'categorias' => 'id_categoria',
    'marcas' => 'id_marca',
    'clientes' => 'id_cliente',
    'proveedores' => 'id_proveedor'
];

if (!isset($primaryKeys[$tabla])) {
    Response::json("error", "Catálogo no permitido.", null, 400);
}

$pk = $primaryKeys[$tabla];

$campo_orden = ($tabla === 'proveedores') ? 'razon_social' : 'nombre';

if ($tabla === 'clientes' || $tabla === 'usuarios') {
    $campo_orden = 'nombres';
}

$action = isset($segments[1]) ? $segments[1] : '';

// Filtro opcional ?estado=activo|inactivo|todos para el listado. Si no
// viene o viene con un valor no reconocido, se queda en 'activo' (el
// comportamiento de siempre), así que ningún llamador existente que no
// mande este parámetro se ve afectado.
$estadosPermitidos = ['activo', 'inactivo', 'todos'];
$estadoFiltro = $_GET['estado'] ?? 'activo';
if (!in_array($estadoFiltro, $estadosPermitidos, true)) {
    $estadoFiltro = 'activo';
}

switch ($action) {
    case 'listar':
        if ($method === 'GET') {
            $controller->listar($tabla, $campo_orden, $estadoFiltro);
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

    case 'reactivar':
        // Mismo rol que 'eliminar': dar de baja y reactivar son la misma
        // operación (cambiar estado), así que exigen el mismo permiso.
        if ($method === 'PUT') {
            $auth->checkRole($currentUser, ['ADMIN']);
            $id = isset($segments[2]) ? $segments[2] : 0;
            $controller->reactivar($tabla, $pk, $id);
        } else {
            Response::json("error", "Método no permitido.", null, 405);
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