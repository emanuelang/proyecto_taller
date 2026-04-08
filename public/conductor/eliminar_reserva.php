<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$reserva_id = (int)$_GET['id'];
$viaje_id = (int)$_GET['viaje'];

$stmt = $pdo->prepare("DELETE FROM Reservas WHERE ID_reserva = ?");
$stmt->execute([$reserva_id]);

header("Location: ver_reservas.php?id=$viaje_id");
exit;
