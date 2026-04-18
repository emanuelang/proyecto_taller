<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener todas las notificaciones
$stmt = $pdo->prepare("SELECT * FROM Notificaciones WHERE ID_usuario = ? ORDER BY Fecha DESC LIMIT 50");
$stmt->execute([$user_id]);
$notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marcar todas como leídas al entrar
$stmt_upd = $pdo->prepare("UPDATE Notificaciones SET Leida = TRUE WHERE ID_usuario = ? AND Leida = FALSE");
$stmt_upd->execute([$user_id]);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mis Notificaciones - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= time() ?>">
</head>
<body style="background-color: #f1f5f9;">

<div class="nav-menu">
    <h2>Notificaciones</h2>
    <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Volver al inicio</a>
</div>

<div class="card" style="max-width: 800px; margin: 40px auto; padding: 20px;">
    <?php if (empty($notificaciones)): ?>
        <p style="text-align: center; color: #64748b; font-size: 1.1em; padding: 40px 0;">No tenés ninguna notificación reciente.</p>
    <?php else: ?>
        <?php foreach ($notificaciones as $n): ?>
            <div style="background-color: <?= $n['Leida'] ? '#f8fafc' : '#ffffff' ?>; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid <?= $n['Leida'] ? '#cbd5e1' : '#3b82f6' ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 5px;">
                <span style="font-size: 0.85em; color: #64748b; font-weight: bold;"><?= date('d/m/Y H:i', strtotime($n['Fecha'])) ?></span>
                <span style="color: #334155; font-size: 1.05em;"><?= htmlspecialchars($n['Mensaje']) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
