<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$reserva_id = (int)($_GET['id'] ?? 0);
$viaje_id   = (int)($_GET['viaje'] ?? 0);

if (!verify_csrf_token($_GET['csrf_token'] ?? null)) {
    header("Location: ver_reservas.php?id=$viaje_id&err=" . urlencode("La sesion expiro. Volve a cargar la pagina e intentalo nuevamente."));
    exit;
}

if (!$reserva_id || !$viaje_id) {
    header("Location: viajes.php");
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Verificar que la reserva pertenezca a un viaje del conductor actual
    $stmt_check = $pdo->prepare("
        SELECT r.ID_reserva, r.Estado, pas.ID_usuario, p.CiudadOrigen, p.CiudadDestino, p.Precio
        FROM Reservas r
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        WHERE r.ID_reserva = ? AND cp.ID_conductor = ?
    ");
    $stmt_check->execute([$reserva_id, $_SESSION['conductor_id']]);
    $info = $stmt_check->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        throw new Exception("Reserva no encontrada o no pertenece a tus viajes.");
    }

    // 2. Si la reserva está Completada (pagada), reembolsar al pasajero
    if ($info['Estado'] === 'Completada') {
        $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")
            ->execute([$info['Precio'], $info['ID_usuario']]);
        $msg = "El conductor te ha expulsado del viaje de {$info['CiudadOrigen']} a {$info['CiudadDestino']}. Se han reembolsado $" . number_format($info['Precio'], 2) . " a tu saldo.";
    } else {
        $msg = "El conductor te ha expulsado del viaje de {$info['CiudadOrigen']} a {$info['CiudadDestino']}.";
    }

    // 3. Notificar al pasajero
    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")
        ->execute([$info['ID_usuario'], $msg]);

    // 4. Marcar la reserva como Cancelada (soft delete para preservar integridad referencial)
    $pdo->prepare("UPDATE Reservas SET Estado = 'Cancelada' WHERE ID_reserva = ?")
        ->execute([$reserva_id]);

    // 5. Liberar el asiento en la publicación
    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al cancelar reserva desde conductor: " . $e->getMessage());
    header("Location: ver_reservas.php?id=$viaje_id&err=" . urlencode("No se pudo cancelar la reserva. Intentalo nuevamente."));
    exit;
}

header("Location: ver_reservas.php?id=$viaje_id&msg=" . urlencode("Reserva cancelada correctamente."));
exit;
