# MOVEON - Plataforma de Carpooling

MOVEON es una aplicacion web PHP/MySQL para gestionar viajes compartidos. Permite que pasajeros busquen y reserven viajes, que conductores publiquen recorridos y que administradores controlen usuarios, vehiculos, reportes, soporte, backups y estadisticas del sistema.

## Tecnologias utilizadas

- PHP con PDO
- MySQL / MariaDB
- Apache mediante XAMPP
- HTML, CSS y JavaScript
- Backups SQL y paquete de recuperacion desde el panel administrador

## Requisitos

- XAMPP instalado
- Apache y MySQL activos
- PHP 8.x o compatible
- Navegador web moderno
- Base de datos MySQL llamada `carpooling`

## Instalacion y ejecucion local

1. Copiar o clonar el proyecto en:

```text
C:\xampp\htdocs\proyecto_taller
```

2. Iniciar Apache y MySQL desde XAMPP.

3. Crear la base de datos:

```sql
CREATE DATABASE carpooling CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

4. Importar `basededatos_definitiva.sql` o un backup SQL generado desde el panel.

5. Revisar la configuracion de conexion en:

```text
config/database.php
```

Tambien se incluye una plantilla:

```text
config/database.example.php
```

6. Abrir el sistema en:

```text
http://localhost/proyecto_taller/public/
```

## Configuracion

Archivos principales:

- `config/database.php`: conexion a MySQL.
- `config/app.php`: configuracion general del sistema.
- `.env.example`: variables de referencia para reconstruir el entorno.
- `config/database.example.php`: plantilla de conexion.
- `config/app.example.php`: plantilla de configuracion general.

Los pagos online estan desactivados en esta version mediante `PAYMENTS_ENABLED = false`.

## Estructura del proyecto

```text
public/                 Pantallas publicas y paneles del sistema
public/admin/           Panel administrativo
public/conductor/       Funciones del conductor
public/reservas/        Reservas del pasajero
core/                   Logica compartida
config/                 Configuracion del sistema
docs/                   Documentacion tecnica y de usuario
basededatos_definitiva.sql
```

## Base de datos

El sistema utiliza tablas como:

- `Usuarios`
- `Conductores`
- `Vehiculos`
- `Publicaciones`
- `Reservas`
- `Pasajeros`
- `PasajerosReservas`
- `Reportes`
- `ReportesPasajeros`
- `Soporte`
- `Notificaciones`
- `BusquedasViajes`

## Roles del sistema

### Administrador

Gestiona usuarios, conductores, vehiculos, viajes, reportes, soporte, backups y reportes gerenciales.

### Conductor

Administra su perfil, vehiculos, viajes publicados, pasajeros y reportes posteriores al viaje.

### Pasajero

Busca viajes, reserva pasajes, confirma llegada, califica conductores y puede reportar problemas.

## Backups y recuperacion ante desastres

Desde el panel administrador se puede descargar:

- Backup SQL de la base de datos.
- Paquete de recuperacion con:
  - SQL
  - `README_RESTAURACION.txt`
  - `MANIFEST.txt`
  - `config/database.example.php`
  - `config/app.example.php`
  - `.env.example`

Para restaurar el sistema desde cero se necesita:

1. Codigo fuente del proyecto.
2. Base de datos o backup SQL.
3. Archivos de configuracion.
4. Archivos subidos por usuarios si existieran fuera de la base.
5. Entorno XAMPP o equivalente.

## Documentacion adicional

La documentacion completa esta en la carpeta `docs/`:

- [Manual de desarrollo](docs/MANUAL_DESARROLLO.md)
- [Manual de usuario por perfiles](docs/MANUAL_USUARIO.md)
- [Manual administrativo](docs/MANUAL_ADMIN.md)
- [Recuperacion ante desastres](docs/RECUPERACION_ANTE_DESASTRES.md)

## Mantenimiento

Antes de hacer cambios importantes se recomienda:

1. Exportar SQL desde el panel.
2. Exportar paquete de recuperacion.
3. Guardar una copia del proyecto.
4. Probar login, reservas, reportes y panel admin despues de modificar el sistema.
