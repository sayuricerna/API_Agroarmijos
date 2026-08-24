<?php

class Movimiento {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /*
     * Mapa de los 8 tipos reales de `tipos_movimiento` hacia:
     *   - "general": el badge de dirección (ENTRADA / SALIDA / AJUSTE / RESERVA)
     *   - "etiqueta": la descripción entendible para el usuario ("Entrada por compra", etc.)
     *
     * No se modifican los valores almacenados en la base de datos, solo se
     * traducen para la presentación. Si en el futuro se agrega un tipo nuevo
     * y no está en este mapa, enriquecerTipo() usa un fallback defensivo.
     */
    private static function mapaTipos() {
        return [
            'COMPRA'               => ['general' => 'ENTRADA', 'etiqueta' => 'Entrada por compra'],
            'VENTA'                 => ['general' => 'SALIDA',  'etiqueta' => 'Salida por venta'],
            'AJUSTE_POSITIVO'       => ['general' => 'AJUSTE',  'etiqueta' => 'Ajuste de inventario (incremento)'],
            'AJUSTE_NEGATIVO'       => ['general' => 'AJUSTE',  'etiqueta' => 'Ajuste de inventario (disminución)'],
            'RESERVA'               => ['general' => 'RESERVA', 'etiqueta' => 'Reserva de producto'],
            'CANCELACION_RESERVA'   => ['general' => 'RESERVA', 'etiqueta' => 'Liberación de reserva'],
            'DEVOLUCION_COMPRA'     => ['general' => 'SALIDA',  'etiqueta' => 'Devolución a proveedor'],
            'DEVOLUCION_VENTA'      => ['general' => 'ENTRADA', 'etiqueta' => 'Devolución de cliente'],
        ];
    }

    private function enriquecerTipo(&$mov) {
        $mapa = self::mapaTipos();
        $nombreTipo = $mov['tipo_movimiento'] ?? '';

        if (isset($mapa[$nombreTipo])) {
            $mov['tipo_general'] = $mapa[$nombreTipo]['general'];
            $mov['tipo_etiqueta'] = $mapa[$nombreTipo]['etiqueta'];
        } else {
            // Tipo desconocido (por ejemplo, agregado a futuro sin actualizar
            // el mapa): fallback defensivo por comparación de stock, igual al
            // criterio que usaba el endpoint antes de este cambio.
            $mov['tipo_general'] = ((float) $mov['stock_nuevo'] >= (float) $mov['stock_anterior']) ? 'ENTRADA' : 'SALIDA';
            $mov['tipo_etiqueta'] = $mov['tipo_movimiento_descripcion'] ?: $nombreTipo;
        }
    }

