<?php
class Usuario {
    private $conn;
    private $table_name = "usuarios";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username) {
        $query = "SELECT u.*, r.nombre AS rol_nombre 
                  FROM " . $this->table_name . " u
                  INNER JOIN roles r ON u.id_rol = r.id_rol
                  WHERE u.usuario = :usuario OR u.correo = :correo 
                  LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario", $username);
        $stmt->bindParam(":correo", $username);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateLastLogin($id_usuario) {
        $query = "UPDATE " . $this->table_name . " SET ultimo_login = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id_usuario);
        return $stmt->execute();
    }
}