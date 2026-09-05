<?php
// index.php
require_once __DIR__ . '/config/Env.php';
Env::load(__DIR__ . '/.env');

require_once __DIR__ . '/config/Cors.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/config/Roles.php';
require_once __DIR__ . '/middlewares/AuthMiddleware.php';
require_once __DIR__ . '/helpers/Response.php';

$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('?', $request_uri, 2);
$path_clean = str_replace('/api_agroarmijos/', '', $uri_parts[0]);
$path_clean = str_replace('index.php/', '', $path_clean);
$path_clean = str_replace('index.php', '', $path_clean);
$segments = explode('/', trim($path_clean, '/'));
$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

$input_data = json_decode(file_get_contents("php://input"), true) ?? $_POST;
$modulo = isset($segments[0]) ? $segments[0] : '';

switch ($modulo) {
    case 'auth':
        require_once __DIR__ . '/routes/auth.php';
        break;

    case 'productos':
        require_once __DIR__ . '/routes/productos.php';
        break;

    case 'ventas':
        require_once __DIR__ . '/routes/ventas.php';
        break;

    case 'compras':
        require_once __DIR__ . '/routes/compras.php';
        break;

    case 'dashboard':
        require_once __DIR__ . '/routes/dashboard.php';
        break;

    case 'movimientos':
        require_once __DIR__ . '/routes/movimientos.php';
        break;
    case 'reportes':
        require_once __DIR__ . '/routes/reportes.php';
        break;
    case 'roles':
        require_once __DIR__ . '/routes/roles.php';
        break;
    case 'categorias':
    case 'marcas':
    case 'proveedores':
    case 'clientes':
    case 'ubicaciones':
        require_once __DIR__ . '/routes/catalogos.php';
        break;
    case 'usuarios':
        require_once __DIR__ . '/routes/usuarios.php';
        break;
    case 'reservas':
        require_once __DIR__ . '/routes/reservas.php';
        break;
    case 'auditoria':
        require_once __DIR__ . '/routes/auditoria.php';
        break;
    default:
        Response::json("error", "Módulo no encontrado en el ERP AgroArmijos.", null, 404);
        break;
}
