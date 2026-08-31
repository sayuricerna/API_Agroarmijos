<?php
require_once __DIR__ . '/../helpers/Auditor.php';

class Catalogo {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /*
     * $estadoFiltro controla qué registros trae:
     *   'activo'   (default, mismo comportamiento de siempre) -> WHERE estado = 1
     *   'inactivo' -> WHERE estado = 0
     *   'todos'    -> sin filtro de estado
     * El default se mantiene en 'activo' a propósito: cualquier llamada
     * existente que no mande este parámetro sigue viendo exactamente lo
     * mismo que antes (selects de formularios, filtros, etc.), no se
     * rompe nada. La validación del valor recibido ya se hace en el
     * controller/ruta, pero se revalida aquí también por seguridad ya
     * que se concatena directo en el SQL.
     */
    public function listarTodo($tabla, $campo_orden, $estadoFiltro = 'activo') {
        $where = '';

        if ($estadoFiltro === 'activo') {
            $where = ' WHERE estado = 1';
        } elseif ($estadoFiltro === 'inactivo') {
            $where = ' WHERE estado = 0';
        } elseif ($estadoFiltro !== 'todos') {
            // Cualquier valor no reconocido cae al comportamiento de siempre.
            $where = ' WHERE estado = 1';
        }

        $query = "SELECT * FROM " . $tabla . $where . " ORDER BY " . $campo_orden . " ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertar($tabla, $datos, $idUsuario) {
        $columnas = implode(', ', array_keys($datos));
        $valores = ':' . implode(', :', array_keys($datos));

        $query = "INSERT INTO " . $tabla . " ($columnas) VALUES ($valores)";
        $stmt = $this->conn->prepare($query);

        foreach ($datos as $key => &$val) {
            $stmt->bindParam(":$key", $val);
        }

        $ok = $stmt->execute();

        if ($ok) {
            $idNuevo = (int) $this->conn->lastInsertId();
            $nombreRegistro = $datos['nombre'] ?? $datos['razon_social'] ?? $datos['bodega'] ?? ('#' . $idNuevo);
            Auditor::registrarSeguro(
                $this->conn, $idUsuario, ucfirst($tabla), $tabla, $idNuevo, 'CREAR',
                "Creó el registro \"$nombreRegistro\" en $tabla."
            );
        }

        return $ok;
    }

    public function actualizar($tabla, $pk, $id, $datos, $idUsuario) {
    $queryAntes = "SELECT * FROM " . $tabla . " WHERE " . $pk . " = :id";
    $stmtAntes = $this->conn->prepare($queryAntes);
    $stmtAntes->bindParam(":id", $id);
    $stmtAntes->execute();
    $antes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: [];

    $campos = [];

    foreach ($datos as $key => $value) {
        $campos[] = "$key = :$key";
    }

    $set = implode(', ', $campos);

    $query = "UPDATE " . $tabla . " SET $set WHERE " . $pk . " = :id";

    $stmt = $this->conn->prepare($query);

    foreach ($datos as $key => &$val) {
        $stmt->bindParam(":$key", $val);
    }

    $stmt->bindParam(":id", $id);

    $ok = $stmt->execute();

    if ($ok) {
        [$antesFiltrado, $despuesFiltrado] = Auditor::diferencias($antes, $datos);
        $nombreRegistro = $datos['nombre'] ?? $datos['razon_social'] ?? $datos['bodega'] ?? ('#' . $id);
        Auditor::registrarSeguro(
            $this->conn, $idUsuario, ucfirst($tabla), $tabla, (int) $id, 'EDITAR',
            "Editó el registro \"$nombreRegistro\" en $tabla.", $antesFiltrado, $despuesFiltrado
        );
    }

    return $ok;
}
    public function eliminarLogico($tabla, $pk, $id, $idUsuario) {
        return $this->cambiarEstado($tabla, $pk, $id, 0, $idUsuario);
    }

    // Contraparte de eliminarLogico(): reactiva un registro dado de baja
    // (estado 0 -> 1). No revierte ni recalcula nada más: el registro
    // solo vuelve a aparecer en los listados/selects "activo", igual que
    // cualquier otro activo.
    public function reactivarLogico($tabla, $pk, $id, $idUsuario) {
        return $this->cambiarEstado($tabla, $pk, $id, 1, $idUsuario);
    }

    private function cambiarEstado($tabla, $pk, $id, $estado, $idUsuario) {
        $estado = $estado ? 1 : 0;
        $query = "UPDATE " . $tabla . " SET estado = :estado WHERE " . $pk . " = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":estado", $estado, PDO::PARAM_INT);
        $stmt->bindParam(":id", $id);
        $ok = $stmt->execute();

        if ($ok) {
            Auditor::registrarSeguro(
                $this->conn, $idUsuario, ucfirst($tabla), $tabla, (int) $id, $estado ? 'ACTIVAR' : 'DESACTIVAR',
                "Cambió el estado del registro #$id en $tabla a " . ($estado ? 'activo' : 'inactivo') . "."
            );
        }

        return $ok;
    }
    public function existeDocumentoCliente($documento, $id = null) {
    $query = "SELECT id_cliente FROM clientes WHERE numero_documento = :documento";

    if ($id) {
        $query .= " AND id_cliente != :id";
    }

    $stmt = $this->conn->prepare($query);
    $stmt->bindParam(":documento", $documento);

    if ($id) {
        $stmt->bindParam(":id", $id);
    }

    $stmt->execute();

    return $stmt->rowCount() > 0;
}
}