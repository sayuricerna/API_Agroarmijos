<?php
class Database {
    private $host = "localhost";
    private $db_name = "db_agroarmijos";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $exception) {
            header("Content-Type: application/json");
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "Error de conexión a la base de datos: " . $exception->getMessage()
            ]);
            exit;
        }
        return $this->conn;
    }
}