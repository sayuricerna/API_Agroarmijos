<?php

/*
 * Auditor centraliza la escritura en la tabla `auditoria` (ya existía en
 * la base de datos, pero ningún controller/model la usaba todavía — ver
 * migración db_auditoria_ampliacion.sql para las columnas nuevas).
 *
 * Dos formas de registrar, a propósito:
 *
 *   - registrar()       -> lanza la excepción si el INSERT falla. Se usa
 *                           DENTRO de una transacción ya abierta (compras,
 *                           ventas, reservas) para que, si la auditoría
 *                           falla, toda la operación haga rollback junto
 *                           con ella (nunca "se vendió pero no se auditó").
 *
 *   - registrarSeguro() -> atrapa cualquier error y solo lo deja en el log
 *                          de PHP. Se usa para operaciones que NO están en
 *                          una transacción propia (crear/editar producto,
 *                          usuario, cliente, login, etc.): la auditoría es
 *                          secundaria a esa operación, así que un fallo al
 *                          auditar nunca debe tumbar una acción que de otro
 *                          modo funcionó bien.
 */
class Auditor {

    public static function registrar(
        $db,
        $idUsuario,
        $modulo,
        $tabla,
        $idRegistro,
        $accion,
        $descripcion,
        $antes = null,
        $despues = null
    ) {
        $query = "INSERT INTO auditoria
                    (id_usuario, modulo, tabla, registro_id, accion, descripcion, datos_anteriores, datos_nuevos, ip, navegador)
                  VALUES
                    (:id_usuario, :modulo, :tabla, :registro_id, :accion, :descripcion, :antes, :despues, :ip, :navegador)";

        $stmt = $db->prepare($query);

        $stmt->bindValue(':id_usuario', (int) $idUsuario, PDO::PARAM_INT);
        $stmt->bindValue(':modulo', $modulo);
        $stmt->bindValue(':tabla', $tabla);
        $stmt->bindValue(':registro_id', (int) $idRegistro, PDO::PARAM_INT);
        $stmt->bindValue(':accion', $accion);
        $stmt->bindValue(':descripcion', $descripcion);
        $stmt->bindValue(':antes', $antes !== null && $antes !== [] ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null);
        $stmt->bindValue(':despues', $despues !== null && $despues !== [] ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null);
        $stmt->bindValue(':ip', self::obtenerIp());
        $stmt->bindValue(':navegador', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255));

        $stmt->execute();
    }

    public static function registrarSeguro(
        $db,
        $idUsuario,
        $modulo,
        $tabla,
        $idRegistro,
        $accion,
        $descripcion,
        $antes = null,
        $despues = null
    ) {
        try {
            self::registrar($db, $idUsuario, $modulo, $tabla, $idRegistro, $accion, $descripcion, $antes, $despues);
        } catch (Throwable $e) {
            error_log("[Auditor::registrarSeguro] " . $e->getMessage());
        }
    }

    /*
     * Compara dos arreglos asociativos (mismo registro antes/después) y
     * devuelve solo los campos que realmente cambiaron, para no guardar
     * una copia completa del registro cuando solo se editó un campo.
     * $ignorar excluye columnas que nunca aportan valor a la auditoría
     * (o que son sensibles, como password).
     */
    public static function diferencias(array $antes, array $despues, array $ignorar = ['password', 'created_at', 'updated_at', 'version']) {
        $antesFiltrado = [];
        $despuesFiltrado = [];

        foreach ($despues as $campo => $valorNuevo) {
            if (in_array($campo, $ignorar, true)) {
                continue;
            }

            if (!array_key_exists($campo, $antes)) {
                continue;
            }

            $valorAnterior = $antes[$campo];

            if ((string) $valorAnterior !== (string) $valorNuevo) {
                $antesFiltrado[$campo] = $valorAnterior;
                $despuesFiltrado[$campo] = $valorNuevo;
            }
        }

        return [$antesFiltrado, $despuesFiltrado];
    }

    private static function obtenerIp() {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
