<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    DELETE FROM viajes
    WHERE id = ? AND conductor_id = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);

header('Location: viajes.php');
exit;
