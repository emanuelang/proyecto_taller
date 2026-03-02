<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];

    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, email, password, fecha_nacimiento)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([$nombre, $email, $password, $fecha_nacimiento]);

    header("Location: login.php");
    exit;
}
?>

<h2>Registro de Usuario</h2>

<form method="POST">

    <label>Nombre:</label>
    <input type="text" name="nombre" required><br><br>

    <label>Email:</label>
    <input type="email" name="email" required><br><br>

    <label>Fecha de nacimiento:</label>
    <input type="date" name="fecha_nacimiento" required><br><br>

    <label>Contraseña:</label>
    <input type="password" name="password" required><br><br>

    <button type="submit">Registrarse</button>
</form>
