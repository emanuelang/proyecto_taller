<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

if (!verify_csrf_token($_GET['csrf_token'] ?? null)) {
    safe_error('Solicitud invalida.');
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: viajes.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 0. Verificar que el viaje pertenezca al conductor actual
    $stmt_own = $pdo->prepare("
        SELECT p.ID_publicacion, p.Precio, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida
        FROM Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
    ");
    $stmt_own->execute([$id, $_SESSION['conductor_id']]);
    $viaje = $stmt_own->fetch(PDO::FETCH_ASSOC);

    if (!$viaje) {
        throw new Exception("El viaje no existe o no te pertenece.");
    }

    // 1. Obtener información de pasajeros para reembolsos y notificaciones
    $stmt_info = $pdo->prepare("
        SELECT pas.ID_usuario, p.Precio, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida, r.Estado AS EstadoReserva
        FROM Reservas r 
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva 
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero 
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion 
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        WHERE p.ID_publicacion = ? AND cp.ID_conductor = ? AND r.Estado NOT IN ('Cancelada', 'Rechazada')
    ");
    $stmt_info->execute([$id, $_SESSION['conductor_id']]);
    $reembolsos = $stmt_info->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reembolsos as $r) {
        if ($r['EstadoReserva'] === 'Completada' && PAYMENTS_ENABLED) {
            // 2. Reembolsar saldo
            $stmt_reembolso = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?");
            $stmt_reembolso->execute([$r['Precio'], $r['ID_usuario']]);
            $msg = "El viaje de {$r['CiudadOrigen']} a {$r['CiudadDestino']} ({$r['HoraSalida']}) ha sido cancelado por el conductor. Se han reembolsado $" . number_format($r['Precio'], 2) . " a tu saldo.";
        } else {
            $msg = "El viaje de {$r['CiudadOrigen']} a {$r['CiudadDestino']} ({$r['HoraSalida']}) ha sido cancelado por el conductor.";
        }

        // 3. Notificar
        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$r['ID_usuario'], $msg]);
    }

    // Cancelar las reservas asociadas
    $stmt_cancel_res = $pdo->prepare("UPDATE Reservas SET Estado = 'Cancelada' WHERE ID_publicacion = ? AND Estado NOT IN ('Cancelada', 'Rechazada')");
    $stmt_cancel_res->execute([$id]);

    // 4. Cambiar estado de la publicación a 'Cancelada'
    $stmt_cancel = $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?");
    $stmt_cancel->execute([$id]);

    $pdo->commit();
    $mensaje_final = PAYMENTS_ENABLED ? "Viaje cancelado y pasajeros reembolsados." : "Viaje cancelado y pasajeros notificados.";
    header('Location: viajes.php?msg=' . urlencode($mensaje_final));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al cancelar viaje: " . $e->getMessage());
    safe_error("No se pudo cancelar el viaje.");
}


header('Location: viajes.php');
exit;
