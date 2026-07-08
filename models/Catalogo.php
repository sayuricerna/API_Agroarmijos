<?php
class Catalogo {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listarTodo($tabla, $campo_orden) {
        $query = "SELECT * FROM " . $tabla . " WHERE estado = 1 ORDER BY " . $campo_orden . " ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertar($tabla, $datos) {
        $columnas = implode(', ', array_keys($datos));
        $valores = ':' . implode(', :', array_keys($datos));
        
        $query = "INSERT INTO " . $tabla . " ($columnas) VALUES ($valores)";
        $stmt = $this->conn->prepare($query);
        
        foreach ($datos as $key => &$val) {
            $stmt->bindParam(":$key", $val);
        }
        
        return $stmt->execute();
    }

    public function actualizar($tabla, $pk, $id, $datos) {
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

    return $stmt->execute();
}
    public function eliminarLogico($tabla, $pk, $id) {
        $query = "UPDATE " . $tabla . " SET estado = 0 WHERE " . $pk . " = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
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