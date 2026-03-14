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

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Registro - Carpooling</title>
    <link rel="stylesheet" href="main.css">
</head>
<body>
    <div class="nav-menu">
        <h2>Crear Cuenta</h2>
        <a href="index.php" style="margin-left: auto;">← Volver al inicio</a>
    </div>

    <form method="POST">
        <h3 style="margin-top:0; color:var(--primary); text-align:center;">Únete a Carpooling</h3>

        <label>Nombre completo:</label>
        <input type="text" name="nombre" placeholder="Ej: Juan Pérez" required>

        <label>Correo Electrónico:</label>
        <input type="email" name="email" placeholder="ejemplo@email.com" required>

        <label>Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" required>

        <label>Contraseña:</label>
        <input type="password" name="password" placeholder="Mínimo 8 caracteres" required>

        <button type="submit" style="width: 100%; margin-top: 15px;">Registrarse</button>

        <p style="text-align:center; margin-top: 20px;">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </p>
    </form>
</body>
</html>
