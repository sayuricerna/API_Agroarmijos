-- Agrega la columna `id_venta` a la tabla `reservas`, para poder enlazar
-- una reserva confirmada con la venta real que finalmente la concreta.
-- Antes, una reserva CONFIRMADA no tenía ruta de salida más que
-- cancelarla manualmente, y el stock quedaba apartado en
-- stock_reservado de forma indefinida.
--
-- ALTER seguro: la columna es NULL por defecto, así que no afecta
-- reservas existentes (quedan con id_venta = NULL). Se usa
-- ON DELETE SET NULL para no bloquear ni arrastrar una eventual baja
-- de la venta.
--
-- Ejecutar una sola vez, en phpMyAdmin o `mysql < este_archivo.sql`,
-- contra la base `db_agroarmijos`.

ALTER TABLE `reservas`
  ADD COLUMN `id_venta` INT NULL DEFAULT NULL AFTER `estado`,
  ADD CONSTRAINT `fk_reservas_venta`
    FOREIGN KEY (`id_venta`) REFERENCES `ventas`(`id_venta`)
    ON DELETE SET NULL;
