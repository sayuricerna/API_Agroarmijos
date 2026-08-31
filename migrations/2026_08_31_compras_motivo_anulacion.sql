-- Agrega la columna `motivo_anulacion` a la tabla `compras`, para el
-- feature/compras-listar-anular (GCS). Mismo criterio que se aplicó a
-- `ventas` en 2026_08_31_ventas_motivo_anulacion.sql: el motivo con el
-- que un ADMIN anula una compra ya recibida debe quedar visible en el
-- propio registro, no solo en el texto de Auditoría.
--
-- Es un ALTER seguro: la columna es NULL por defecto, así que no rompe
-- ninguna compra ya existente (todas quedan con motivo_anulacion = NULL,
-- que es justo lo correcto para compras que nunca se anularon).
--
-- Ejecutar una sola vez, en phpMyAdmin o `mysql < este_archivo.sql`,
-- contra la base `db_agroarmijos`.

ALTER TABLE `compras`
  ADD COLUMN `motivo_anulacion` TEXT NULL DEFAULT NULL AFTER `observacion`;
