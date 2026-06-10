<?php
// Copiar este archivo como config/database.php y ajustar credenciales.
$host = '127.0.0.1';
$db   = 'carpooling';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Error DB: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo conectar con la base de datos.');
}
