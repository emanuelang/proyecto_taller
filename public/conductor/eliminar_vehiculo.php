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

    // 2. Buscar TODAS las publicaciones asociadas a este vehículo (no solo las activas)
    $stmt_pub = $pdo->prepare("
        SELECT ID_publicacion, CiudadOrigen, CiudadDestino, HoraSalida, Precio, Estado
        FROM Publicaciones
        WHERE ID_vehiculo = ?
    ");
    $stmt_pub->execute([$vehiculo_id]);
    $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

    foreach ($publicaciones as $pub) {
        $pub_id = $pub['ID_publicacion'];

        // Solo procesar reembolsos y notificaciones para publicaciones que no estaban ya canceladas/rechazadas
        if (!in_array($pub['Estado'], ['Cancelada', 'Rechazada'])) {

            // 3. Buscar reservas activas (no canceladas ni rechazadas) para este viaje
            $stmt_res = $pdo->prepare("
                SELECT r.ID_reserva, r.Estado, u.ID_usuario, u.Saldo, u.Nombre
                FROM Reservas r
                JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
                JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
                JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
                WHERE r.ID_publicacion = ? AND r.Estado NOT IN ('Cancelada', 'Rechazada')
            ");
            $stmt_res->execute([$pub_id]);
            $reservas = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reservas as $res) {
                if ($res['Estado'] === 'Completada' && PAYMENTS_ENABLED) {
                    // 4a. Reembolso automático para reservas ya pagadas
                    $reembolso = (float)$pub['Precio'];
                    $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")
                        ->execute([$reembolso, $res['ID_usuario']]);
                    $mensaje = "Tu viaje de " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] . " (" . date('d/m', strtotime($pub['HoraSalida'])) . ") ha sido cancelado porque el conductor eliminó el vehículo. Se han reembolsado $" . number_format($reembolso, 2) . " a tu saldo.";
                } else {
                    // 4b. Notificar sin reembolso (Pendiente = aún no había pagado)
                    $mensaje = "Tu reserva para el viaje de " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] . " (" . date('d/m', strtotime($pub['HoraSalida'])) . ") fue cancelada porque el conductor eliminó el vehículo.";
                }

                // 5. Notificar al pasajero
                $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")
                    ->execute([$res['ID_usuario'], $mensaje]);
            }

            // 6. Cancelar todas las reservas activas de esta publicación
            $pdo->prepare("UPDATE Reservas SET Estado = 'Cancelada' WHERE ID_publicacion = ? AND Estado NOT IN ('Cancelada', 'Rechazada')")
                ->execute([$pub_id]);
        }

        // 7. Eliminar físicamente la publicación (sus reservas, pagos y calificaciones se eliminan por CASCADE)
        $pdo->prepare("DELETE FROM Publicaciones WHERE ID_publicacion = ?")
            ->execute([$pub_id]);
    }

    // 8. Eliminar relación ConductorVehiculo (tiene ON DELETE CASCADE, pero lo hacemos explícito por claridad)
    $pdo->prepare("DELETE FROM ConductorVehiculo WHERE ID_vehiculo = ?")
        ->execute([$vehiculo_id]);

    // 9. Finalmente eliminar el vehículo (ya no tiene publicaciones que lo referencien)
    $pdo->prepare("DELETE FROM Vehiculos WHERE ID_vehiculo = ?")
        ->execute([$vehiculo_id]);

    $pdo->commit();
    $mensaje_final = PAYMENTS_ENABLED ? "Vehiculo eliminado y viajes asociados cancelados/reembolsados." : "Vehiculo eliminado y viajes asociados cancelados.";
    header("Location: vehiculos.php?msg=" . urlencode($mensaje_final));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al eliminar vehiculo: " . $e->getMessage());
    safe_error("No se pudo eliminar el vehiculo.");
}
