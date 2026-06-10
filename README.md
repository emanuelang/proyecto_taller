# MOVEON - Plataforma de carpooling

MOVEON es una aplicacion web PHP/MySQL para publicar viajes compartidos, reservar pasajes, administrar conductores, vehiculos, usuarios, reportes y soporte.

## Tecnologias

- PHP con PDO.
- MySQL/MariaDB.
- XAMPP para ejecucion local.
- HTML, CSS y JavaScript sin framework.
- Generacion de backups SQL desde el panel administrativo.

## Requisitos

- Apache y MySQL activos en XAMPP.
- PHP 8 o compatible con PDO MySQL.
- Navegador moderno.
- Base de datos creada en MySQL.

## Instalacion local

1. Copiar el proyecto en `C:\xampp\htdocs\proyecto_taller`.
2. Crear una base de datos MySQL para la aplicacion, por ejemplo `carpooling`.
3. Si la base esta vacia, importar `basededatos_definitiva.sql`.
4. Revisar `config/database.php` y `config/local.php` para usuario, clave, host y nombre de base.
5. Abrir `http://localhost/proyecto_taller/public/`.

Si ya existe una base con datos, no reimportar `basededatos_definitiva.sql` encima. Primero hacer un backup y luego ejecutar `database/actualizar_base_actual.sql`, que agrega columnas/tablas faltantes sin borrar datos.

## Configuracion

La configuracion principal esta en:

- `config/database.php`: conexion PDO.
- `config/app.php`: opciones generales, rutas y banderas como `PAYMENTS_ENABLED`.
- `core/security.php`: CSRF y utilidades de seguridad.

En la version actual los pagos estan ocultos/desactivados para mantener el flujo sin plataforma de cobro externa.

## Estructura general

- `public/`: pantallas publicas, panel de usuario, conductor y administrador.
- `public/admin/`: dashboard, conductores, vehiculos, usuarios, viajes, reportes, soporte y backups.
- `core/`: logica compartida de viajes, reservas, seguridad y servicios.
- `config/`: configuracion de base de datos y aplicacion.
- `docs/`: documentacion tecnica, usuario, administracion y recuperacion.
- `basededatos_definitiva.sql`: estructura definitiva para crear una base nueva.
- `database/actualizar_base_actual.sql`: migracion para actualizar una base vieja sin perder datos.
- `database/seed_admin_demo.php`: datos de prueba para desarrollo o demostraciones.

## Base de datos

El sistema usa tablas como `Usuarios`, `Conductores`, `Vehiculos`, `Publicaciones`, `Reservas`, `Pasajeros`, `PasajerosReservas`, `Reportes`, `ReportesPasajeros`, `Soporte`, `Notificaciones` y tablas de confirmaciones de viaje.

Los datos sensibles cargados por usuarios incluyen DNI, telefono, imagenes de DNI, foto de perfil y datos del vehiculo. Deben tratarse como informacion privada.

### Instalacion nueva vs actualizacion

- Instalacion nueva: crear una base vacia e importar `basededatos_definitiva.sql`.
- Base existente: hacer backup y ejecutar `database/actualizar_base_actual.sql`.
- Datos demo: ejecutar `database/seed_admin_demo.php` solo en entornos de prueba.

No mezclar estos usos: el SQL definitivo crea la estructura completa, el script de actualizacion corrige bases viejas, y el seed carga informacion ficticia.

## Roles

- Administrador: gestiona usuarios, conductores, vehiculos, viajes, reportes, soporte, backups y reportes gerenciales.
- Conductor: administra perfil, vehiculos, viajes publicados, pasajeros y reportes.
- Pasajero: busca viajes, reserva pasajes, confirma llegada, califica y reporta problemas.

## Recuperacion ante Desastres

Ver `docs/RECUPERACION_ANTE_DESASTRES.md`. En resumen, para reconstruir el sistema se necesita el proyecto, la base de datos o backup SQL, archivos de configuracion y cualquier archivo subido por usuarios que no este guardado directamente en la base.

Desde el dashboard administrador se puede descargar:

- Un SQL de base de datos.
- Un paquete de recuperacion con SQL, `README_RESTAURACION.txt`, `MANIFEST.txt`, `config/database.example.php`, `config/app.example.php` y `.env.example`.

## Documentacion adicional

- `docs/MANUAL_DESARROLLO.md`: guia para desarrolladores.
- `docs/MANUAL_USUARIO.md`: guia por perfil.
- `docs/MANUAL_ADMIN.md`: manual administrativo.
- `public/admin/manual_admin.php`: manual integrado al panel.
