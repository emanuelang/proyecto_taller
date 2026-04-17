<?php
// Detectar si estamos en HTTPS (incluso si estamos detrás de proxies como ngrok)
$isSecure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $isSecure = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
    $isSecure = true;
}

$protocol = $isSecure ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

// Determinamos la ubicación base del proyecto de manera dinámica.
// Esto permite que el proyecto corra desde cualquier carpeta y a la vez
// apuntando sus rutas base (assets, etc) correctamente a /public/
$docRoot = str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
$dirPath = str_replace('\\', '/', dirname(__DIR__) . '/public');
$baseDir = str_replace($docRoot, '', $dirPath);
$baseDir = rtrim($baseDir, '/') . '/';

// Si corremos scripts desde CLI sin web server, podemos tener un fallback seguro
if (empty($docRoot) || empty($_SERVER['HTTP_HOST'])) {
    $baseDir = '/proyecto_taller/public/';
}

define('BASE_URL', $protocol . $host . $baseDir);
