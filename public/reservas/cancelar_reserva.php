<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserva_id'])) {

    $reserva_id = (int) $_POST['reserva_id'];
    $usuario_id = $_SESSION['user_id'];

    $sql = "UPDATE Reservas r
            JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
            JOIN Pasajeros p ON pr.ID_pasajero = p.ID_pasajero
            SET r.Estado = 'Cancelada'
            WHERE r.ID_reserva = ? AND p.ID_usuario = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reserva_id, $usuario_id]);
}

header("Location: mis_reservas.php");
exit;
