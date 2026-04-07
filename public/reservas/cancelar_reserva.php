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

    /* El usuario quiere eliminar la reserva completamente de la base de datos */
    $stmt_p = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
    $stmt_p->execute([$usuario_id]);
    $pas = $stmt_p->fetch();

    if ($pas) {
        $check = $pdo->prepare("SELECT 1 FROM PasajerosReservas WHERE ID_reserva = ? AND ID_pasajero = ?");
        $check->execute([$reserva_id, $pas['ID_pasajero']]);
        
        if ($check->fetch()) {
            $stmt = $pdo->prepare("DELETE FROM Reservas WHERE ID_reserva = ?");
            $stmt->execute([$reserva_id]);
            $_SESSION['mensaje_cancelacion'] = "Has cancelado y eliminado la reserva del viaje exitosamente.";
        } else {
            $_SESSION['mensaje_cancelacion'] = "No se pudo eliminar la reserva (ya fue eliminada o no te pertenece).";
        }
    } else {
        $_SESSION['mensaje_cancelacion'] = "No estás registrado como pasajero.";
    }
}

header("Location: mis_reservas.php");
exit;
