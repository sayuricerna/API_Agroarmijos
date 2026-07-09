<?php

class Rol {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listar() {
        $query = "SELECT id_rol, nombre, descripcion, estado
                  FROM roles
                  ORDER BY id_rol ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}