    /*
     * Listado paginado y filtrable del Kárdex. Todos los filtros son
     * opcionales y se combinan con AND. Usa siempre consultas preparadas,
     * nunca concatena valores de usuario directamente en el SQL.
     */
    public function listar($filtros = []) {
        $condiciones = [];
        $params = [];

        if (!empty($filtros['id_producto'])) {
            $condiciones[] = "m.id_producto = :id_producto";
            $params[':id_producto'] = (int) $filtros['id_producto'];
        }

        if (!empty($filtros['id_tipo_movimiento'])) {
            $condiciones[] = "m.id_tipo_movimiento = :id_tipo_movimiento";
            $params[':id_tipo_movimiento'] = (int) $filtros['id_tipo_movimiento'];
        }

        if (!empty($filtros['id_usuario'])) {
            $condiciones[] = "m.id_usuario = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }

        if (!empty($filtros['fecha'])) {
            $condiciones[] = "DATE(m.fecha) = :fecha";
            $params[':fecha'] = $filtros['fecha'];
        } else {
            if (!empty($filtros['desde'])) {
                $condiciones[] = "DATE(m.fecha) >= :desde";
                $params[':desde'] = $filtros['desde'];
            }
            if (!empty($filtros['hasta'])) {
                $condiciones[] = "DATE(m.fecha) <= :hasta";
                $params[':hasta'] = $filtros['hasta'];
            }
        }

        if (!empty($filtros['q'])) {
            $condiciones[] = "(p.nombre LIKE :q1 OR tm.nombre LIKE :q2 OR CONCAT(u.nombres, ' ', u.apellidos) LIKE :q3 OR m.observacion LIKE :q4)";
            $like = '%' . $filtros['q'] . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        $queryCount = "SELECT COUNT(*) AS total
                       FROM movimientos m
                       INNER JOIN productos p ON m.id_producto = p.id_producto
                       INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                       INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                       $where";

        $stmtCount = $this->conn->prepare($queryCount);
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $total = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Resumen para las tarjetas superiores: se calcula sobre el MISMO
        // conjunto filtrado (antes de paginar), no sobre el histórico global,
        // para que las tarjetas reflejen lo que el usuario está consultando.
        $queryResumenTipos = "SELECT tm.nombre AS tipo, COUNT(*) AS cantidad
                               FROM movimientos m
                               INNER JOIN productos p ON m.id_producto = p.id_producto
                               INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                               INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                               $where
                               GROUP BY tm.nombre";
        $stmtResumenTipos = $this->conn->prepare($queryResumenTipos);
        foreach ($params as $key => $value) {
            $stmtResumenTipos->bindValue($key, $value);
        }
        $stmtResumenTipos->execute();
        $conteoTipos = $stmtResumenTipos->fetchAll(PDO::FETCH_ASSOC);

        $mapa = self::mapaTipos();
        $resumen = ['total' => $total, 'entradas' => 0, 'salidas' => 0, 'ajustes' => 0, 'reservas' => 0];

        foreach ($conteoTipos as $fila) {
            $general = $mapa[$fila['tipo']]['general'] ?? null;
            $cantidad = (int) $fila['cantidad'];

            switch ($general) {
                case 'ENTRADA':
                    $resumen['entradas'] += $cantidad;
                    break;
                case 'SALIDA':
                    $resumen['salidas'] += $cantidad;
                    break;
                case 'AJUSTE':
                    $resumen['ajustes'] += $cantidad;
                    break;
                case 'RESERVA':
                    $resumen['reservas'] += $cantidad;
                    break;
            }
        }

        $condicionesHoy = $condiciones;
        $condicionesHoy[] = "DATE(m.fecha) = CURDATE()";
        $whereHoy = 'WHERE ' . implode(' AND ', $condicionesHoy);

        $queryHoy = "SELECT COUNT(*) AS total
                     FROM movimientos m
                     INNER JOIN productos p ON m.id_producto = p.id_producto
                     INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                     INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                     $whereHoy";
        $stmtHoy = $this->conn->prepare($queryHoy);
        foreach ($params as $key => $value) {
            $stmtHoy->bindValue($key, $value);
        }
        $stmtHoy->execute();
        $resumen['hoy'] = (int) $stmtHoy->fetch(PDO::FETCH_ASSOC)['total'];

        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = (int) ($filtros['per_page'] ?? 20);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 20;
        }
        $offset = ($page - 1) * $perPage;

        $query = "SELECT
                    m.id_movimiento,
                    m.fecha,
                    m.id_producto,
                    p.nombre AS producto,
                    p.codigo_interno,
                    p.codigo_barras,
                    m.id_tipo_movimiento,
                    tm.nombre AS tipo_movimiento,
                    tm.descripcion AS tipo_movimiento_descripcion,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    m.id_compra,
                    m.id_venta,
                    m.id_reserva,
                    m.id_usuario,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario,
                    m.observacion
                  FROM movimientos m
                  INNER JOIN productos p ON m.id_producto = p.id_producto
                  INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                  INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                  $where
                  ORDER BY m.fecha DESC, m.id_movimiento DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($movimientos as &$mov) {
            $this->enriquecerTipo($mov);
        }

        return [
            'items' => $movimientos,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_paginas' => max(1, (int) ceil($total / $perPage)),
            'resumen' => $resumen
        ];
    }

    /*
     * Detalle completo de un movimiento: producto (con códigos), tipo,
     * trazabilidad de stock, usuario responsable con su rol, y el origen
     * real (compra/venta/reserva) cuando exista.
     */
    public function detalle($id) {
        $query = "SELECT
                    m.id_movimiento,
                    m.fecha,
                    m.id_producto,
                    p.nombre AS producto,
                    p.codigo_interno,
                    p.codigo_barras,
                    m.id_tipo_movimiento,
                    tm.nombre AS tipo_movimiento,
                    tm.descripcion AS tipo_movimiento_descripcion,
                    m.cantidad,
                    m.stock_anterior,
                    m.stock_nuevo,
                    m.id_compra,
                    co.numero_factura AS compra_numero_factura,
                    co.fecha AS compra_fecha,
                    m.id_venta,
                    ve.fecha AS venta_fecha,
                    ve.total AS venta_total,
                    m.id_reserva,
                    re.fecha_reserva AS reserva_fecha,
                    re.fecha_expiracion AS reserva_fecha_expiracion,
                    m.id_usuario,
                    u.nombres AS usuario_nombres,
                    u.apellidos AS usuario_apellidos,
                    rol.nombre AS usuario_rol,
                    m.observacion
                  FROM movimientos m
                  INNER JOIN productos p ON m.id_producto = p.id_producto
                  INNER JOIN tipos_movimiento tm ON m.id_tipo_movimiento = tm.id_tipo_movimiento
                  INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                  INNER JOIN roles rol ON u.id_rol = rol.id_rol
                  LEFT JOIN compras co ON m.id_compra = co.id_compra
                  LEFT JOIN ventas ve ON m.id_venta = ve.id_venta
                  LEFT JOIN reservas re ON m.id_reserva = re.id_reserva
                  WHERE m.id_movimiento = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mov) {
            return null;
        }

        $mov['usuario'] = trim($mov['usuario_nombres'] . ' ' . $mov['usuario_apellidos']);
        $this->enriquecerTipo($mov);

        return $mov;
    }

    /*
     * Datos auxiliares para poblar los selects de filtro del frontend:
     * los tipos de movimiento reales (con su badge/etiqueta ya resueltos)
     * y los usuarios que efectivamente tienen movimientos registrados.
     */
    public function filtrosDisponibles() {
        $stmtTipos = $this->conn->prepare(
            "SELECT id_tipo_movimiento, nombre, descripcion
             FROM tipos_movimiento
             WHERE estado = 1
             ORDER BY id_tipo_movimiento ASC"
        );
        $stmtTipos->execute();
        $tipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

        $mapa = self::mapaTipos();
        foreach ($tipos as &$tipo) {
            $info = $mapa[$tipo['nombre']] ?? null;
            $tipo['tipo_general'] = $info['general'] ?? null;
            $tipo['etiqueta'] = $info['etiqueta'] ?? $tipo['descripcion'];
        }

        $stmtUsuarios = $this->conn->prepare(
            "SELECT DISTINCT u.id_usuario, CONCAT(u.nombres, ' ', u.apellidos) AS nombre
             FROM movimientos m
             INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
             ORDER BY nombre ASC"
        );
        $stmtUsuarios->execute();
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

        return [
            'tipos' => $tipos,
            'usuarios' => $usuarios
        ];
    }
}
