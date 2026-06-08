<?php
session_start();
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";
require_once __DIR__ . "/../../core/security.php";
require_once __DIR__ . "/../../core/session_guard.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

require_csrf();

$reserva_id = (int)($_POST['reserva_id'] ?? 0);
$usuario_id = (int)$_SESSION['user_id'];

if ($reserva_id <= 0) {
    $_SESSION['mensaje_exito'] = "No se pudo quitar la reserva del historial.";
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT pr.ID_pasajero, r.ID_reserva, p.ID_publicacion, p.CiudadOrigen, p.CiudadDestino
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
    WHERE r.ID_reserva = ?
      AND pas.ID_usuario = ?
      AND r.Estado = 'Completada'
      AND p.HoraSalida < NOW()
    LIMIT 1
");
$stmt->execute([$reserva_id, $usuario_id]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    $_SESSION['mensaje_exito'] = "No se pudo quitar la reserva del historial.";
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt_delete = $pdo->prepare("DELETE FROM PasajerosReservas WHERE ID_pasajero = ? AND ID_reserva = ?");
    $stmt_delete->execute([(int)$reserva['ID_pasajero'], $reserva_id]);

    $base_path = parse_url(BASE_URL, PHP_URL_PATH) ?: BASE_URL;
    $base_path = rtrim($base_path, '/') . '/';
    $confirmar_urls = [
        BASE_URL . 'reservas/confirmar_llegada.php?reserva_id=' . $reserva_id,
        $base_path . 'reservas/confirmar_llegada.php?reserva_id=' . $reserva_id,
    ];
    $calificar_urls = [
        BASE_URL . 'reservas/calificar.php?reserva_id=' . $reserva_id,
        $base_path . 'reservas/calificar.php?reserva_id=' . $reserva_id,
    ];
    $stmt_notif = $pdo->prepare("
        DELETE FROM Notificaciones
        WHERE ID_usuario = ?
          AND (
              AccionURL IN (?, ?, ?, ?)
              OR AccionSecundariaURL LIKE ?
              OR AccionSecundariaURL LIKE ?
          )
    ");
    $stmt_notif->execute([
        $usuario_id,
        $confirmar_urls[0],
        $confirmar_urls[1],
        $calificar_urls[0],
        $calificar_urls[1],
        '%publicacion_id=' . (int)$reserva['ID_publicacion'] . '%',
        '%publicacion_id=' . (int)$reserva['ID_publicacion'] . '%',
    ]);

    $pdo->commit();
    $_SESSION['mensaje_exito'] = "Reserva quitada del historial.";
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error al quitar reserva del historial: " . $e->getMessage());
    $_SESSION['mensaje_exito'] = "No se pudo quitar la reserva del historial.";
}

header("Location: " . BASE_URL . "reservas/historial_viajes.php");
exit;
