<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $dni = trim($_POST['dni']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Quitamos la fecha de nacimiento que no está en la BD y no se usaba

    $stmt = $pdo->prepare("
        INSERT INTO Usuarios (Nombre, Apellido, DNI, Correo, Telefono, Contraseña)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$nombre, $apellido, $dni, $email, $telefono, $password]);

    // Lo agregamos automáticamente como "Pasajero"
    $id_usuario = $pdo->lastInsertId();
    $stmt2 = $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)");
    $stmt2->execute([$id_usuario]);

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

        <label>Nombre:</label>
        <input type="text" name="nombre" placeholder="Ej: Juan" required autocomplete="given-name">

        <label>Apellido:</label>
        <input type="text" name="apellido" placeholder="Ej: Pérez" required autocomplete="family-name">

        <label>DNI:</label>
        <input type="text" name="dni" placeholder="Ej: 12345678" required pattern="[0-9]{7,9}" title="Debe contener entre 7 y 9 números" autocomplete="off">

        <label>Teléfono:</label>
        <input type="tel" name="telefono" placeholder="Ej: 3431234567" required pattern="[0-9]{8,15}" title="Ingrese un número de teléfono válido" autocomplete="tel">

        <label>Correo Electrónico:</label>
        <input type="email" name="email" placeholder="ejemplo@email.com" required autocomplete="email">

        <label>Contraseña:</label>
        <input type="password" name="password" placeholder="Mínimo 8 caracteres" minlength="8" required autocomplete="new-password">

        <button type="submit" style="width: 100%; margin-top: 15px;">Registrarse</button>

        <p style="text-align:center; margin-top: 20px;">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </p>
    </form>
</body>
</html>
