<?php
session_start();
// Ajustamos las rutas considerando que estamos en public/admin/
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

// Si ya está logueado y es admin, lo redirigimos al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Tu sesion se cerro por inactividad.';
} elseif (isset($_GET['inactive']) && $_GET['inactive'] === '1') {
    $error = 'Tu cuenta esta desactivada. No podes ingresar al panel.';
} elseif (isset($_GET['banned']) && $_GET['banned'] === '1') {
    $error = 'Tu cuenta esta suspendida temporalmente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("
        SELECT u.*, a.ID_administrador 
        FROM Usuarios u 
        JOIN Administradores a ON u.ID_usuario = a.ID_usuario 
        WHERE u.Correo = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['Contraseña'])) {
        if (($user['estado'] ?? 'activo') !== 'activo') {
            $error = 'Esta cuenta ha sido desactivada o suspendida.';
        } elseif (!empty($user['BaneadoHasta']) && strtotime($user['BaneadoHasta']) > time()) {
            $error = 'Tu cuenta esta suspendida hasta el ' . date('d/m/Y H:i', strtotime($user['BaneadoHasta']));
        } else {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['ID_usuario'];
        $_SESSION['nombre'] = $user['Nombre'];
        $_SESSION['is_admin'] = true; 
        $_SESSION['last_activity'] = time();
        
        // Cargar también si es conductor para no pisar el panel de conductor:
        $stmt2 = $pdo->prepare("SELECT ID_conductor, BaneadoHasta FROM Conductores WHERE ID_usuario = ? AND Estado = 'Aceptada'");
        $stmt2->execute([$user['ID_usuario']]);
        $cond = $stmt2->fetch();

        if ($cond) {
            if ($cond['BaneadoHasta'] && strtotime($cond['BaneadoHasta']) > time()) {
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

        header('Location: dashboard.php');
        exit;
        }
    } else {
        $error = 'Credenciales incorrectas o no tienes permisos de administrador.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Administrador - Carpooling</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/moveon-favicon.svg">
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= filemtime(__DIR__ . '/../main.css') ?>">
    <script src="<?= BASE_URL ?>main.js?v=<?= time() ?>"></script>
    <style>
        .admin-login-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .admin-login-container h2 {
            text-align: center;
            color: #333;
        }
        .admin-login-container input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            box-sizing: border-box;
        }
        .admin-login-container button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .admin-login-container button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<div class="admin-login-container">
    <h2>Panel de Administración</h2>
    <?php if ($error): ?><p style="color:red; text-align:center;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    
    <form method="post">
        <?= csrf_field() ?>
        <input  name="email" type="email" required placeholder="Email Administrativo" minlength="5" maxlength="254">
        <div class="password-field">
            <input  name="password" type="password" required placeholder="Contraseña" minlength="8" maxlength="72" autocomplete="current-password">
            <button class="password-toggle" type="button" aria-label="Mostrar contrasena" aria-pressed="false">
                <span class="password-eye password-eye-open" aria-hidden="true"></span>
                <span class="password-eye password-eye-closed" aria-hidden="true"></span>
            </button>
        </div>
        <button type="submit">Ingresar al Panel</button>
    </form>
    
    <div style="text-align: center; margin-top: 15px;">
        <a href="../index.php">← Volver al sitio público</a>
    </div>
</div>

</body>
</html>
