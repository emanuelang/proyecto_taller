<?php
session_start();
// Ajustamos las rutas considerando que estamos en public/admin/
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Si ya está logueado y es admin, lo redirigimos al dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    // Buscamos al usuario por email y nos aseguramos de que tenga rol 'admin'
    // IMPORTANTE: Deberás tener la columna 'rol' en tu tabla 'usuarios'
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND rol = 'admin'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verificamos si existe el usuario y si el estado no es suspendido o baneado
    if ($user && password_verify($pass, $user['password'])) {
        
        // OPCIONAL: Verificar aquí si el usuario está baneado/suspendido
        // Si tienes la columna 'estado', podrías hacer:
        // if ($user['estado'] !== 'activo') { $error = "Cuenta suspendida."; } else { ... }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['is_admin'] = true; // Flag importante para proteger otras rutas
        
        // Limpiamos flags de conductor por precaución para la sesión admin
        $_SESSION['is_conductor'] = false;
        unset($_SESSION['conductor_id']);

        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Credenciales incorrectas o no tienes permisos de administrador.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Login Administrador - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
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
        <input name="email" type="email" required placeholder="Email Administrativo">
        <input name="password" type="password" required placeholder="Contraseña">
        <button type="submit">Ingresar al Panel</button>
    </form>
    
    <div style="text-align: center; margin-top: 15px;">
        <a href="../index.php">← Volver al sitio público</a>
    </div>
</div>

</body>
</html>
