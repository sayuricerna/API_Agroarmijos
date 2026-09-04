<?php

/*
 * Auditoría: trazabilidad de ACCIONES de usuario dentro del sistema
 * (quién hizo qué, en qué módulo, sobre qué registro, cuándo, y qué
 * cambió). No confundir con Movimiento (Kárdex de inventario, que
 * responde "qué pasó con el stock") — son conceptos distintos y usan
 * tablas distintas (`auditoria` vs `movimientos`).
 */
class Auditoria {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function construirFiltros($filtros) {
        $condiciones = [];
        $params = [];

        if (!empty($filtros['id_usuario'])) {
            $condiciones[] = "a.id_usuario = :id_usuario";
            $params[':id_usuario'] = (int) $filtros['id_usuario'];
        }

        if (!empty($filtros['accion'])) {
            $condiciones[] = "a.accion = :accion";
            $params[':accion'] = $filtros['accion'];
        }

        if (!empty($filtros['modulo'])) {
            $condiciones[] = "a.modulo = :modulo";
            $params[':modulo'] = $filtros['modulo'];
        }

        if (!empty($filtros['fecha'])) {
            $condiciones[] = "DATE(a.fecha) = :fecha";
            $params[':fecha'] = $filtros['fecha'];
        } else {
            if (!empty($filtros['desde'])) {
                $condiciones[] = "DATE(a.fecha) >= :desde";
                $params[':desde'] = $filtros['desde'];
            }
            if (!empty($filtros['hasta'])) {
                $condiciones[] = "DATE(a.fecha) <= :hasta";
                $params[':hasta'] = $filtros['hasta'];
            }
        }

        if (!empty($filtros['buscar'])) {
            $condiciones[] = "(a.descripcion LIKE :b1 OR a.modulo LIKE :b2 OR a.tabla LIKE :b3 OR CONCAT(u.nombres, ' ', u.apellidos) LIKE :b4 OR u.usuario LIKE :b5)";
            $like = '%' . $filtros['buscar'] . '%';
            $params[':b1'] = $like;
            $params[':b2'] = $like;
            $params[':b3'] = $like;
            $params[':b4'] = $like;
            $params[':b5'] = $like;
        }

        $where = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

        return [$where, $params];
    }

