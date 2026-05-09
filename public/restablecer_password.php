<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$token = $_GET['token'] ?? '';
$msg = '';
$msg_type = '';

if (!$token) {
    die("Token inválido.");
}

// Verificar token
$stmt = $pdo->prepare("SELECT ID_usuario FROM Usuarios WHERE TokenRecuperacion = ? AND ExpiracionToken > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("El token es inválido o ha expirado. Por favor solicita uno nuevo.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if (strlen($pass1) < 6) {
        $msg = "La contraseña debe tener al menos 6 caracteres.";
        $msg_type = "error";
    } elseif ($pass1 !== $pass2) {
        $msg = "Las contraseñas no coinciden.";
        $msg_type = "error";
    } else {
        $hashed = password_hash($pass1, PASSWORD_DEFAULT);
        
        $stmt_update = $pdo->prepare("UPDATE Usuarios SET Contraseña = ?, TokenRecuperacion = NULL, ExpiracionToken = NULL WHERE ID_usuario = ?");
        $stmt_update->execute([$hashed, $user['ID_usuario']]);

        $msg = "¡Tu contraseña ha sido actualizada con éxito!";
        $msg_type = "success";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nueva Contraseña</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div class="nav-menu">
        <h2>Crear Nueva Contraseña</h2>
        <a href="<?= BASE_URL ?>login.php" style="margin-left: auto;">← Ir al login</a>
    </div>

    <?php if ($msg): ?>
        <p style="color:<?= $msg_type === 'success' ? '#166534' : '#ef4444' ?>; background:<?= $msg_type === 'success' ? '#dcfce7' : '#fef2f2' ?>; border:1px solid <?= $msg_type === 'success' ? '#bbf7d0' : '#ef4444' ?>; padding:10px; border-radius:6px; text-align:center;">
            <?= htmlspecialchars($msg) ?>
        </p>
    <?php endif; ?>

    <?php if ($msg_type !== 'success'): ?>
    <form method="post">
        <label>Nueva Contraseña:</label>
        <input name="pass1" type="password" required placeholder="Al menos 6 caracteres">
        
        <label>Confirmar Nueva Contraseña:</label>
        <input name="pass2" type="password" required placeholder="Repite la contraseña">
        
        <button type="submit" style="width: 100%; margin-top: 15px;">Guardar Contraseña</button>
    </form>
    <?php else: ?>
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?= BASE_URL ?>login.php" class="btn">Iniciar Sesión</a>
        </div>
    <?php endif; ?>
</body>
</html>
