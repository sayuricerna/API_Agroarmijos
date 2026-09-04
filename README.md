# API AgroArmijos (backend)

API en PHP puro (sin framework) para el sistema de AgroArmijos. Va de la mano con el repo frontend `agroarmijos_piv2` (Ionic/Angular).

## Requisitos

- XAMPP con PHP 8.2 (Apache + MySQL). El proyecto está pensado para vivir en `htdocs/api_agroarmijos` — respetar ese nombre de carpeta, porque el frontend arma las URLs de la API asumiendo esa ruta.
- Composer.

## Instalación

1. Clonar este repositorio dentro de `htdocs`, de forma que quede en `.../xampp/htdocs/api_agroarmijos`.
2. Copiar `.env.example` a `.env` y ajustar los valores si el XAMPP local usa otro usuario/contraseña de MySQL (por defecto suele ser `root` sin contraseña):
   ```
   copy .env.example .env
   ```
3. Instalar las dependencias:
   ```
   composer install
   ```
4. Crear la base de datos e importar el dump correspondiente (ver más abajo "Cómo importar la base de datos"). El nombre de la base debe coincidir con `DB_NAME` en el `.env` (por defecto `db_agroarmijos`).
5. Iniciar Apache y MySQL desde el panel de control de XAMPP.
6. Verificar que responda entrando en el navegador a:
   ```
   http://localhost/api_agroarmijos/index.php/auth/login
   ```
   (debe devolver un error de "método no permitido" o similar en vez de una pantalla en blanco/404 — eso confirma que Apache y el enrutador de `index.php` están funcionando).

## Cómo importar la base de datos

Al recibir el archivo `.sql` con la estructura y los datos de prueba actuales:

1. Abrir phpMyAdmin (`http://localhost/phpmyadmin`).
2. Si el `.sql` no trae ya las sentencias `CREATE DATABASE`/`USE` (confirmar con quien lo compartió; normalmente sí las incluye), crear primero una base vacía con el mismo nombre configurado en `DB_NAME` del `.env`.
3. Entrar a esa base (o a la raíz, si el archivo ya trae `CREATE DATABASE`) → pestaña **Importar** → seleccionar el archivo `.sql` → **Continuar/Importar**.
4. Con eso quedan las mismas tablas y los mismos datos de prueba con los que se está probando la app.

Cada vez que haya cambios grandes en la base (nuevas tablas/columnas), lo más simple es solicitar un `.sql` actualizado en vez de aplicar migraciones sueltas a mano.

## Notas

- El `.htaccess` incluido es necesario para que el header `Authorization` (token JWT) le llegue bien a PHP bajo Apache/XAMPP — no eliminarlo ni desactivarlo.
- `CORS_ALLOWED_ORIGIN=*` en el `.env` de ejemplo es para desarrollo local; no usar así en producción.
- El histórico de cambios de este repo está en `CHANGELOG.md` (versionado SemVer + tags `LB-XXX` compartidos con el repo frontend).
