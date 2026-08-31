-- Agrega la columna `motivo_anulacion` a la tabla `ventas`, para el
-- feature/ventas-anular (GCS). Antes de este cambio el motivo con el que
-- un ADMIN anula una venta pagada solo quedaba guardado en el texto de
-- Auditoría (`auditoria.descripcion`); no existía forma de mostrarlo en el
-- detalle de la propia venta sin cruzar con el log de auditoría. Como es
-- buena práctica que el motivo de anulación viva en el propio registro
-- (no solo en el historial), se agrega esta columna dedicada.
--
-- Es un ALTER seguro: la columna es NULL por defecto, así que no rompe
-- ninguna venta ya existente (todas quedan con motivo_anulacion = NULL,
-- que es justo lo correcto para ventas que nunca se anularon).
--
-- Ejecutar una sola vez, en phpMyAdmin o `mysql < este_archivo.sql`,
-- contra la base `db_agroarmijos`.

ALTER TABLE `ventas`
  ADD COLUMN `motivo_anulacion` TEXT NULL DEFAULT NULL AFTER `observacion`;
