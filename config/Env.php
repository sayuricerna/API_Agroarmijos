<?php
/**
 * Cargador minimalista de variables de entorno (.env).
 * No depende de paquetes externos de Composer: basta con incluir
 * este archivo y llamar a Env::load() una sola vez al inicio de la
 * aplicación (ver index.php).
 */
class Env {
    private static $loaded = false;

    public static function load($path) {
        if (self::$loaded || !file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignora comentarios y líneas vacías o mal formadas.
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim(trim($value), "\"'");

            if (!array_key_exists($name, $_ENV)) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
