<?php
session_start();
require_once __DIR__ . '/../core/storage.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM Usuarios WHERE Correo = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['Contraseña'])) {
        $_SESSION['user_id'] = $user['ID_usuario'];
        $_SESSION['nombre'] = $user['Nombre'];

        $stmt2 = $pdo->prepare("SELECT ID_conductor FROM Conductores WHERE ID_usuario = ? AND Estado = 'Aceptada'");
        $stmt2->execute([$user['ID_usuario']]);
        $cond = $stmt2->fetch();

        if ($cond) {
            $_SESSION['is_conductor'] = true;
            $_SESSION['conductor_id'] = $cond['ID_conductor'];
        } else {
            $_SESSION['is_conductor'] = false;
            unset($_SESSION['conductor_id']);
        }

        header('Location: ' . BASE_URL . 'index.php');
        exit;
    } else {
        $error = 'Credenciales incorrectas';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div class="nav-menu">
        <h2>Iniciar Sesión</h2>
        <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Volver al inicio</a>
    </div>

    <?php if ($error): ?>
        <p style="color:#ef4444; background:#fef2f2; border:1px solid #ef4444; padding:10px; border-radius:6px; text-align:center;">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <h3 style="margin-top:0; color:var(--primary); text-align:center;">Bienvenido de nuevo</h3>
        
        <label>Correo Electrónico:</label>
        <input name="email" type="email" required placeholder="tu@email.com">
        
        <label>Contraseña:</label>
        <input name="password" type="password" required placeholder="••••••••">
        
        <button type="submit" style="width: 100%; margin-top: 15px;">Ingresar</button>
        
        <p style="text-align:center; margin-top: 20px;">
            ¿No tienes cuenta? <a href="<?= BASE_URL ?>registro_usuario.php">Regístrate aquí</a>
        </p>
    </form>
</body>
</html>
