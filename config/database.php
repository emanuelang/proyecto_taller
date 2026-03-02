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
} catch (PDOException $e) {
    die('Error DB: ' . $e->getMessage());
}
