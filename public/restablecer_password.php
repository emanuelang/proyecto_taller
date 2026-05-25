<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';

$token = $_GET['token'] ?? '';
$msg = '';
$msg_type = '';

if (!$token) {
    die("Token invalido.");
}

$stmt = $pdo->prepare("SELECT ID_usuario FROM Usuarios WHERE TokenRecuperacion = ? AND ExpiracionToken > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("El token es invalido o ha expirado. Por favor solicita uno nuevo.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if (strlen($pass1) < 8 || strlen($pass1) > 72) {
        $msg = "La contrasena debe tener entre 8 y 72 caracteres.";
        $msg_type = "error";
    } elseif ($pass1 !== $pass2) {
        $msg = "Las contrasenas no coinciden.";
        $msg_type = "error";
    } else {
        $hashed = password_hash($pass1, PASSWORD_DEFAULT);

        $stmt_update = $pdo->prepare("UPDATE Usuarios SET `Contraseña` = ?, TokenRecuperacion = NULL, ExpiracionToken = NULL WHERE ID_usuario = ?");
        $stmt_update->execute([$hashed, $user['ID_usuario']]);

        $msg = "Tu contrasena fue actualizada con exito.";
        $msg_type = "success";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva contrasena - MOVEON</title>
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
            <a href="<?= BASE_URL ?>login.php">&larr; Ir al login</a>
        </div>

        <section class="auth-card">
            <h1>Crear nueva contrasena</h1>
            <p class="page-subtitle">Elegi una clave nueva para volver a ingresar a MOVEON.</p>

            <?php if ($msg): ?>
                <div class="<?= $msg_type === 'success' ? 'alert-success' : 'alert-error' ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($msg_type !== 'success'): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <label>Nueva contrasena</label>
                    <div class="password-field">
                        <input name="pass1" type="password" required placeholder="Minimo 8 caracteres" minlength="8" maxlength="72" autocomplete="new-password">
                        <button class="password-toggle" type="button" aria-label="Mostrar contrasena" aria-pressed="false">
                            <span class="password-eye password-eye-open" aria-hidden="true"></span>
                            <span class="password-eye password-eye-closed" aria-hidden="true"></span>
                        </button>
                    </div>

                    <label>Confirmar nueva contrasena</label>
                    <div class="password-field">
                        <input name="pass2" type="password" required placeholder="Repeti la contrasena" minlength="8" maxlength="72" autocomplete="new-password">
                        <button class="password-toggle" type="button" aria-label="Mostrar contrasena" aria-pressed="false">
                            <span class="password-eye password-eye-open" aria-hidden="true"></span>
                            <span class="password-eye password-eye-closed" aria-hidden="true"></span>
                        </button>
                    </div>

                    <button type="submit">Guardar contrasena</button>
                </form>
            <?php else: ?>
                <a href="<?= BASE_URL ?>login.php" class="btn" style="width:100%;">Iniciar sesion</a>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
