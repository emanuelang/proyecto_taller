<?php
session_start();
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserva_id'])) {

    $reserva_id = (int) $_POST['reserva_id'];
    $usuario_id = $_SESSION['user_id'];

    /* Verificar que la reserva pertenece al usuario y está en estado cancelable */
    $sql = "
        UPDATE Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros p ON pr.ID_pasajero = p.ID_pasajero
        SET r.Estado = 'Cancelada'
        WHERE r.ID_reserva = ?
          AND p.ID_usuario = ?
          AND r.Estado IN ('Pendiente', 'Aceptada')
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reserva_id, $usuario_id]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['mensaje_cancelacion'] = "Reserva cancelada correctamente.";
    } else {
        $_SESSION['mensaje_cancelacion'] = "No se pudo cancelar la reserva (ya fue cancelada o no te pertenece).";
    }
}

header("Location: mis_reservas.php");
exit;
