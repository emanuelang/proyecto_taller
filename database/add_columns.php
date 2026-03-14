<?php
$host = '127.0.0.1';
$db   = 'carpooling';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->query("ALTER TABLE viajes ADD COLUMN distancia_km INT DEFAULT 0 AFTER destino_id");
    $pdo->query("ALTER TABLE viajes ADD COLUMN duracion_estimada VARCHAR(50) DEFAULT '0h 0m' AFTER distancia_km");
    echo "Columnas añadidas correctamente.\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
