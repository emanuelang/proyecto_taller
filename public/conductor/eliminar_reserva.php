<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

    $reserva_id = (int)$_GET['id'];
    $viaje_id = (int)$_GET['viaje'];

    // Notificar al pasajero
    $stmt_info = $pdo->prepare("
        SELECT pr.ID_pasajero, pas.ID_usuario, p.CiudadOrigen, p.CiudadDestino 
        FROM Reservas r 
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva 
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero 
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion 
        WHERE r.ID_reserva = ?
    ");
    $stmt_info->execute([$reserva_id]);
    $info = $stmt_info->fetch();

    if ($info) {
        $msg = "El conductor te ha expulsado del viaje de {$info['CiudadOrigen']} a {$info['CiudadDestino']}.";
        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$info['ID_usuario'], $msg]);
    }

    $stmt = $pdo->prepare("DELETE FROM Reservas WHERE ID_reserva = ?");
    $stmt->execute([$reserva_id]);

header("Location: ver_reservas.php?id=$viaje_id");
exit;
