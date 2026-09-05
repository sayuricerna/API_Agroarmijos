<?php
// config/Roles.php
//
// Nombres de rol validos, tal como quedan guardados en la columna
// `nombre` de la tabla `roles` de la base de datos. Se usan estas
// constantes en las rutas en vez de escribir el nombre del rol
// directamente ('ADMIN', 'BODEGA', etc.): si alguien escribe mal una
// constante (por ejemplo Roles::COMPRAS, que no existe), PHP lanza un
// error de inmediato en vez de bloquear el acceso en silencio, que es
// lo que pasaba antes con un string suelto mal escrito o inventado.
class Roles {
    const ADMIN = 'ADMIN';
    const VENDEDOR = 'VENDEDOR';
    const BODEGA = 'BODEGA';
    const GERENTE = 'GERENTE';
}
