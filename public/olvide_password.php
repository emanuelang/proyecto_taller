<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/mailer.php';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT ID_usuario, Nombre FROM Usuarios WHERE Correo = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt_update = $pdo->prepare("UPDATE Usuarios SET TokenRecuperacion = ?, ExpiracionToken = ? WHERE ID_usuario = ?");
        $stmt_update->execute([$token, $expiracion, $user['ID_usuario']]);

        // Construir enlace de recuperación
        $enlace = BASE_URL . "restablecer_password.php?token=" . $token;

        $cuerpo = "
            <h2>Recuperación de Contraseña</h2>
            <p>Hola {$user['Nombre']},</p>
            <p>Has solicitado restablecer tu contraseña en Carpooling. Haz clic en el siguiente enlace para crear una nueva:</p>
            <p><a href='{$enlace}' style='display:inline-block; padding:10px 20px; background-color:#38BDF8; color:white; text-decoration:none; border-radius:5px;'>Restablecer Contraseña</a></p>
            <p>Este enlace expirará en 1 hora.</p>
            <p>Si no solicitaste esto, ignora este correo.</p>
        ";

        if (enviarCorreo($email, "Recuperación de contraseña - Carpooling", $cuerpo)) {
            $msg = "Te hemos enviado un correo con las instrucciones.";
            $msg_type = "success";
        } else {
            $msg = "Hubo un problema al enviar el correo. Intenta más tarde.";
            $msg_type = "error";
        }
    } else {
        // Por seguridad, damos el mismo mensaje si el usuario no existe (evita enumeración de usuarios)
        $msg = "Te hemos enviado un correo con las instrucciones (si la cuenta existe).";
        $msg_type = "success";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recuperar Contraseña</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div class="nav-menu">
        <h2>Recuperar Contraseña</h2>
        <a href="<?= BASE_URL ?>login.php" style="margin-left: auto;">← Volver al login</a>
    </div>

    <?php if ($msg): ?>
        <p style="color:<?= $msg_type === 'success' ? '#166534' : '#ef4444' ?>; background:<?= $msg_type === 'success' ? '#dcfce7' : '#fef2f2' ?>; border:1px solid <?= $msg_type === 'success' ? '#bbf7d0' : '#ef4444' ?>; padding:10px; border-radius:6px; text-align:center;">
            <?= htmlspecialchars($msg) ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <h3 style="margin-top:0; color:var(--primary); text-align:center;">¿Olvidaste tu contraseña?</h3>
        <p style="text-align: center; color: #64748b; font-size: 0.9em; margin-bottom: 20px;">
            Ingresa tu correo electrónico y te enviaremos un enlace para restablecerla.
        </p>

        <label>Correo Electrónico:</label>
        <input name="email" type="email" required placeholder="tu@email.com">
        
        <button type="submit" style="width: 100%; margin-top: 15px;">Enviar Enlace</button>
    </form>
</body>
</html>
