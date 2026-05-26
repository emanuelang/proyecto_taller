## Levantar en local

1. Copiar el proyecto en:

C:\xampp\htdocs\proyecto_taller

2. Iniciar Apache y MySQL desde XAMPP.

3. Crear la base de datos:

carpooling

4. Importar:

basededatos_definitiva.sql

5. Abrir:

http://localhost/proyecto_taller/public/

## Configuracion privada

Crear `config/local.php` con credenciales de Mercado Pago y correo.

Este archivo no se sube a GitHub.

## Levantar con ngrok

Ejecutar:

bash
ngrok http 80
