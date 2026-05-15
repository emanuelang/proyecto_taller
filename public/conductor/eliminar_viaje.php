<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

try {
    $pdo->beginTransaction();

    // 1. Obtener información de pasajeros y precio para reembolsos
    $stmt_info = $pdo->prepare("
        SELECT pas.ID_usuario, p.Precio, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida
        FROM Reservas r 
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva 
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero 
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion 
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        WHERE p.ID_publicacion = ? AND cp.ID_conductor = ? AND r.Estado = 'Completada'
    ");
    $stmt_info->execute([$id, $_SESSION['conductor_id']]);
    $reembolsos = $stmt_info->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reembolsos as $r) {
        // 2. Reembolsar saldo
        $stmt_reembolso = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?");
        $stmt_reembolso->execute([$r['Precio'], $r['ID_usuario']]);

        // 3. Notificar
        $msg = "El viaje de {$r['CiudadOrigen']} a {$r['CiudadDestino']} ({$r['HoraSalida']}) ha sido cancelado por el conductor. Se han reembolsado $" . number_format($r['Precio'], 2) . " a tu saldo.";
        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$r['ID_usuario'], $msg]);
    }

    // 4. Cambiar estado de la publicación a 'Cancelada'
    $stmt_cancel = $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?");
    $stmt_cancel->execute([$id]);

    $pdo->commit();
    header('Location: viajes.php?msg=' . urlencode("Viaje cancelado y pasajeros reembolsados."));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al cancelar el viaje: " . $e->getMessage());
}


header('Location: viajes.php');
exit;
