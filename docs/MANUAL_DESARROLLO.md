# Manual de desarrollo

## Objetivo del sistema

MOVEON es una plataforma de carpooling donde pasajeros buscan viajes, conductores publican viajes y administradores supervisan operacion, usuarios, vehiculos, reportes, soporte y backups.

## Arquitectura

La aplicacion es PHP tradicional con paginas por modulo:

- `public/` contiene las pantallas accesibles desde navegador.
- `public/admin/` contiene el panel administrativo.
- `core/` contiene logica compartida de viajes, reservas, seguridad y servicios.
- `config/` contiene configuracion de base y opciones generales.
- `docs/` contiene documentacion tecnica y manuales.

No se usa framework MVC. La convencion del proyecto es incluir archivos comunes con `require_once`, usar PDO para MySQL y mantener la logica cerca de cada pantalla.

## Base de datos

Tablas principales observadas:

- `Usuarios`: datos de cuenta, DNI, telefono, foto de perfil, estado y saldo heredado.
- `Conductores`: solicitud y estado de conductor.
- `Vehiculos`: vehiculos registrados y estado de aprobacion.
- `Publicaciones`: viajes publicados.
- `Reservas`: reservas realizadas.
- `Pasajeros` y `PasajerosReservas`: pasajeros reales del viaje, incluyendo reservas para terceros.
- `Notificaciones`: mensajes y acciones para usuarios.
- `Reportes` y `ReportesPasajeros`: reclamos contra conductores o pasajeros.
- `Soporte`: tickets de ayuda.
- `ConfirmacionesViaje` y `ConfirmacionesConductorViaje`: confirmaciones posteriores a viajes finalizados.

El archivo `database/schema.txt` puede no estar sincronizado con la base real. Para cambios estructurales usar como referencia `basededatos_definitiva.sql` y consultas reales de MySQL.

## Configuracion importante

- `config/database.php`: conexion PDO.
- `config/app.php`: constantes generales, rutas y banderas.
- `PAYMENTS_ENABLED`: controla si se muestran funcionalidades de pago. En la version actual los pagos estan desactivados visualmente.
- `core/security.php`: CSRF, sanitizacion y controles compartidos.
- `core/trips.php`: sincronizacion de viajes finalizados y notificaciones relacionadas.

## Seguridad y sesiones

Buenas practicas actuales:

- Acceso admin protegido por `public/admin/auth.php`.
- Formularios sensibles con CSRF.
- Consultas con PDO preparado.
- Datos sensibles de viaje ocultos hasta que exista reserva.
- Imagenes de DNI visibles para administracion.
- Notificaciones con acciones controladas por tipo.

Recomendaciones:

- Mantener validaciones server-side aunque exista validacion visual.
- No mostrar punto de encuentro, patente, fotos del vehiculo ni datos personales sin autorizacion.
- No guardar nuevas credenciales o secretos en archivos versionados.
- Mantener backups fuera del directorio publico.

## Integraciones externas

El proyecto conserva referencias a pagos, pero la version actual no opera con plataforma de pago externa. Si se retoma Mercado Pago u otra pasarela, se debe implementar validacion real de cuentas, webhooks y conciliacion antes de mostrarlo a usuarios.

## Como agregar funcionalidades

1. Identificar el modulo existente mas cercano.
2. Revisar tablas disponibles y no asumir columnas nuevas.
3. Crear o ajustar consultas con PDO preparado.
4. Agregar controles CSRF en formularios POST.
5. Mantener el estilo visual de `public/styles.css`.
6. Si es administracion, agregar la entrada en `public/admin/_nav.php`.
7. Documentar el flujo en este manual o en `docs/MANUAL_ADMIN.md`.
8. Ejecutar `php -l` sobre archivos PHP modificados.

## Reportes gerenciales

El modulo esta en `public/admin/dashboard.php`, debajo de los indicadores principales y antes del grafico mensual de reservas.

Componentes:

- `$reporte_tipos`: lista de reportes disponibles.
- Parametros GET: `reporte_desde`, `reporte_hasta`, `reporte_tipo`.
- Helpers `dashboard_scalar()` y `dashboard_rows()`.
- Un `switch` construye metricas, filas de tabla y datos de grafico.

El tipo `tecnico` genera un reporte general del funcionamiento del sistema. Combina eventos fechados del periodo con estados actuales: usuarios suspendidos o eliminados, vehiculos registrados, conductores, viajes, reservas, confirmaciones, reportes, soporte y notificaciones. No reemplaza una auditoria administrativa historica porque el sistema aun no tiene una tabla especifica para registrar cada accion del administrador.

Para agregar un reporte:

1. Agregar la opcion en `$reporte_tipos`.
2. Crear un caso nuevo en el `switch`.
3. Completar `$reporte_metricas`, `$reporte_columnas`, `$reporte_filas` y `$reporte_grafico`.
4. Usar solo tablas y columnas existentes.

## Backups

`public/admin/backup.php` exporta estructura y datos de todas las tablas de la base actual. `public/admin/import_backup.php` importa SQL generado por el sistema.

`public/admin/backup_recuperacion.php` genera un paquete de recuperacion ante desastres. Incluye el SQL, `README_RESTAURACION.txt`, `MANIFEST.txt`, `config/database.example.php`, `config/app.example.php` y `.env.example`. Si la extension `ZipArchive` esta disponible descarga `.zip`; si no, usa `.tar.gz` con `PharData`.

Los archivos `config/database.example.php`, `config/app.example.php` y `.env.example` documentan la configuracion necesaria sin exponer credenciales reales.

Ver tambien `docs/RECUPERACION_ANTE_DESASTRES.md`.

## Mantenimiento

- Hacer backup antes de cambios de base de datos.
- Probar login de administrador, pasajero y conductor despues de cambios grandes.
- Revisar que no haya mojibake nuevo en textos visibles.
- Revisar que las acciones de reportes y notificaciones no dejen datos sensibles expuestos.
