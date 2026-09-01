<?php
require_once __DIR__ . '/../helpers/Auditor.php';

class Reserva {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function listar(): array {
        // No hay un cron disponible, así que la expiración se dispara de
        // forma perezosa cada vez que se listan las reservas (también al
        // crear una reserva nueva o una venta).
        $this->expirarVencidas();

        $query = "SELECT
                    r.id_reserva,
                    r.id_cliente,
                    r.id_usuario,
                    r.fecha_reserva,
                    r.fecha_expiracion,
                    r.estado,
                    r.id_venta,
                    r.observacion,
                    c.nombres AS cliente,
                    CONCAT(u.nombres, ' ', u.apellidos) AS usuario
                  FROM reservas r
                  INNER JOIN clientes c
                    ON r.id_cliente = c.id_cliente
                  INNER JOIN usuarios u
                    ON r.id_usuario = u.id_usuario
                  ORDER BY r.fecha_reserva DESC, r.id_reserva DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservas as &$reserva) {
            $reserva['productos'] = $this->obtenerDetalles(
                (int) $reserva['id_reserva']
            );
        }

        return $reservas;
    }

    public function obtenerDetalles(int $idReserva): array {
        $query = "SELECT
                    dr.id_detalle_reserva,
                    dr.id_reserva,
                    dr.id_producto,
                    dr.cantidad,
                    p.nombre AS producto,
                    p.codigo_interno,
                    p.codigo_barras,
                    p.precio_venta
                  FROM detalle_reservas dr
                  INNER JOIN productos p
                    ON dr.id_producto = p.id_producto
                  WHERE dr.id_reserva = :id_reserva
                  ORDER BY dr.id_detalle_reserva ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear(array $data, int $idUsuario): int {
        // Libera primero el stock de reservas ya vencidas, antes de validar
        // disponibilidad para esta reserva nueva.
        $this->expirarVencidas();

        try {
            $this->conn->beginTransaction();

            $queryReserva = "INSERT INTO reservas (
                                id_cliente,
                                id_usuario,
                                fecha_reserva,
                                fecha_expiracion,
                                estado,
                                observacion
                             ) VALUES (
                                :id_cliente,
                                :id_usuario,
                                NOW(),
                                :fecha_expiracion,
                                'PENDIENTE',
                                :observacion
                             )";

            $stmtReserva = $this->conn->prepare($queryReserva);
            $stmtReserva->bindValue(
                ':id_cliente',
                (int) $data['id_cliente'],
                PDO::PARAM_INT
            );
            $stmtReserva->bindValue(
                ':id_usuario',
                $idUsuario,
                PDO::PARAM_INT
            );
            $stmtReserva->bindValue(
                ':fecha_expiracion',
                $data['fecha_expiracion']
            );
            $stmtReserva->bindValue(
                ':observacion',
                $data['observacion'] ?? ''
            );
            $stmtReserva->execute();

            $idReserva = (int) $this->conn->lastInsertId();

            foreach ($data['productos'] as $producto) {
                $idProducto = (int) $producto['id_producto'];
                $cantidad = (float) $producto['cantidad'];

                if ($cantidad <= 0) {
                    throw new Exception(
                        "La cantidad reservada debe ser mayor a cero."
                    );
                }

                $queryProducto = "SELECT
                                    nombre,
                                    stock_actual,
                                    stock_reservado,
                                    stock_disponible
                                  FROM productos
                                  WHERE id_producto = :id_producto
                                    AND estado = 1
                                  FOR UPDATE";

                $stmtProducto = $this->conn->prepare($queryProducto);
                $stmtProducto->bindValue(
                    ':id_producto',
                    $idProducto,
                    PDO::PARAM_INT
                );
                $stmtProducto->execute();

                $registroProducto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

                if (!$registroProducto) {
                    throw new Exception(
                        "El producto seleccionado no existe o está inactivo."
                    );
                }

                if (
                    (float) $registroProducto['stock_disponible'] < $cantidad
                ) {
                    throw new Exception(
                        "Stock disponible insuficiente para: " .
                        $registroProducto['nombre']
                    );
                }

                $queryDetalle = "INSERT INTO detalle_reservas (
                                    id_reserva,
                                    id_producto,
                                    cantidad
                                 ) VALUES (
                                    :id_reserva,
                                    :id_producto,
                                    :cantidad
                                 )";

                $stmtDetalle = $this->conn->prepare($queryDetalle);
                $stmtDetalle->bindValue(
                    ':id_reserva',
                    $idReserva,
                    PDO::PARAM_INT
                );
                $stmtDetalle->bindValue(
                    ':id_producto',
                    $idProducto,
                    PDO::PARAM_INT
                );
                $stmtDetalle->bindValue(':cantidad', $cantidad);
                $stmtDetalle->execute();

                $queryStock = "UPDATE productos
                               SET
                                 stock_reservado = stock_reservado + :cantidad,
                                 stock_disponible = stock_disponible - :cantidad,
                                 version = version + 1
                               WHERE id_producto = :id_producto";

                $stmtStock = $this->conn->prepare($queryStock);
                $stmtStock->bindValue(':cantidad', $cantidad);
                $stmtStock->bindValue(
                    ':id_producto',
                    $idProducto,
                    PDO::PARAM_INT
                );
                $stmtStock->execute();

                // Kárdex: registra la reserva (tipo 5 = RESERVA). No se toca
                // stock_actual (la mercadería sigue físicamente en bodega),
                // por eso aquí se usa stock_disponible como anterior/nuevo —
                // es la cantidad que deja de estar disponible para vender.
                $stockDisponibleAnterior = (float) $registroProducto['stock_disponible'];
                $stockDisponibleNuevo = $stockDisponibleAnterior - $cantidad;

                $queryKardex = "CALL sp_registrar_movimiento(:id_prod, 5, :id_user, NULL, NULL, :id_reserva, :cant, :ant, :nue, 'Reserva de producto')";
                $stmtKardex = $this->conn->prepare($queryKardex);
                $stmtKardex->bindValue(':id_prod', $idProducto, PDO::PARAM_INT);
                $stmtKardex->bindValue(':id_user', $idUsuario, PDO::PARAM_INT);
                $stmtKardex->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
                $stmtKardex->bindValue(':cant', $cantidad);
                $stmtKardex->bindValue(':ant', $stockDisponibleAnterior);
                $stmtKardex->bindValue(':nue', $stockDisponibleNuevo);
                $stmtKardex->execute();
            }

            // Auditoría (GCS — feature/auditoria-integracion): dentro de la
            // misma transacción, mismo criterio que en Venta::crearVenta().
            Auditor::registrar(
                $this->conn, $idUsuario, 'Reservas', 'reservas', $idReserva, 'REGISTRAR',
                "Registró la reserva #$idReserva."
            );

            $this->conn->commit();

            return $idReserva;

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function cancelar(int $idReserva, int $idUsuario): bool {
        try {
            $this->conn->beginTransaction();

            $queryReserva = "SELECT estado
                             FROM reservas
                             WHERE id_reserva = :id_reserva
                             FOR UPDATE";

            $stmtReserva = $this->conn->prepare($queryReserva);
            $stmtReserva->bindValue(
                ':id_reserva',
                $idReserva,
                PDO::PARAM_INT
            );
            $stmtReserva->execute();

            $reserva = $stmtReserva->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                throw new Exception("La reserva no existe.");
            }

            if ($reserva['estado'] !== 'PENDIENTE') {
                throw new Exception(
                    "Solo se pueden cancelar reservas pendientes."
                );
            }

            $detalles = $this->obtenerDetalles($idReserva);

            // La liberación línea por línea vive en liberarLineaReserva(),
            // compartida con expirarUna().
            foreach ($detalles as $detalle) {
                $this->liberarLineaReserva(
                    $idReserva,
                    (int) $detalle['id_producto'],
                    (float) $detalle['cantidad'],
                    $idUsuario,
                    'Liberación de reserva'
                );
            }

            $queryCancelar = "UPDATE reservas
                              SET estado = 'CANCELADA'
                              WHERE id_reserva = :id_reserva";

            $stmtCancelar = $this->conn->prepare($queryCancelar);
            $stmtCancelar->bindValue(
                ':id_reserva',
                $idReserva,
                PDO::PARAM_INT
            );
            $stmtCancelar->execute();

            // Auditoría (GCS — feature/auditoria-integracion): dentro de la
            // misma transacción, mismo criterio que en Venta::crearVenta().
            Auditor::registrar(
                $this->conn, $idUsuario, 'Reservas', 'reservas', $idReserva, 'CANCELAR',
                "Canceló la reserva #$idReserva."
            );

            $this->conn->commit();

            return true;

        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    public function confirmar(int $idReserva, int $idUsuario): bool {
        $query = "UPDATE reservas
                  SET estado = 'CONFIRMADA'
                  WHERE id_reserva = :id_reserva
                    AND estado = 'PENDIENTE'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(
            ':id_reserva',
            $idReserva,
            PDO::PARAM_INT
        );
        $stmt->execute();

        $ok = $stmt->rowCount() > 0;

        if ($ok) {
            Auditor::registrarSeguro(
                $this->conn, $idUsuario, 'Reservas', 'reservas', $idReserva, 'CONFIRMAR',
                "Confirmó la reserva #$idReserva."
            );
        }

        return $ok;
    }

    /*
     * Libera la porción de stock apartada por una línea de una reserva.
     * La usan cancelar() y expirarUna(); requiere una transacción ya
     * abierta por quien la llama.
     */
    private function liberarLineaReserva(
        int $idReserva,
        int $idProducto,
        float $cantidad,
        int $idUsuario,
        string $observacionKardex
    ): void {
        // Se lee el stock_disponible actual antes de liberarlo, para poder
        // dejar la trazabilidad anterior/nuevo en el Kárdex.
        $querySelectStock = "SELECT stock_disponible
                              FROM productos
                              WHERE id_producto = :id_producto
                              FOR UPDATE";
        $stmtSelectStock = $this->conn->prepare($querySelectStock);
        $stmtSelectStock->bindValue(
            ':id_producto',
            $idProducto,
            PDO::PARAM_INT
        );
        $stmtSelectStock->execute();
        $stockDisponibleAnterior = (float) (
            $stmtSelectStock->fetch(PDO::FETCH_ASSOC)['stock_disponible'] ?? 0
        );

        $queryStock = "UPDATE productos
                       SET
                         stock_reservado =
                           GREATEST(stock_reservado - :cantidad, 0),
                         stock_disponible =
                           stock_disponible + :cantidad,
                         version = version + 1
                       WHERE id_producto = :id_producto";

        $stmtStock = $this->conn->prepare($queryStock);
        $stmtStock->bindValue(':cantidad', $cantidad);
        $stmtStock->bindValue(
            ':id_producto',
            $idProducto,
            PDO::PARAM_INT
        );
        $stmtStock->execute();

        // Kárdex: tipo 6 (CANCELACION_RESERVA), reutilizado también para
        // expiraciones automáticas. Anterior/nuevo son stock_disponible,
        // no stock_actual, misma convención que al crear la reserva.
        $stockDisponibleNuevo = $stockDisponibleAnterior + $cantidad;

        $queryKardex = "CALL sp_registrar_movimiento(:id_prod, 6, :id_user, NULL, NULL, :id_reserva, :cant, :ant, :nue, :obs)";
        $stmtKardex = $this->conn->prepare($queryKardex);
        $stmtKardex->bindValue(':id_prod', $idProducto, PDO::PARAM_INT);
        $stmtKardex->bindValue(':id_user', $idUsuario, PDO::PARAM_INT);
        $stmtKardex->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
        $stmtKardex->bindValue(':cant', $cantidad);
        $stmtKardex->bindValue(':ant', $stockDisponibleAnterior);
        $stmtKardex->bindValue(':nue', $stockDisponibleNuevo);
        $stmtKardex->bindValue(':obs', $observacionKardex);
        $stmtKardex->execute();
    }

    /*
     * Recorre las reservas PENDIENTE vencidas y las mueve a EXPIRADA,
     * liberando el stock apartado. Se llama de forma perezosa desde
     * listar(), crear() y Venta::crearVenta(), ya que no hay un proceso
     * programado (cron) disponible. Devuelve cuántas reservas se expiraron.
     */
    public function expirarVencidas(?int $idUsuario = null): int {
        $queryVencidas = "SELECT id_reserva
                          FROM reservas
                          WHERE estado = 'PENDIENTE'
                            AND fecha_expiracion < NOW()";

        $stmtVencidas = $this->conn->query($queryVencidas);
        $idsVencidas = $stmtVencidas->fetchAll(PDO::FETCH_COLUMN);

        $totalExpiradas = 0;

        foreach ($idsVencidas as $idReserva) {
            try {
                if ($this->expirarUna((int) $idReserva, $idUsuario)) {
                    $totalExpiradas++;
                }
            } catch (Throwable $e) {
                // No se detiene el lote completo por un problema puntual en
                // una reserva (ej. un producto que ya no existe): se deja
                // constancia en el log del servidor y se sigue con las
                // demás. Esto se dispara de forma implícita dentro de
                // otras operaciones (listar, crear, vender), así que no
                // debe poder tumbar la operación principal del usuario.
                error_log(
                    "No se pudo expirar automáticamente la reserva #$idReserva: " . $e->getMessage()
                );
            }
        }

        return $totalExpiradas;
    }

    /*
     * Expira UNA reserva puntual, en su propia transacción. Vuelve a
     * comprobar estado + vencimiento con FOR UPDATE antes de tocar nada,
     * por si otra petición la confirmó/canceló justo entre el SELECT de
     * expirarVencidas() y este momento. Devuelve false (sin lanzar) si ya
     * no aplica, en vez de tratarlo como error.
     */
    private function expirarUna(int $idReserva, ?int $idUsuario): bool {
        try {
            $this->conn->beginTransaction();

            $queryReserva = "SELECT estado, id_usuario, fecha_expiracion
                             FROM reservas
                             WHERE id_reserva = :id_reserva
                             FOR UPDATE";

            $stmtReserva = $this->conn->prepare($queryReserva);
            $stmtReserva->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmtReserva->execute();

            $reserva = $stmtReserva->fetch(PDO::FETCH_ASSOC);

            if (
                !$reserva ||
                $reserva['estado'] !== 'PENDIENTE' ||
                strtotime($reserva['fecha_expiracion']) >= time()
            ) {
                // Ya no aplica: alguien la confirmó, canceló, o su
                // fecha_expiracion cambió mientras tanto.
                $this->conn->rollBack();
                return false;
            }

            $detalles = $this->obtenerDetalles($idReserva);

            foreach ($detalles as $detalle) {
                $this->liberarLineaReserva(
                    $idReserva,
                    (int) $detalle['id_producto'],
                    (float) $detalle['cantidad'],
                    $idUsuario ?? (int) $reserva['id_usuario'],
                    'Liberación por expiración automática de reserva'
                );
            }

            $queryExpirar = "UPDATE reservas
                             SET estado = 'EXPIRADA'
                             WHERE id_reserva = :id_reserva";

            $stmtExpirar = $this->conn->prepare($queryExpirar);
            $stmtExpirar->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmtExpirar->execute();

            // El ENUM de auditoria.accion no tiene un valor 'EXPIRAR', así
            // que se reutiliza 'CANCELAR' con una descripción que aclara
            // que fue automático. Se usa registrarSeguro() porque esto no
            // es una acción manual del usuario: no debe interrumpir la
            // operación (listar, crear reserva, vender) que la disparó.
            // Sin usuario autenticado en el contexto, se atribuye al
            // dueño original de la reserva.
            Auditor::registrarSeguro(
                $this->conn,
                $idUsuario ?? (int) $reserva['id_usuario'],
                'Reservas',
                'reservas',
                $idReserva,
                'CANCELAR',
                "Expiró automáticamente la reserva #$idReserva (venció el {$reserva['fecha_expiracion']}) y se liberó el stock reservado."
            );

            $this->conn->commit();

            return true;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    /*
     * Crea la venta real a partir de las líneas de una reserva CONFIRMADA
     * (al precio_venta actual del producto, no al de cuando se reservó) y
     * enlaza reservas.id_venta.
     *
     * No reutiliza Venta::crearVenta(): esa función descuenta contra
     * stock_disponible, pero aquí el stock de estas líneas ya estaba
     * apartado en stock_reservado desde la reserva. Corresponde mover la
     * cantidad de stock_actual/stock_reservado a "vendido", dejando
     * stock_disponible igual (ya estaba descontado).
     */
    public function convertirEnVenta(int $idReserva, int $idUsuario): int {
        try {
            $this->conn->beginTransaction();

            $queryReserva = "SELECT id_cliente, estado, id_venta
                             FROM reservas
                             WHERE id_reserva = :id_reserva
                             FOR UPDATE";

            $stmtReserva = $this->conn->prepare($queryReserva);
            $stmtReserva->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmtReserva->execute();

            $reserva = $stmtReserva->fetch(PDO::FETCH_ASSOC);

            if (!$reserva) {
                throw new Exception("La reserva no existe.");
            }

            if ($reserva['id_venta'] !== null) {
                throw new Exception("Esta reserva ya fue convertida en la venta #{$reserva['id_venta']}.");
            }

            if ($reserva['estado'] !== 'CONFIRMADA') {
                throw new Exception("Solo se pueden convertir en venta las reservas confirmadas.");
            }

            $detalles = $this->obtenerDetalles($idReserva);

            if (empty($detalles)) {
                throw new Exception("La reserva no tiene productos asociados.");
            }

            $lineas = [];
            $subtotalGeneral = 0.0;

            foreach ($detalles as $detalle) {
                $idProducto = (int) $detalle['id_producto'];
                $cantidad = (float) $detalle['cantidad'];

                // Precio ACTUAL del producto (puede haber cambiado desde
                // que se creó la reserva), mismo criterio que
                // Venta::crearVenta(): el precio nunca se toma de lo que
                // se guardó al reservar.
                $queryProducto = "SELECT stock_actual, stock_reservado, nombre, precio_venta
                                  FROM productos
                                  WHERE id_producto = :id_producto
                                  FOR UPDATE";
                $stmtProducto = $this->conn->prepare($queryProducto);
                $stmtProducto->bindValue(':id_producto', $idProducto, PDO::PARAM_INT);
                $stmtProducto->execute();
                $producto = $stmtProducto->fetch(PDO::FETCH_ASSOC);

                if (!$producto) {
                    throw new Exception("El producto ID $idProducto ya no existe.");
                }

                $precio = (float) $producto['precio_venta'];
                $subtotalLinea = $precio * $cantidad;
                $subtotalGeneral += $subtotalLinea;

                $stockActualAnterior = (float) $producto['stock_actual'];
                $stockReservadoAnterior = (float) $producto['stock_reservado'];
                $stockActualNuevo = $stockActualAnterior - $cantidad;
                $stockReservadoNuevo = max($stockReservadoAnterior - $cantidad, 0);

                $lineas[] = [
                    "id_producto"            => $idProducto,
                    "cantidad"               => $cantidad,
                    "precio_unitario"        => $precio,
                    "subtotal"               => $subtotalLinea,
                    "stock_actual_anterior"  => $stockActualAnterior,
                    "stock_actual_nuevo"     => $stockActualNuevo,
                    "stock_reservado_nuevo"  => $stockReservadoNuevo,
                ];
            }

            $queryVenta = "INSERT INTO ventas (id_cliente, id_usuario, fecha, subtotal, descuento, iva, total, observacion, estado)
                          VALUES (:id_cliente, :id_usuario, CURDATE(), :subtotal, 0, 0, :total, :observacion, 'PAGADA')";

            $stmtVenta = $this->conn->prepare($queryVenta);
            $stmtVenta->bindValue(':id_cliente', (int) $reserva['id_cliente'], PDO::PARAM_INT);
            $stmtVenta->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmtVenta->bindValue(':subtotal', $subtotalGeneral);
            $stmtVenta->bindValue(':total', $subtotalGeneral);
            $stmtVenta->bindValue(':observacion', "Generada a partir de la reserva #$idReserva.");
            $stmtVenta->execute();

            $idVenta = (int) $this->conn->lastInsertId();

            foreach ($lineas as $linea) {
                // stock_disponible NO se toca aquí: ya se había descontado
                // al crear la reserva (Reserva::crear()), y stock_actual y
                // stock_reservado bajan juntos en la misma cantidad, así
                // que la diferencia (disponible) no cambia.
                $qUpdateStock = "UPDATE productos
                                 SET
                                   stock_actual = :stock_actual_nuevo,
                                   stock_reservado = :stock_reservado_nuevo
                                 WHERE id_producto = :id_producto";
                $stUpdateStock = $this->conn->prepare($qUpdateStock);
                $stUpdateStock->bindValue(':stock_actual_nuevo', $linea['stock_actual_nuevo']);
                $stUpdateStock->bindValue(':stock_reservado_nuevo', $linea['stock_reservado_nuevo']);
                $stUpdateStock->bindValue(':id_producto', $linea['id_producto'], PDO::PARAM_INT);
                $stUpdateStock->execute();

                $qDetalleVenta = "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal)
                                  VALUES (:id_venta, :id_producto, :cantidad, :precio, 0, :subtotal)";
                $stDetalleVenta = $this->conn->prepare($qDetalleVenta);
                $stDetalleVenta->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
                $stDetalleVenta->bindValue(':id_producto', $linea['id_producto'], PDO::PARAM_INT);
                $stDetalleVenta->bindValue(':cantidad', $linea['cantidad']);
                $stDetalleVenta->bindValue(':precio', $linea['precio_unitario']);
                $stDetalleVenta->bindValue(':subtotal', $linea['subtotal']);
                $stDetalleVenta->execute();

                $qKardex = "CALL sp_registrar_movimiento(:id_prod, 2, :id_user, NULL, :id_venta, :id_reserva, :cant, :ant, :nue, :obs)";
                $stKardex = $this->conn->prepare($qKardex);
                $stKardex->bindValue(':id_prod', $linea['id_producto'], PDO::PARAM_INT);
                $stKardex->bindValue(':id_user', $idUsuario, PDO::PARAM_INT);
                $stKardex->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
                $stKardex->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
                $stKardex->bindValue(':cant', $linea['cantidad']);
                $stKardex->bindValue(':ant', $linea['stock_actual_anterior']);
                $stKardex->bindValue(':nue', $linea['stock_actual_nuevo']);
                $stKardex->bindValue(':obs', "Venta generada desde conversión de la reserva #$idReserva");
                $stKardex->execute();
            }

            $queryEnlazar = "UPDATE reservas SET id_venta = :id_venta WHERE id_reserva = :id_reserva";
            $stmtEnlazar = $this->conn->prepare($queryEnlazar);
            $stmtEnlazar->bindValue(':id_venta', $idVenta, PDO::PARAM_INT);
            $stmtEnlazar->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
            $stmtEnlazar->execute();

            // Igual que en Venta::crearVenta(): si no se puede dejar
            // constancia en Auditoría, toda la operación hace rollback.
            Auditor::registrar(
                $this->conn,
                $idUsuario,
                'Ventas',
                'ventas',
                $idVenta,
                'REGISTRAR',
                "Registró la venta #$idVenta por $" . number_format($subtotalGeneral, 2) . " a partir de la reserva #$idReserva (" . count($lineas) . " producto(s))."
            );

            $this->conn->commit();

            return $idVenta;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}