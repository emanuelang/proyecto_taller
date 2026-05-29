<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/session_guard.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

$reserva_id = (int)($_GET['reserva_id'] ?? 0);
if ($reserva_id <= 0) {
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.ID_reserva, r.ID_publicacion, r.Estado, p.HoraSalida, p.CiudadOrigen, p.CiudadDestino,
           cp.ID_conductor
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE r.ID_reserva = ?
      AND pas.ID_usuario = ?
      AND r.Estado = 'Completada'
      AND p.HoraSalida < NOW()
    LIMIT 1
");
$stmt->execute([$reserva_id, (int)$_SESSION['user_id']]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

$stmt_confirm = $pdo->prepare("
    INSERT INTO ConfirmacionesViaje (ID_reserva, ID_usuario, ID_publicacion, ConfirmoLlegada)
    VALUES (?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        ConfirmoLlegada = VALUES(ConfirmoLlegada),
        FechaConfirmacion = CURRENT_TIMESTAMP
");
$stmt_confirm->execute([$reserva_id, (int)$_SESSION['user_id'], (int)$reserva['ID_publicacion']]);

$fecha = date('d/m/Y H:i', strtotime($reserva['HoraSalida']));
$confirmar_url = BASE_URL . 'reservas/confirmar_llegada.php?reserva_id=' . $reserva_id;
$calificar_url = BASE_URL . 'reservas/calificar.php?reserva_id=' . $reserva_id;
$report_url = BASE_URL . 'reportar.php?conductor_id=' . (int)$reserva['ID_conductor'] . '&publicacion_id=' . (int)$reserva['ID_publicacion'];

$stmt_calificada = $pdo->prepare("SELECT COUNT(*) FROM Calificaciones WHERE ID_reserva = ?");
$stmt_calificada->execute([$reserva_id]);
if ((int)$stmt_calificada->fetchColumn() > 0) {
    $mensaje = "Tu llegada al viaje de {$reserva['CiudadOrigen']} a {$reserva['CiudadDestino']} del {$fecha} ya fue confirmada y la calificacion ya fue enviada.";
    $stmt_notif = $pdo->prepare("
        UPDATE Notificaciones
        SET Mensaje = ?, AccionURL = NULL, AccionLabel = NULL, AccionSecundariaURL = ?, AccionSecundariaLabel = ?, Leida = FALSE, Fecha = CURRENT_TIMESTAMP
        WHERE ID_usuario = ?
          AND AccionURL IN (?, ?)
    ");
    $stmt_notif->execute([$mensaje, $report_url, 'Reportar conductor', (int)$_SESSION['user_id'], $confirmar_url, $calificar_url]);

    $_SESSION['mensaje_exito'] = 'Llegada confirmada. Este viaje ya tenia una calificacion registrada.';
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

$mensaje = "Tu llegada al viaje de {$reserva['CiudadOrigen']} a {$reserva['CiudadDestino']} del {$fecha} ya fue confirmada. Ahora podes calificar al conductor.";
$stmt_notif = $pdo->prepare("
    UPDATE Notificaciones
    SET Mensaje = ?, AccionURL = ?, AccionLabel = ?, AccionSecundariaURL = ?, AccionSecundariaLabel = ?, Leida = FALSE, Fecha = CURRENT_TIMESTAMP
    WHERE ID_usuario = ?
      AND AccionURL = ?
");
$stmt_notif->execute([$mensaje, $calificar_url, 'Calificar conductor', $report_url, 'Reportar conductor', (int)$_SESSION['user_id'], $confirmar_url]);

header("Location: " . BASE_URL . "reservas/calificar.php?reserva_id=" . $reserva_id);
exit;
