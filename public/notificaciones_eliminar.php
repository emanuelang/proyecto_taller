<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

require_active_session($pdo);
require_csrf();

$notification_id = (int)($_POST['notification_id'] ?? 0);
if ($notification_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM Notificaciones WHERE ID_notificacion = ? AND ID_usuario = ?");
$stmt->execute([$notification_id, (int)$_SESSION['user_id']]);

echo json_encode(['ok' => true]);
