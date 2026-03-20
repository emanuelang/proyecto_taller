<?php
$host = '127.0.0.1';
$db   = 'carpooling';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->query("
        CREATE TABLE IF NOT EXISTS calificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reserva_id INT NOT NULL,
            conductor_id INT NOT NULL,
            pasajero_id INT NOT NULL,
            puntaje INT NOT NULL CHECK(puntaje >= 1 AND puntaje <= 5),
            comentario TEXT,
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Tabla calificaciones creada correctamente.\n";
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
