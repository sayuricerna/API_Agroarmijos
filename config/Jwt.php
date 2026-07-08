<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHandler {
    private $secret_key = "AgroArmijos_Secret_Key_2026_@PI_UNIANDES";
    private $algorithm = "HS256";

    public function generateToken($user_id, $username, $role_name) {
        $issued_at = time();
        $expiration_time = $issued_at + (60 * 60 * 12); // token 12 horas

        $payload = [
            "iss" => "localhost",
            "iat" => $issued_at,
            "exp" => $expiration_time,
            "data" => [
                "id_usuario" => $user_id,
                "usuario"    => $username,
                "rol"        => $role_name
            ]
        ];

        return JWT::encode($payload, $this->secret_key, $this->algorithm);
    }

    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key($this->secret_key, $this->algorithm));
            return (array) $decoded->data;
        } catch (Exception $e) {
            return null; 
        }
    }
}