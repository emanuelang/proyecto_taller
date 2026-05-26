# Manual de desarrollo - MOVEON

Este documento explica como levantar el proyecto MOVEON en un entorno local con XAMPP y como exponerlo con ngrok para probar integraciones externas como Mercado Pago.

## 1. Requisitos

Para ejecutar el proyecto se necesita:

- Windows.
- XAMPP instalado.
- Apache activo desde el panel de XAMPP.
- MySQL activo desde el panel de XAMPP.
- PHP incluido en XAMPP.
- Navegador web.
- ngrok, solo si se quiere probar el proyecto con una URL publica HTTPS.
- Cuenta de Mercado Pago Developers para probar pagos.
- Cuenta Gmail con contrasena de aplicacion para probar recuperacion de contrasena.

## 2. Ubicacion del proyecto

El proyecto debe estar dentro de la carpeta `htdocs` de XAMPP:

```text
C:\xampp\htdocs\proyecto_taller
```

La entrada publica del sistema esta dentro de la carpeta `public`.

URL local principal:

```text
http://localhost/proyecto_taller/public/
```

## 3. Base de datos

El sistema usa MySQL y la base de datos se llama:

```text
carpooling
```

### Crear e importar la base

1. Abrir el panel de XAMPP.
2. Iniciar `Apache`.
3. Iniciar `MySQL`.
4. Abrir phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Crear una base de datos llamada `carpooling`.
6. Importar el archivo SQL principal:

```text
basededatos_definitiva.sql
```

7. Confirmar que se hayan creado las tablas del sistema.

## 4. Configuracion de la base de datos

La conexion se configura en:

```text
config/database.php
```

Configuracion esperada para XAMPP por defecto:

```php
$host = '127.0.0.1';
$db   = 'carpooling';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
```

Si MySQL tiene contrasena, colocarla en `$pass`.

## 5. Levantar el proyecto en localhost

1. Abrir XAMPP.
2. Iniciar `Apache`.
3. Iniciar `MySQL`.
4. Verificar que exista la base `carpooling`.
5. Abrir en el navegador:

```text
http://localhost/proyecto_taller/public/
```

Si aparece un error de conexion a la base de datos, revisar:

- Que MySQL este iniciado.
- Que la base `carpooling` exista.
- Que el archivo SQL haya sido importado.
- Que `config/database.php` tenga usuario y contrasena correctos.

## 6. Configuracion privada local

Las credenciales privadas no deben colocarse directamente en el codigo publico. El proyecto usa:

```text
config/local.php
```

Este archivo esta incluido en `.gitignore`, por lo tanto no debe subirse a GitHub.

Ejemplo de estructura:

```php
<?php
return [
    'app' => [
        'public_base_url' => '',
    ],
    'mp' => [
        'access_token' => 'TU_ACCESS_TOKEN',
        'public_key' => 'TU_PUBLIC_KEY',
        'ssl_verify' => true,
        'local_test_mode' => false,
    ],
    'mail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'tu_correo@gmail.com',
        'password' => 'tu_contrasena_de_aplicacion',
        'from_email' => 'tu_correo@gmail.com',
        'from_name' => 'MOVEON',
    ],
];
```

Para trabajar solamente en localhost, se puede dejar:

```php
'public_base_url' => '',
```

## 7. Levantar el proyecto con ngrok

ngrok permite exponer el localhost con una URL publica HTTPS. Esto es importante para probar redirecciones de Mercado Pago.

### Pasos

1. Abrir XAMPP.
2. Iniciar `Apache`.
3. Iniciar `MySQL`.
4. Abrir una terminal.
5. Ejecutar:

```bash
ngrok http 80
```

6. Copiar la URL HTTPS que muestra ngrok. Ejemplo:

```text
https://abcd-1234.ngrok-free.app
```

7. La URL publica del proyecto queda asi:

```text
https://abcd-1234.ngrok-free.app/proyecto_taller/public/
```

8. Configurar esa URL en `config/local.php`:

```php
'app' => [
    'public_base_url' => 'https://abcd-1234.ngrok-free.app/proyecto_taller/public',
],
```

Importante: cada vez que ngrok genere una URL nueva, hay que actualizar `public_base_url`.

## 8. Mercado Pago

Para probar Mercado Pago correctamente:

1. Configurar el `access_token` en `config/local.php`.
2. Configurar `public_base_url` con la URL HTTPS de ngrok.
3. Entrar al sistema desde la URL de ngrok, no desde localhost.
4. Iniciar una reserva o una carga de saldo.
5. Realizar el pago.
6. Mercado Pago redirige nuevamente al sistema.
7. El sistema valida el pago con Mercado Pago antes de confirmar la reserva o acreditar saldo.

Ejemplo de URL correcta para probar:

```text
https://abcd-1234.ngrok-free.app/proyecto_taller/public/
```

No usar `http://localhost/...` para probar Mercado Pago, porque Mercado Pago necesita una URL publica para volver al sistema.

## 9. Correo de recuperacion de contrasena

El sistema usa SMTP para enviar correos de recuperacion.

Para Gmail:

1. Entrar a la cuenta de Gmail.
2. Activar verificacion en dos pasos.
3. Crear una contrasena de aplicacion.
4. Colocar esa contrasena en `config/local.php`.

Ejemplo:

```php
'mail' => [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'tu_correo@gmail.com',
    'password' => 'tu_contrasena_de_aplicacion',
    'from_email' => 'tu_correo@gmail.com',
    'from_name' => 'MOVEON',
],
```

No usar la contrasena normal de Gmail.

## 10. Archivos que no deben subirse a Git

No subir archivos con credenciales, temporales o locales.

El archivo `.gitignore` debe incluir:

```text
config/local.php
*.log
*.tmp
Thumbs.db
.DS_Store
```

## 11. Pruebas basicas antes de presentar

Antes de mostrar el sistema, revisar:

- Apache iniciado.
- MySQL iniciado.
- Base `carpooling` creada.
- SQL importado.
- `config/database.php` correcto.
- `config/local.php` configurado si se usan servicios externos.
- URL de ngrok actualizada si se prueba Mercado Pago.
- Login de administrador.
- Login de pasajero.
- Login de conductor.
- Busqueda de viajes.
- Creacion de viaje.
- Reserva de viaje.
- Pago con Mercado Pago.
- Carga de saldo.
- Recuperacion de contrasena.
- Cancelacion de reserva.
- Panel de administrador.

## 12. Problemas frecuentes

### Error de conexion a la base de datos

Revisar que MySQL este iniciado y que la base `carpooling` exista.

### Mercado Pago no vuelve al sistema

Revisar que `public_base_url` tenga la URL HTTPS actual de ngrok.

### El correo no se envia

Revisar usuario, contrasena de aplicacion y conexion SMTP.

### ngrok cambia la URL

Actualizar `public_base_url` en `config/local.php`.

### Apache no inicia

Puede haber otro servicio usando el puerto 80. Revisar el panel de XAMPP y cambiar el puerto si es necesario.

## 13. URLs utiles

Localhost:

```text
http://localhost/proyecto_taller/public/
```

phpMyAdmin:

```text
http://localhost/phpmyadmin
```

Proyecto con ngrok:

```text
https://TU_URL_NGROK/proyecto_taller/public/
```

