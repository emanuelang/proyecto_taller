<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

if (!isset($_GET['id'])) {
    header("Location: vehiculos.php");
    exit;
}

$vehiculo_id = (int)$_GET['id'];
$conductor_id = $_SESSION['conductor_id'];

try {
    $pdo->beginTransaction();

    // 1. Verificar que el vehículo pertenezca al conductor
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM ConductorVehiculo WHERE ID_conductor = ? AND ID_vehiculo = ?");
    $stmt_check->execute([$conductor_id, $vehiculo_id]);
    if ($stmt_check->fetchColumn() == 0) {
        throw new Exception("El vehículo no te pertenece o no existe.");
    }

    // 2. Buscar publicaciones (viajes) activas asociadas a este vehículo
    $stmt_pub = $pdo->prepare("SELECT ID_publicacion, CiudadOrigen, CiudadDestino, HoraSalida, Precio FROM Publicaciones WHERE ID_vehiculo = ? AND Estado = 'Activa'");
    $stmt_pub->execute([$vehiculo_id]);
    $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

    foreach ($publicaciones as $pub) {
        $pub_id = $pub['ID_publicacion'];
        
        // 3. Buscar reservas pagadas (Completadas) para este viaje
        $stmt_res = $pdo->prepare("
            SELECT r.ID_reserva, u.ID_usuario, u.Saldo, u.Nombre
            FROM Reservas r
            JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
            JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
            JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
            WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
        ");
        $stmt_res->execute([$pub_id]);
        $reservas = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservas as $res) {
            // 4. Procesar reembolso automático
            $reembolso = (float)$pub['Precio'];
            $stmt_reembolso = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?");
            $stmt_reembolso->execute([$reembolso, $res['ID_usuario']]);

            // 5. Notificar al pasajero
            $mensaje = "Tu viaje de " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] . " (" . date('d/m', strtotime($pub['HoraSalida'])) . ") ha sido cancelado porque el conductor eliminó el vehículo. Se han reembolsado $" . number_format($reembolso, 2) . " a tu saldo.";
            $stmt_notif = $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)");
            $stmt_notif->execute([$res['ID_usuario'], $mensaje]);
        }

        // 6. Marcar publicación como cancelada
        $stmt_cancel = $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?");
        $stmt_cancel->execute([$pub_id]);
    }

    // 7. Eliminar relación y vehículo (Físico porque el usuario pidió aplicar propuesta 1 que decía cancelar viajes, no el vehículo en sí)
    // Pero la relación ConductorVehiculo tiene ON DELETE CASCADE, así que borramos el vehículo
    $stmt_del_veh = $pdo->prepare("DELETE FROM Vehiculos WHERE ID_vehiculo = ?");
    $stmt_del_veh->execute([$vehiculo_id]);

    $pdo->commit();
    header("Location: vehiculos.php?msg=" . urlencode("Vehículo eliminado y viajes asociados cancelados/reembolsados."));
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al eliminar el vehículo: " . $e->getMessage());
}
