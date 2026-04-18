<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE Notificaciones SET Leida = TRUE WHERE ID_usuario = ? AND Leida = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid method']);
}
