<?php
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";
require_once __DIR__ . "/../../core/security.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Solicitud invalida']);
    exit;
}

$csrfToken = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
if (!verify_csrf_token($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solicitud invalida. Volve a cargar la pagina.']);
    exit;
}

if (!isset($data['reserva_id']) || !isset($data['puntuacion'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$reserva_id = (int)$data['reserva_id'];
$puntuacion = (int)$data['puntuacion'];

if ($reserva_id <= 0 || $puntuacion < 1 || $puntuacion > 5) {
    echo json_encode(['success' => false, 'message' => 'Datos invalidos']);
    exit;
}

$usuario_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT pr.ID_pasajero, r.ID_publicacion, p.HoraSalida, p.CiudadOrigen, p.CiudadDestino,
               cp.ID_conductor, c.ID_usuario AS conductor_usuario_id
        FROM PasajerosReservas pr
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Reservas r ON pr.ID_reserva = r.ID_reserva
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON r.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        WHERE r.ID_reserva = ? AND pas.ID_usuario = ?
    ");
    $stmt->execute([$reserva_id, $usuario_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'No tienes permiso para calificar este viaje']);
        exit;
    }

    if ($usuario_id == $res['conductor_usuario_id']) {
        echo json_encode(['success' => false, 'message' => 'No puedes calificarte a ti mismo']);
        exit;
    }

    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Calificaciones WHERE ID_reserva = ? AND ID_pasajero = ?");
    $stmt_check->execute([$reserva_id, $res['ID_pasajero']]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya calificaste este viaje']);
        exit;
    }

    $stmt_insert = $pdo->prepare("
        INSERT INTO Calificaciones (Puntuacion, ID_pasajero, ID_conductor, ID_reserva)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_insert->execute([$puntuacion, $res['ID_pasajero'], $res['ID_conductor'], $reserva_id]);

    $stmt_confirm = $pdo->prepare("
        INSERT INTO ConfirmacionesViaje (ID_reserva, ID_usuario, ID_publicacion, ConfirmoLlegada)
        VALUES (?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            ConfirmoLlegada = VALUES(ConfirmoLlegada),
            FechaConfirmacion = CURRENT_TIMESTAMP
    ");
    $stmt_confirm->execute([$reserva_id, (int)$usuario_id, (int)$res['ID_publicacion']]);

    $fecha = date('d/m/Y H:i', strtotime($res['HoraSalida']));
    $mensaje = "Tu llegada al viaje de {$res['CiudadOrigen']} a {$res['CiudadDestino']} del {$fecha} ya fue confirmada y la calificacion ya fue enviada.";
    $confirmar_url = BASE_URL . 'reservas/confirmar_llegada.php?reserva_id=' . $reserva_id;
    $calificar_url = BASE_URL . 'reservas/calificar.php?reserva_id=' . $reserva_id;
    $report_url = BASE_URL . 'reportar.php?conductor_id=' . (int)$res['ID_conductor'] . '&publicacion_id=' . (int)$res['ID_publicacion'];
    $stmt_notif = $pdo->prepare("
        UPDATE Notificaciones
        SET Mensaje = ?, AccionURL = NULL, AccionLabel = NULL, AccionSecundariaURL = ?, AccionSecundariaLabel = ?, Leida = FALSE, Fecha = CURRENT_TIMESTAMP
        WHERE ID_usuario = ?
          AND AccionURL IN (?, ?)
    ");
    $stmt_notif->execute([$mensaje, $report_url, 'Reportar conductor', (int)$usuario_id, $confirmar_url, $calificar_url]);

    echo json_encode(['success' => true, 'message' => 'Calificacion guardada exitosamente']);
} catch (Exception $e) {
    error_log('Error calificando conductor: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'No se pudo guardar la calificacion']);
}
