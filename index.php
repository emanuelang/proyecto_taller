<?php
/**
 * Redirección automática a la carpeta pública.
 * Esta es la forma más segura de evitar el "Index of" y el bucle de redirecciones en XAMPP.
 */
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$uri = '/proyecto_taller/public/';

header("Location: $protocol://$host$uri", true, 302);
exit;
