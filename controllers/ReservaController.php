<?php

require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../helpers/Response.php';

class ReservaController {
    private Reserva $model;

    public function __construct(PDO $db) {
        $this->model = new Reserva($db);
    }

    public function listar(): void {
        try {
            $data = $this->model->listar();

            Response::json(
                "success",
                "Reservas cargadas correctamente.",
                $data,
                200
            );
        } catch (Throwable $e) {
            Response::json(
                "error",
                "No se pudieron cargar las reservas: " . $e->getMessage(),
                null,
                500
            );
        }
    }

    public function crear(array $data, int $idUsuario): void {
        if (empty($data['id_cliente'])) {
            Response::json(
                "error",
                "Debe seleccionar un cliente.",
                null,
                400
            );
        }

        if (empty($data['fecha_expiracion'])) {
            Response::json(
                "error",
                "Debe ingresar la fecha de expiración.",
                null,
                400
            );
        }

        if (
            empty($data['productos']) ||
            !is_array($data['productos'])
        ) {
            Response::json(
                "error",
                "Debe agregar al menos un producto.",
                null,
                400
            );
        }

        try {
            $idReserva = $this->model->crear($data, $idUsuario);

            Response::json(
                "success",
                "Reserva registrada correctamente.",
                ["id_reserva" => $idReserva],
                201
            );
        } catch (Throwable $e) {
            Response::json(
                "error",
                $e->getMessage(),
                null,
                400
            );
        }
    }

    public function cancelar(int $idReserva, int $idUsuario): void {
        if ($idReserva <= 0) {
            Response::json(
                "error",
                "ID de reserva no válido.",
                null,
                400
            );
        }

        try {
            $this->model->cancelar($idReserva, $idUsuario);

            Response::json(
                "success",
                "Reserva cancelada y stock liberado.",
                null,
                200
            );
        } catch (Throwable $e) {
            Response::json(
                "error",
                $e->getMessage(),
                null,
                400
            );
        }
    }

    public function confirmar(int $idReserva): void {
        if ($idReserva <= 0) {
            Response::json(
                "error",
                "ID de reserva no válido.",
                null,
                400
            );
        }

        if (!$this->model->confirmar($idReserva)) {
            Response::json(
                "error",
                "La reserva no existe o ya fue procesada.",
                null,
                400
            );
        }

        Response::json(
            "success",
            "Reserva confirmada correctamente.",
            null,
            200
        );
    }
}