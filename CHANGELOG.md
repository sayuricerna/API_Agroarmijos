# Changelog — API AgroArmijos (backend)

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado según [SemVer](https://semver.org/lang/es/) (`MAYOR.MENOR.PARCHE`).

Cada entrada referencia también el tag académico `LB-XXX` correspondiente (hito compartido con el repo frontend `agroarmijos_piv2`, gestión de configuración del curso). El número de versión SemVer es **propio de este repo**: backend y frontend evolucionan de forma independiente y no necesariamente comparten número de versión, ya que son proyectos desacoplados — lo que sí comparten es el tag `LB-XXX` cuando un hito los toca a ambos.

## [No liberado]

_Sin cambios pendientes de liberar._

---

## [1.6.0] - 2026-09-01 — `LB-011`
### Added
- `Reserva::expirarVencidas()`: expira automáticamente (perezoso, sin cron) las reservas `PENDIENTE` cuya `fecha_expiracion` ya pasó, liberando el stock apartado.
- `Reserva::convertirEnVenta()` + endpoint `PUT /reservas/convertir/:id`: convierte una reserva `CONFIRMADA` en una venta real, enlazando `reservas.id_venta`.
- Migración `2026_08_31_reservas_id_venta.sql`: agrega columna `id_venta` a `reservas`.

### Fixed
- `Venta::crearVenta()` ahora valida el stock contra `stock_disponible` (no `stock_actual`), respetando unidades ya apartadas por una reserva.

## [1.5.1] - 2026-08-31 — `LB-010`
### Fixed
- `Producto::ajustarStock()`: valida que un ajuste de disminución no deje `stock_actual` en negativo, dentro de la misma transacción (mismo criterio que `Compra::anular()`).

## [1.5.0] - 2026-08-31 — `LB-009`
### Added
- Módulo de Compras: `GET /compras/listar`, `GET /compras/detalle/:id`, `PUT /compras/anular/:id` (rol ADMIN, motivo obligatorio, revierte stock con validación de insuficiencia).

### Fixed
- Rol `'COMPRAS'` inexistente en `routes/compras.php` (`crear`), corregido a `'BODEGA'`.

## [1.4.0] - 2026-08-31 — `LB-008`
### Added
- Anulación de ventas pagadas (`PUT /ventas/anular/:id`, rol ADMIN, motivo obligatorio): restituye stock, registra Kárdex (`DEVOLUCION_VENTA`) y Auditoría (`ANULAR`).
- Columna `motivo_anulacion` en `ventas`, expuesta en `listarVentas()`.

## [1.3.1] - 2026-08-31 — `LB-007`
### Fixed
- 404 en `GET /ubicaciones/listar`: no existía ruta/modelo para `ubicaciones`; se enrutó por el sistema genérico `Catalogo`.

## [1.3.0] - 2026-08-30 — `LB-006`
### Added
- Ajustes de Inventario: `POST /productos/ajustar` (rol ADMIN/BODEGA), tipos de movimiento `AJUSTE_POSITIVO`/`AJUSTE_NEGATIVO` resueltos dinámicamente.

## [1.2.0] - 2026-08-30 — `LB-005`
### Added
- Auditoría Parte 2: integra registro de auditoría en ventas, compras, productos, usuarios, catálogos, reservas y autenticación (login/logout).

## [1.1.0] - 2026-08-30 — `LB-003`
### Added
- Módulo de Auditoría (backend): `helpers/Auditor.php`, `models/Auditoria.php`, `controllers/AuditoriaController.php`, `routes/auditoria.php`.

## [1.0.1] - 2026-08-30 — `LB-002`
### Fixed
- El precio de venta ya no se confía al frontend: se valida/recalcula siempre contra `productos.precio_venta` en la base de datos.

## [1.0.0] - 2026-08-16 — baseline
- Depósito inicial funcional del backend (equivalente al `LB-001` del frontend, que marca el depósito inicial de elementos de configuración del proyecto). Este repo no llevaba tag propio en ese punto; se documenta aquí como línea base para el versionado semántico.

---

### Nota sobre el mapeo LB-XXX → SemVer
Este mapeo es una propuesta de reconstrucción retroactiva a partir del historial real de tags y commits (`git log`, `git tag`), clasificando cada cambio como MAYOR (rompe compatibilidad), MENOR (funcionalidad nueva y compatible) o PARCHE (corrección de errores, compatible) según su naturaleza real. `LB-002`, `LB-007` y `LB-010` no tienen equivalente en el repo frontend porque fueron cambios exclusivos de backend.
