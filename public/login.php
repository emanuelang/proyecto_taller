<?php
session_start();
require_once __DIR__ . '/../core/storage.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$error = '';
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Tu sesion se cerro por inactividad.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Token CSRF invalido.');
    }

    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare('SELECT * FROM Usuarios WHERE Correo = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['Contraseña'])) {
        if (isset($user['Estado']) && $user['Estado'] === 'Inactivo') {
            $error = 'Esta cuenta ha sido desactivada.';
        } elseif (!empty($user['BaneadoHasta']) && strtotime($user['BaneadoHasta']) > time()) {
            $error = 'Tu cuenta esta suspendida hasta el ' . date('d/m/Y H:i', strtotime($user['BaneadoHasta']));
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['ID_usuario'];
            $_SESSION['nombre'] = $user['Nombre'];
            $_SESSION['last_activity'] = time();

            $stmt2 = $pdo->prepare("SELECT ID_conductor, BaneadoHasta FROM Conductores WHERE ID_usuario = ? AND Estado = 'Aceptada'");
            $stmt2->execute([$user['ID_usuario']]);
            $cond = $stmt2->fetch();

            if ($cond) {
                if (!empty($cond['BaneadoHasta']) && strtotime($cond['BaneadoHasta']) > time()) {
                    $_SESSION['is_conductor'] = false;
                    unset($_SESSION['conductor_id']);
                } else {
                    $_SESSION['is_conductor'] = true;
                    $_SESSION['conductor_id'] = $cond['ID_conductor'];
                }
            } else {
                $_SESSION['is_conductor'] = false;
                unset($_SESSION['conductor_id']);
            }

            $stmt_admin = $pdo->prepare('SELECT ID_administrador FROM Administradores WHERE ID_usuario = ?');
            $stmt_admin->execute([$user['ID_usuario']]);
            $_SESSION['is_admin'] = (bool) $stmt_admin->fetch();

            header('Location: ' . BASE_URL . 'index.php');
            exit;
        }
    } else {
        $error = 'Credenciales incorrectas';
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Iniciar sesion - MOVEON</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= filemtime(__DIR__ . '/main.css') ?>">
    <script src="<?= BASE_URL ?>main.js?v=<?= time() ?>"></script>
</head>
<body class="auth-body">
    <main class="auth-shell">
        <div class="auth-topbar">
            <a class="auth-brand" href="<?= BASE_URL ?>index.php">
                <img src="<?= BASE_URL ?>assets/moveon-logo.svg" alt="MOVEON">
                <span>MOVEON</span>
            </a>
            <a href="<?= BASE_URL ?>index.php">&larr; Volver</a>
        </div>

        <section class="auth-card">
            <h1>Iniciar sesion</h1>
            <p class="page-subtitle">Bienvenido de nuevo. Ingresa para gestionar tus viajes.</p>

            <?php if ($error): ?>
                <div class="alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <label>Correo electronico</label>
                <input name="email" type="email" required placeholder="tu@email.com" minlength="5" maxlength="254" autocomplete="email">

                <label>Contrasena</label>
                <input name="password" type="password" required placeholder="........" minlength="8" maxlength="72" autocomplete="current-password">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="auth-link-row">
                    <a href="<?= BASE_URL ?>olvide_password.php">Olvidaste tu contrasena?</a>
                </div>

                <button type="submit">Ingresar</button>

                <p class="auth-footer">
                    No tenes cuenta? <a href="<?= BASE_URL ?>registro_usuario.php">Registrate aqui</a>
                </p>
            </form>
        </section>
    </main>
</body>
</html>
