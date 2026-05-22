<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$external_reference = $_GET['external_reference'] ?? null;
$status = $_GET['collection_status'] ?? null;
$payment_id = $_GET['collection_id'] ?? null;

if (!$external_reference || $status !== 'approved') {
    safe_error('Pago no aprobado.');
}

$pending = $_SESSION['pending_reserva'][$external_reference] ?? null;
if (!$pending || (time() - (int)$pending['created_at']) > 1800 || (int)$pending['user_id'] !== (int)$_SESSION['user_id']) {
    safe_error('La operacion de reserva no es valida o ya expiro.');
}

$viaje_id = (int)$pending['viaje_id'];
$usuario_id = (int)$pending['user_id'];
$codigo_acceso = "CA-" . strtoupper(substr(bin2hex(random_bytes(8)), 0, 8));

try {
    $pdo->beginTransaction();

    $stmt_viaje = $pdo->prepare("
        SELECT p.ID_publicacion, p.Precio, p.Estado, c.ID_usuario AS conductor_usuario_id,
               v.CantidadAsientos AS total,
               (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') AS ocupados
        FROM Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
        WHERE p.ID_publicacion = ?
        FOR UPDATE
    ");
    $stmt_viaje->execute([$viaje_id]);
    $viaje = $stmt_viaje->fetch(PDO::FETCH_ASSOC);

    if (!$viaje || $viaje['Estado'] !== 'Activa') {
        throw new Exception('El viaje ya no esta disponible.');
    }

    if ((int)$viaje['conductor_usuario_id'] === $usuario_id) {
        throw new Exception('No podes reservar tu propio viaje.');
    }

    if ((int)$viaje['ocupados'] >= (int)$viaje['total']) {
        throw new Exception('No hay asientos disponibles en este viaje.');
    }

    $stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
    $stmt_pasajero->execute([$usuario_id]);
    $pasajero = $stmt_pasajero->fetch();

    if (!$pasajero) {
        $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)")->execute([$usuario_id]);
        $pasajero_id = $pdo->lastInsertId();
    } else {
        $pasajero_id = $pasajero['ID_pasajero'];
    }

    $stmt_dup = $pdo->prepare("
        SELECT COUNT(*) FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        WHERE r.ID_publicacion = ? AND pr.ID_pasajero = ? AND r.Estado = 'Completada'
    ");
    $stmt_dup->execute([$viaje_id, $pasajero_id]);
    if ($stmt_dup->fetchColumn() > 0) {
        $pdo->rollBack();
        unset($_SESSION['pending_reserva'][$external_reference]);
        header("Location: " . BASE_URL . "reservas/mis_reservas.php");
        exit;
    }

    $stmt_res = $pdo->prepare("INSERT INTO Reservas (ID_publicacion, Estado, FechaReserva, CodigoAcceso) VALUES (?, 'Completada', NOW(), ?)");
    $stmt_res->execute([$viaje_id, $codigo_acceso]);
    $reserva_id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)")->execute([$pasajero_id, $reserva_id]);

    $stmt_pago = $pdo->prepare("INSERT INTO Pagos (Monto, Estado, ID_reserva) VALUES (?, 'Completado', ?)");
    $stmt_pago->execute([(float)$viaje['Precio'], $reserva_id]);

    $pdo->commit();
    unset($_SESSION['pending_reserva'][$external_reference]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error procesando reserva MP: ' . $e->getMessage());
    safe_error('No se pudo completar la reserva.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago Exitoso - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div style="max-width: 500px; margin: 50px auto; padding: 30px; border: 1px solid #c3e6cb; border-radius: 8px; background-color: #d4edda; text-align: center;">
        <h2 style="color: #155724;">Reserva y pago exitosos</h2>
        <p style="color: #155724; font-size: 1.1em;">Tu lugar ha sido asegurado a traves de Mercado Pago.</p>

        <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0;">
            <p style="margin:0; color:#6c757d;">Tu codigo secreto de acceso al auto es:</p>
            <h1 style="margin: 10px 0; color: #007bff; letter-spacing: 2px;"><?= htmlspecialchars($codigo_acceso) ?></h1>
            <p style="margin:0; font-size: 0.9em; color:#6c757d;">Mostraselo al conductor antes de iniciar el viaje.</p>
        </div>

        <?php if ($payment_id): ?>
            <p>Referencia de pago MP: <strong><?= htmlspecialchars($payment_id) ?></strong></p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="btn" style="display:inline-block; margin-top:20px;">Ver mis reservas activas</a>
    </div>
</body>
</html>
