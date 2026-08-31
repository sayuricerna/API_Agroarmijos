<?php
require_once __DIR__ . '/../helpers/Auditor.php';

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

    public function crear($data, $idActor) {
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

        $ok = $stmt->execute();

        if ($ok) {
            $idNuevo = (int) $this->conn->lastInsertId();
            Auditor::registrarSeguro(
                $this->conn, $idActor, 'Usuarios', 'usuarios', $idNuevo, 'CREAR',
                "Creó el usuario \"{$data['usuario']}\"."
            );
        }

        return $ok;
    }

    public function actualizar($id, $data, $idActor) {
        $queryAntes = "SELECT * FROM usuarios WHERE id_usuario = :id_usuario";
        $stmtAntes = $this->conn->prepare($queryAntes);
        $stmtAntes->bindParam(":id_usuario", $id);
        $stmtAntes->execute();
        $antes = $stmtAntes->fetch(PDO::FETCH_ASSOC) ?: [];

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

        $ok = $stmt->execute();

        if ($ok) {
            $despues = [
                'id_rol' => $data['id_rol'],
                'cedula' => $data['cedula'],
                'nombres' => $data['nombres'],
                'apellidos' => $data['apellidos'],
                'telefono' => $data['telefono'],
                'correo' => $data['correo'],
                'usuario' => $data['usuario'],
                'foto' => $data['foto'],
            ];
            [$antesFiltrado, $despuesFiltrado] = Auditor::diferencias($antes, $despues);
            Auditor::registrarSeguro(
                $this->conn, $idActor, 'Usuarios', 'usuarios', (int) $id, 'EDITAR',
                "Editó el usuario \"{$data['usuario']}\".", $antesFiltrado, $despuesFiltrado
            );
        }

        return $ok;
    }

    public function cambiarEstado($id, $estado, $idActor) {
        $query = "UPDATE usuarios SET estado = :estado WHERE id_usuario = :id_usuario";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":estado", $estado);
        $stmt->bindParam(":id_usuario", $id);

        $ok = $stmt->execute();

        if ($ok) {
            Auditor::registrarSeguro(
                $this->conn, $idActor, 'Usuarios', 'usuarios', (int) $id, $estado ? 'ACTIVAR' : 'DESACTIVAR',
                "Cambió el estado del usuario #$id a " . ($estado ? 'activo' : 'inactivo') . "."
            );
        }

        return $ok;
    }

    public function cambiarPassword($id, $password, $idActor) {
        $query = "UPDATE usuarios SET password = :password WHERE id_usuario = :id_usuario";

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password", $hash);
        $stmt->bindParam(":id_usuario", $id);

        $ok = $stmt->execute();

        if ($ok) {
            Auditor::registrarSeguro(
                $this->conn, $idActor, 'Usuarios', 'usuarios', (int) $id, 'EDITAR',
                "Cambió la contraseña del usuario #$id."
            );
        }

        return $ok;
    }
}