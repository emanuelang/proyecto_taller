<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    $stmt = $pdo->prepare("UPDATE Notificaciones SET Leida = TRUE WHERE ID_usuario = ? AND Leida = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid method']);
}
