# Recuperacion ante Desastres

Esta guia explica como reconstruir MOVEON desde cero si se pierde el entorno local, la base de datos o el servidor.

## Que respalda el backup del panel

El panel ofrece dos descargas:

- `Exportar SQL`: genera solo el backup logico de la base de datos.
- `Exportar paquete de recuperacion`: genera un paquete comprimido con SQL, plantillas de configuracion e instrucciones de restauracion.

`Exportar SQL` llama a `public/admin/backup.php`.
`Exportar paquete de recuperacion` llama a `public/admin/backup_recuperacion.php`.

Ese archivo SQL incluye:

- Todas las tablas de la base actual.
- Sentencias `CREATE TABLE`.
- Sentencias `INSERT` con los datos existentes.
- Desactivacion temporal de claves foraneas durante la restauracion.
- Firma del sistema para validar que el archivo fue generado por MOVEON.

La importacion se realiza desde `public/admin/import_backup.php`, que valida:

- Extension `.sql`.
- Tamano maximo configurado en el codigo.
- Firma del backup.
- Token CSRF del formulario.

## Contenido del paquete de recuperacion

El paquete incluye:

- `backup_carpooling_FECHA.sql`: estructura y datos de la base.
- `README_RESTAURACION.txt`: pasos para reconstruir el sistema.
- `MANIFEST.txt`: fecha, version PHP y tablas incluidas.
- `config/database.example.php`: plantilla de conexion a MySQL.
- `config/app.example.php`: plantilla de configuracion general.
- `.env.example`: variables de entorno documentadas.

Si `ZipArchive` esta habilitado, el paquete se descarga como `.zip`. Si no esta habilitado, el sistema usa `.tar.gz`.

## Que no cubre completamente

El backup del panel cubre la base de datos, pero no reemplaza una copia completa del sistema. Para restaurar todo tambien se necesita:

- Codigo fuente del proyecto.
- `config/database.php` y `config/local.php`.
- Archivos subidos por usuarios si alguna funcionalidad guarda imagenes en disco.
- Archivos de documentacion y scripts locales.
- Configuracion de Apache/MySQL de XAMPP si fue personalizada.

## Restauracion desde cero

1. Instalar XAMPP o preparar Apache, PHP y MySQL equivalentes.
2. Copiar el proyecto en `C:\xampp\htdocs\proyecto_taller`.
3. Crear la base de datos en MySQL.
4. Importar `basededatos_definitiva.sql` o el ultimo backup SQL generado por el panel.
5. Revisar credenciales en `config/database.php` y `config/local.php`.
6. Copiar carpetas de archivos subidos por usuarios si existieran fuera de la base.
7. Iniciar Apache y MySQL.
8. Abrir `http://localhost/proyecto_taller/public/`.
9. Probar login de administrador, busqueda de viajes, reservas y panel admin.

## Recomendacion operativa

Guardar en un lugar externo al servidor:

- Backup SQL periodico.
- Copia del proyecto completo.
- Copia de configuraciones locales.
- Copia de archivos subidos por usuarios.

Sin esos elementos, el sistema puede levantar la base pero perder imagenes, credenciales locales o cambios de codigo no versionados.
