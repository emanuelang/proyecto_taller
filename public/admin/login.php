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

    $stmt = $pdo->prepare("
        SELECT u.*, a.ID_administrador 
        FROM Usuarios u 
        JOIN Administradores a ON u.ID_usuario = a.ID_usuario 
        WHERE u.Correo = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['Contraseña'])) {
        
        $_SESSION['user_id'] = $user['ID_usuario'];
        $_SESSION['nombre'] = $user['Nombre'];
        $_SESSION['is_admin'] = true; 
        
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