    public function listar($filtros = []) {
        [$where, $params] = $this->construirFiltros($filtros);

        $baseFrom = "FROM auditoria a
                     INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                     INNER JOIN roles r ON u.id_rol = r.id_rol
                     $where";

        $stmtCount = $this->conn->prepare("SELECT COUNT(*) AS total $baseFrom");
        foreach ($params as $key => $value) {
            $stmtCount->bindValue($key, $value);
        }
        $stmtCount->execute();
        $total = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

        // Resumen calculado sobre el MISMO conjunto filtrado (antes de
        // paginar), igual que en Movimientos: las tarjetas reflejan lo
        // que el usuario está consultando, no el histórico completo.
        $condicionesHoy = $where ? $where . " AND DATE(a.fecha) = CURDATE()" : "WHERE DATE(a.fecha) = CURDATE()";
        $stmtHoy = $this->conn->prepare("SELECT COUNT(*) AS total FROM auditoria a
                                          INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                                          INNER JOIN roles r ON u.id_rol = r.id_rol
                                          $condicionesHoy");
        foreach ($params as $key => $value) {
            $stmtHoy->bindValue($key, $value);
        }
        $stmtHoy->execute();
        $hoy = (int) $stmtHoy->fetch(PDO::FETCH_ASSOC)['total'];

        $stmtUsuarios = $this->conn->prepare("SELECT COUNT(DISTINCT a.id_usuario) AS total $baseFrom");
        foreach ($params as $key => $value) {
            $stmtUsuarios->bindValue($key, $value);
        }
        $stmtUsuarios->execute();
        $usuariosActivos = (int) $stmtUsuarios->fetch(PDO::FETCH_ASSOC)['total'];

        /*
         * FIX (GCS): "Autenticación" no es un módulo de negocio (Productos,
         * Compras, Ventas...), es el registro de inicios de sesión — y como
         * cada inicio de sesión genera una fila, casi siempre termina
         * "ganando" este resumen sin decir nada útil sobre qué módulo se
         * usa más. Se excluye solo de este cálculo puntual (sigue
         * apareciendo normal en el listado y en el filtro de módulos).
         */
        $condicionesModulo = $where ? $where . " AND a.modulo != 'Autenticación'" : "WHERE a.modulo != 'Autenticación'";
        $stmtModulo = $this->conn->prepare("SELECT a.modulo, COUNT(*) AS cantidad
                                             FROM auditoria a
                                             INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                                             INNER JOIN roles r ON u.id_rol = r.id_rol
                                             $condicionesModulo
                                             GROUP BY a.modulo ORDER BY cantidad DESC LIMIT 1");
        foreach ($params as $key => $value) {
            $stmtModulo->bindValue($key, $value);
        }
        $stmtModulo->execute();
        $filaModulo = $stmtModulo->fetch(PDO::FETCH_ASSOC);
        $moduloTop = $filaModulo ? $filaModulo['modulo'] : null;

        $resumen = [
            'total' => $total,
            'hoy' => $hoy,
            'usuarios_activos' => $usuariosActivos,
            'modulo_top' => $moduloTop
        ];

        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = (int) ($filtros['per_page'] ?? 20);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 20;
        }
        $offset = ($page - 1) * $perPage;

        $query = "SELECT
                    a.id_auditoria,
                    a.id_usuario,
                    a.modulo,
                    a.tabla,
                    a.registro_id,
                    a.accion,
                    a.descripcion,
                    a.fecha,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario,
                    r.nombre AS usuario_rol
                  $baseFrom
                  ORDER BY a.fecha DESC, a.id_auditoria DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_paginas' => max(1, (int) ceil($total / $perPage)),
            'resumen' => $resumen
        ];
    }

    public function detalle($id) {
        $query = "SELECT
                    a.id_auditoria,
                    a.id_usuario,
                    a.modulo,
                    a.tabla,
                    a.registro_id,
                    a.accion,
                    a.descripcion,
                    a.datos_anteriores,
                    a.datos_nuevos,
                    a.ip,
                    a.navegador,
                    a.fecha,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario,
                    u.usuario AS usuario_login,
                    r.nombre AS usuario_rol
                  FROM auditoria a
                  INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                  INNER JOIN roles r ON u.id_rol = r.id_rol
                  WHERE a.id_auditoria = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        return $registro ?: null;
    }

    /*
     * Datos auxiliares para poblar los selects de filtro del frontend:
     * usuarios que efectivamente tienen registros de auditoría, módulos
     * usados, y el catálogo fijo de acciones posibles (coincide con el
     * ENUM de la columna `accion`).
     */
    public function filtrosDisponibles() {
        $stmtUsuarios = $this->conn->prepare(
            "SELECT DISTINCT u.id_usuario, CONCAT(u.nombres, ' ', u.apellidos) AS nombre
             FROM auditoria a
             INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
             ORDER BY nombre ASC"
        );
        $stmtUsuarios->execute();
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

        $stmtModulos = $this->conn->prepare(
            "SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC"
        );
        $stmtModulos->execute();
        $modulos = array_column($stmtModulos->fetchAll(PDO::FETCH_ASSOC), 'modulo');

        $acciones = [
            'CREAR', 'EDITAR', 'ACTIVAR', 'DESACTIVAR', 'REGISTRAR',
            'ANULAR', 'AJUSTAR', 'CONFIRMAR', 'CANCELAR',
            'INICIAR_SESION', 'CERRAR_SESION'
        ];

        return [
            'usuarios' => $usuarios,
            'modulos' => $modulos,
            'acciones' => $acciones
        ];
    }
}
