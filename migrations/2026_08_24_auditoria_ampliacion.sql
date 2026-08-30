-- Amplía la tabla `auditoria` (ya existía en la base de datos pero no la
-- usaba ningún controller/model) para soportar el módulo de Auditoría.
-- La tabla está vacía (0 filas) al momento de escribir esto, así que este
-- ALTER es seguro e instantáneo: no hay datos existentes que migrar.
--
-- Cambios:
--   1) Nueva columna `modulo` — nombre del módulo de la app donde ocurrió
--      la acción (Productos, Ventas, Usuarios...). La columna `tabla` que
--      ya existía se conserva tal cual y ahora funciona como el nombre
--      técnico de la entidad (productos, ventas, usuarios...).
--   2) Nueva columna `descripcion` — resumen legible de la acción, para
--      mostrar en el listado y el detalle sin tener que interpretar JSON.
--   3) El ENUM de `accion` se reemplaza por el vocabulario de acciones de
--      negocio que pidió Sayuri (CREAR, EDITAR, ACTIVAR, DESACTIVAR,
--      REGISTRAR, ANULAR, AJUSTAR, CONFIRMAR, CANCELAR, INICIAR_SESION,
--      CERRAR_SESION) en vez del genérico INSERT/UPDATE/DELETE/LOGIN/
--      LOGOUT. Como la tabla está vacía, no hay filas que pudieran violar
--      el nuevo ENUM.
--   4) Dos índices nuevos para los filtros del listado (modulo, accion).
--
-- Ejecutar una sola vez, en phpMyAdmin o `mysql < este_archivo.sql`,
-- contra la base `db_agroarmijos`.

ALTER TABLE `auditoria`
  ADD COLUMN `modulo` VARCHAR(60) NOT NULL DEFAULT '' AFTER `id_usuario`,
  ADD COLUMN `descripcion` VARCHAR(255) DEFAULT NULL AFTER `accion`,
  MODIFY COLUMN `accion` ENUM(
    'CREAR','EDITAR','ACTIVAR','DESACTIVAR','REGISTRAR','ANULAR',
    'AJUSTAR','CONFIRMAR','CANCELAR','INICIAR_SESION','CERRAR_SESION'
  ) NOT NULL;

ALTER TABLE `auditoria`
  ADD INDEX `idx_auditoria_modulo` (`modulo`),
  ADD INDEX `idx_auditoria_accion` (`accion`);
