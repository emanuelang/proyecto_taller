<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asunto = trim($_POST['asunto']);
    $mensaje = trim($_POST['mensaje']);
    $errores = [];

    if (empty($asunto)) $errores[] = "El asunto es obligatorio.";
    if (empty($mensaje)) $errores[] = "El mensaje es obligatorio.";

    if (empty($errores)) {
        $stmt = $pdo->prepare("INSERT INTO Soporte (ID_usuario, Asunto, Mensaje) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $asunto, $mensaje]);
        $msg_exito = "Tu mensaje ha sido enviado correctamente al equipo de soporte. Te contactaremos pronto.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Soporte Técnico - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .soporte-container { max-width: 600px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .soporte-container h2 { color: var(--primary); margin-top: 0; }
    </style>
</head>
<body style="background-color: #f1f5f9;">

<?php include __DIR__ . '/header.php'; ?>

<div class="soporte-container">
    <h2>Centro de Soporte</h2>
    <p>¿Tuviste algún problema con la plataforma o tu cuenta? Cuéntanos y te ayudaremos a resolverlo lo antes posible.</p>

    <?php if (isset($msg_exito)): ?>
        <div style="background-color: #dcfce7; color: #166534; padding: 15px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
            ✅ <?= htmlspecialchars($msg_exito) ?>
        </div>
        <a href="<?= BASE_URL ?>index.php" class="btn" style="display: block; text-align: center;">Volver al Inicio</a>
    <?php else: ?>
        <?php if (!empty($errores)): ?>
            <div style="background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST">
            <label>Asunto del problema:</label>
            <input type="text"  name="asunto" placeholder="Ej: Problemas para publicar un viaje, Error en pagos, etc." required  minlength="5" maxlength="100">

            <label>Descripción detallada:</label>
            <textarea  name="mensaje" rows="6" placeholder="Describe el problema con el mayor detalle posible para que podamos ayudarte mejor..." required minlength="20" maxlength="2000"></textarea>

            <button type="submit" class="btn success-bg" style="width: 100%; margin-top: 15px; font-size: 1.1em; padding: 12px;">Enviar Mensaje a Soporte</button>
        </form>
    <?php endif; ?>
    <div style="text-align: center; margin-top: 20px;">
        <a href="<?= BASE_URL ?>manual.php" style="color: var(--primary);">← Volver a la Ayuda</a>
    </div>
</div>

</body>
</html>
