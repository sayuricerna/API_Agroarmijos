<?php
require_once __DIR__ . '/../helpers/Auditor.php';

class Reserva {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function listar(): array {
        $query = "SELECT
                    r.id_reserva,
                    r.id_cliente,
                    r.id_usuario,
                    r.fecha_reserva,
                    r.fecha_expiracion,
                    r.estado,
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

            foreach ($detalles as $detalle) {
                $cantidad = (float) $detalle['cantidad'];
                $idProducto = (int) $detalle['id_producto'];

                // Se lee el stock_disponible actual antes de liberarlo, para
                // poder dejar la trazabilidad anterior/nuevo en el Kárdex.
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

                // Kárdex: registra la liberación de la reserva (tipo 6 =
                // CANCELACION_RESERVA), misma convención que al crearla:
                // anterior/nuevo son stock_disponible, no stock_actual.
                $stockDisponibleNuevo = $stockDisponibleAnterior + $cantidad;

                $queryKardex = "CALL sp_registrar_movimiento(:id_prod, 6, :id_user, NULL, NULL, :id_reserva, :cant, :ant, :nue, 'Liberación de reserva')";
                $stmtKardex = $this->conn->prepare($queryKardex);
                $stmtKardex->bindValue(':id_prod', $idProducto, PDO::PARAM_INT);
                $stmtKardex->bindValue(':id_user', $idUsuario, PDO::PARAM_INT);
                $stmtKardex->bindValue(':id_reserva', $idReserva, PDO::PARAM_INT);
                $stmtKardex->bindValue(':cant', $cantidad);
                $stmtKardex->bindValue(':ant', $stockDisponibleAnterior);
                $stmtKardex->bindValue(':nue', $stockDisponibleNuevo);
                $stmtKardex->execute();
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
}