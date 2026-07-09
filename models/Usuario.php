<?php

class Usuario {
    private $conn;
    private $table_name = "usuarios";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username) {
        $query = "SELECT u.*, r.nombre AS rol_nombre 
                  FROM usuarios u
                  INNER JOIN roles r ON u.id_rol = r.id_rol
                  WHERE u.usuario = :usuario OR u.correo = :correo 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":usuario", $username);
        $stmt->bindParam(":correo", $username);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateLastLogin($id_usuario) {
        $query = "UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id_usuario);
        return $stmt->execute();
    }

    public function listar() {
        $query = "SELECT 
                    u.id_usuario,
                    u.id_rol,
                    r.nombre AS rol,
                    u.cedula,
                    u.nombres,
                    u.apellidos,
                    u.telefono,
                    u.correo,
                    u.usuario,
                    u.foto,
                    u.estado,
                    u.ultimo_login
                  FROM usuarios u
                  INNER JOIN roles r ON u.id_rol = r.id_rol
                  ORDER BY u.nombres ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($data) {
        $query = "INSERT INTO usuarios
                  (id_rol, cedula, nombres, apellidos, telefono, correo, usuario, password, foto, estado)
                  VALUES
                  (:id_rol, :cedula, :nombres, :apellidos, :telefono, :correo, :usuario, :password, :foto, 1)";

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_rol", $data['id_rol']);
        $stmt->bindParam(":cedula", $data['cedula']);
        $stmt->bindParam(":nombres", $data['nombres']);
        $stmt->bindParam(":apellidos", $data['apellidos']);
        $stmt->bindParam(":telefono", $data['telefono']);
        $stmt->bindParam(":correo", $data['correo']);
        $stmt->bindParam(":usuario", $data['usuario']);
        $stmt->bindParam(":password", $hash);
        $stmt->bindParam(":foto", $data['foto']);

        return $stmt->execute();
    }

    public function actualizar($id, $data) {
        $query = "UPDATE usuarios SET
                    id_rol = :id_rol,
                    cedula = :cedula,
                    nombres = :nombres,
                    apellidos = :apellidos,
                    telefono = :telefono,
                    correo = :correo,
                    usuario = :usuario,
                    foto = :foto
                  WHERE id_usuario = :id_usuario";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_rol", $data['id_rol']);
        $stmt->bindParam(":cedula", $data['cedula']);
        $stmt->bindParam(":nombres", $data['nombres']);
        $stmt->bindParam(":apellidos", $data['apellidos']);
        $stmt->bindParam(":telefono", $data['telefono']);
        $stmt->bindParam(":correo", $data['correo']);
        $stmt->bindParam(":usuario", $data['usuario']);
        $stmt->bindParam(":foto", $data['foto']);
        $stmt->bindParam(":id_usuario", $id);

        return $stmt->execute();
    }

    public function cambiarEstado($id, $estado) {
        $query = "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":estado", $estado);
        $stmt->bindParam(":id_usuario", $id);

        return $stmt->execute();
    }

    public function cambiarPassword($id, $password) {
        $query = "UPDATE usuarios SET password = :password WHERE id_usuario = :id_usuario";

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password", $hash);
        $stmt->bindParam(":id_usuario", $id);

        return $stmt->execute();
    }
}