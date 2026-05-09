<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['reserva_id']) || !isset($data['puntuacion'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$reserva_id = (int)$data['reserva_id'];
$puntuacion = (int)$data['puntuacion'];

if ($puntuacion < 1 || $puntuacion > 5) {
    echo json_encode(['success' => false, 'message' => 'Puntuación inválida']);
    exit;
}

$usuario_id = $_SESSION['user_id'];

try {
    // Verificar que el usuario es pasajero de esa reserva y obtener el conductor
    $stmt = $pdo->prepare("
        SELECT pr.ID_pasajero, cp.ID_conductor, c.ID_usuario AS conductor_usuario_id
        FROM PasajerosReservas pr
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Reservas r ON pr.ID_reserva = r.ID_reserva
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

    $pasajero_id = $res['ID_pasajero'];
    $conductor_id = $res['ID_conductor'];

    // Validar que no se está calificando a sí mismo
    if ($usuario_id == $res['conductor_usuario_id']) {
        echo json_encode(['success' => false, 'message' => 'No puedes calificarte a ti mismo']);
        exit;
    }

    // Verificar si ya calificó
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Calificaciones WHERE ID_reserva = ? AND ID_pasajero = ?");
    $stmt_check->execute([$reserva_id, $pasajero_id]);
    if ($stmt_check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Ya calificaste este viaje']);
        exit;
    }

    // Insertar calificación
    $stmt_insert = $pdo->prepare("
        INSERT INTO Calificaciones (Puntuacion, ID_pasajero, ID_conductor, ID_reserva)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_insert->execute([$puntuacion, $pasajero_id, $conductor_id, $reserva_id]);

    echo json_encode(['success' => true, 'message' => 'Calificación guardada exitosamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
