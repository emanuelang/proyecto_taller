<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

$external_reference = $_GET['external_reference'] ?? null;
$status = $_GET['collection_status'] ?? null;
$payment_id = $_GET['collection_id'] ?? null;

if (!$external_reference || $status !== 'approved') {
    die("Error o pago no aprobado.");
}

// Extraer ID de viaje y de usuario desde la external_reference (Formato: viajeID_usuarioID)
$partes = explode('_', $external_reference);
if (count($partes) !== 2) {
    die("Referencia de pago inválida (No coincide con el formato esperado).");
}
$viaje_id = (int)$partes[0];
$usuario_id = (int)$partes[1];

// Generar código de acceso único
$codigo_acceso = "CA-" . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));

try {
    $pdo->beginTransaction();

    // 1. Obtener o crear ID Pasajero
    $stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
    $stmt_pasajero->execute([$usuario_id]);
    $pasajero = $stmt_pasajero->fetch();
    
    if (!$pasajero) {
        $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)")->execute([$usuario_id]);
        $pasajero_id = $pdo->lastInsertId();
    } else {
        $pasajero_id = $pasajero['ID_pasajero'];
    }

    // 2. Control de duplicados (por si Mercado Pago recarga la página de éxito o invoca el webhook)
    $stmt_dup = $pdo->prepare("
        SELECT COUNT(*) FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        WHERE r.ID_publicacion = ? AND pr.ID_pasajero = ? AND r.Estado = 'Completada'
    ");
    $stmt_dup->execute([$viaje_id, $pasajero_id]);
    if ($stmt_dup->fetchColumn() > 0) {
        // Ya estaba pagado y registrado exitosamente, lo mandamos al panel
        $pdo->rollBack();
        header("Location: " . BASE_URL . "reservas/mis_reservas.php");
        exit;
    }

    // 3. Crear la Reserva YA COMPLETADA directamente
    $stmt_res = $pdo->prepare("INSERT INTO Reservas (ID_publicacion, Estado, FechaReserva, CodigoAcceso) VALUES (?, 'Completada', NOW(), ?)");
    $stmt_res->execute([$viaje_id, $codigo_acceso]);
    $reserva_id = $pdo->lastInsertId();

    // 4. Vincular Pasajero a Reserva
    $pdo->prepare("INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)")->execute([$pasajero_id, $reserva_id]);

    // 5. Obtener precio actual original para la tabla de Pagos
    $stmt_price = $pdo->prepare("SELECT Precio FROM Publicaciones WHERE ID_publicacion = ?");
    $stmt_price->execute([$viaje_id]);
    $precio = $stmt_price->fetchColumn();

    // 6. Registrar pago oficialmente en el sistema
    $stmt_pago = $pdo->prepare("INSERT INTO Pagos (Monto, Estado, ID_reserva) VALUES (?, 'Completado', ?)");
    $stmt_pago->execute([$precio, $reserva_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error procesando pago en la base de datos: " . $e->getMessage());
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
        <h2 style="color: #155724;">¡Reserva y Pago Exitosos!</h2>
        <p style="color: #155724; font-size: 1.1em;">Tu lugar ha sido asegurado a través de Mercado Pago.</p>
        
        <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0;">
            <p style="margin:0; color:#6c757d;">Tu código secreto de acceso al auto es:</p>
            <h1 style="margin: 10px 0; color: #007bff; letter-spacing: 2px;"><?= htmlspecialchars($codigo_acceso) ?></h1>
            <p style="margin:0; font-size: 0.9em; color:#6c757d;">Muéstraselo al conductor antes de iniciar el viaje.</p>
        </div>
        
        <p>Referencia de pago MP: <strong><?= htmlspecialchars($payment_id) ?></strong></p>
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="btn" style="display:inline-block; margin-top:20px;">Ver mis reservas activas</a>
    </div>
</body>
</html>
