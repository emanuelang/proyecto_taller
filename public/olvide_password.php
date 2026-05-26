<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/mailer.php';
require_once __DIR__ . '/../core/security.php';

$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("SELECT ID_usuario, Nombre FROM Usuarios WHERE Correo = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expiracion = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt_update = $pdo->prepare("UPDATE Usuarios SET TokenRecuperacion = ?, ExpiracionToken = ? WHERE ID_usuario = ?");
        $stmt_update->execute([$token, $expiracion, $user['ID_usuario']]);

        $enlace = BASE_URL . "restablecer_password.php?token=" . $token;
        $nombre = htmlspecialchars($user['Nombre'], ENT_QUOTES, 'UTF-8');
        $enlace_html = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

        $cuerpo = "
            <h2>Recuperacion de contrasena</h2>
            <p>Hola {$nombre},</p>
            <p>Solicitaste restablecer tu contrasena en MOVEON. Usa el siguiente boton para crear una nueva:</p>
            <p><a href='{$enlace_html}' style='display:inline-block; padding:10px 20px; background-color:#2563eb; color:white; text-decoration:none; border-radius:8px;'>Restablecer contrasena</a></p>
            <p>Este enlace expira en 1 hora.</p>
            <p>Si no solicitaste esto, ignora este correo.</p>
        ";

        if (enviarCorreo($email, "Recuperacion de contrasena - MOVEON", $cuerpo)) {
            $msg = "Te enviamos un correo con las instrucciones.";
            $msg_type = "success";
        } else {
            error_log("No se pudo enviar correo de recuperacion. Enlace generado: " . $enlace);
            $msg = "No pudimos enviar el correo en este momento. Intentalo nuevamente mas tarde.";
            $msg_type = "error";
        }
    } else {
        $msg = "Te enviamos un correo con las instrucciones si la cuenta existe.";
        $msg_type = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contrasena - MOVEON</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>assets/moveon-favicon.svg">
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
            <a href="<?= BASE_URL ?>login.php">&larr; Volver al login</a>
        </div>

        <section class="auth-card">
            <h1>Recuperar contrasena</h1>
            <p class="page-subtitle">Ingresa tu correo y te enviamos un enlace para crear una nueva.</p>

            <?php if ($msg): ?>
                <div class="<?= $msg_type === 'success' ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <?= csrf_field() ?>
                <label>Correo electronico</label>
                <input name="email" type="email" required placeholder="tu@email.com" minlength="5" maxlength="254" autocomplete="email">

                <button type="submit">Enviar enlace</button>
            </form>
        </section>
    </main>
</body>
</html>
