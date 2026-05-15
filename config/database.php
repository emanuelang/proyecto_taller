<?php
$host = '127.0.0.1';
$db   = 'carpooling';
$user = 'root';
$pass = ''; // si tu XAMPP tiene contraseña, ponela
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Intentamos aumentar el límite de paquete para fotos pesadas.
    // Primero intentamos SESSION, si falla (porque es read-only), intentamos GLOBAL.
    try {
        @$pdo->exec('SET SESSION max_allowed_packet = 67108864'); // 64 MB
    } catch (Exception $e) {
        try {
            // Si SESSION falló, intentamos GLOBAL (necesita privilegios de root, comunes en XAMPP)
            @$pdo->exec('SET GLOBAL max_allowed_packet = 67108864');
        } catch (Exception $e2) {
            // Si ambos fallan, el servidor tiene límites muy estrictos.
        }
    }
} catch (PDOException $e) {
    die('Error DB: ' . $e->getMessage());
}
