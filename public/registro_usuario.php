<?php
session_start();
require_once '../config/database.php';
require_once '../core/security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $dni = trim($_POST['dni']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $raw_password = $_POST['password'];
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    
    $errores = [];
    if (strlen($nombre) > 100) $errores[] = "El nombre es demasiado largo.";
    if (strlen($apellido) > 100) $errores[] = "El apellido es demasiado largo.";
    if (!preg_match('/^[0-9]{7,8}$/', $dni)) $errores[] = "El DNI debe tener 7 u 8 dígitos numéricos.";
    if (!preg_match('/^[0-9]{8,15}$/', $telefono)) $errores[] = "El teléfono debe tener entre 8 y 15 dígitos numéricos.";
    if (strlen($raw_password) < 8 || strlen($raw_password) > 72) $errores[] = "La contraseña debe tener entre 8 y 72 caracteres.";
    
    if (empty($errores)) {
        try {
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
        } catch (PDOException $e) {
            // Error 1062 es Duplicate entry
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                if (strpos($e->getMessage(), 'Correo') !== false || strpos($e->getMessage(), 'email') !== false) {
                    $errores[] = "Este mail ya está registrado.";
                } else if (strpos($e->getMessage(), 'DNI') !== false) {
                    $errores[] = "Este DNI ya está registrado.";
                } else {
                    $errores[] = "Error de duplicación en la base de datos.";
                }
            } else {
                $errores[] = "Ocurrió un error al registrar el usuario.";
            }
        }
    }
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
        <?= csrf_field() ?>
        <h3 style="margin-top:0; color:var(--primary); text-align:center;">Únete a Carpooling</h3>

        <?php if (!empty($errores)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <label>Nombre:</label>
        <input type="text"  name="nombre" placeholder="Ej: Juan" required autocomplete="given-name" minlength="2" maxlength="50">

        <label>Apellido:</label>
        <input type="text"  name="apellido" placeholder="Ej: Pérez" required autocomplete="family-name" minlength="2" maxlength="50">

        <label>DNI:</label>
        <input type="text"  name="dni" placeholder="Ej: 12345678" required pattern="[0-9]{7,8}" title="Debe contener 7 u 8 números" autocomplete="off" minlength="7" maxlength="8">

        <label>Teléfono:</label>
        <input type="tel"  name="telefono" placeholder="Ej: 3431234567" required pattern="[0-9]{8,15}" title="Ingrese un número de teléfono válido" autocomplete="tel" minlength="10" maxlength="15">

        <label>Correo Electrónico:</label>
        <input type="email"  name="email" placeholder="ejemplo@email.com" required autocomplete="email" minlength="5" maxlength="254">

        <label>Contraseña:</label>
        <input type="password"  name="password" placeholder="Mínimo 8 caracteres" required autocomplete="new-password" minlength="8" maxlength="72">

        <button type="submit" style="width: 100%; margin-top: 15px;">Registrarse</button>

        <p style="text-align:center; margin-top: 20px;">
            ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
        </p>
    </form>
</body>
</html>
