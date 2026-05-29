<?php

function sync_finished_trips(PDO $pdo): void {
    static $already_synced = false;
    if ($already_synced) {
        return;
    }

    $already_synced = true;

    $stmt_finished = $pdo->prepare("
        SELECT p.ID_publicacion, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida,
               r.ID_reserva, pas.ID_usuario, cp.ID_conductor
        FROM Publicaciones p
        JOIN Reservas r ON p.ID_publicacion = r.ID_publicacion
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        WHERE p.Estado = 'Activa'
          AND p.HoraSalida < NOW()
          AND r.Estado = 'Completada'
    ");
    $stmt_finished->execute();
    $finished_passengers = $stmt_finished->fetchAll(PDO::FETCH_ASSOC);

    $stmt_exists = $pdo->prepare("
        SELECT COUNT(*)
        FROM Notificaciones
        WHERE ID_usuario = ?
          AND AccionURL = ?
    ");
    $stmt_notify = $pdo->prepare("
        INSERT INTO Notificaciones
            (ID_usuario, Mensaje, AccionURL, AccionLabel, AccionSecundariaURL, AccionSecundariaLabel)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    foreach ($finished_passengers as $row) {
        $action_url = BASE_URL . 'reservas/confirmar_llegada.php?reserva_id=' . (int)$row['ID_reserva'];
        $report_url = BASE_URL . 'reportar.php?conductor_id=' . (int)$row['ID_conductor'] . '&publicacion_id=' . (int)$row['ID_publicacion'];
        $fecha = date('d/m/Y H:i', strtotime($row['HoraSalida']));
        $mensaje = "Tu viaje de {$row['CiudadOrigen']} a {$row['CiudadDestino']} del {$fecha} finalizo. Confirma tu llegada para calificar al conductor o reporta un problema si corresponde.";

        $stmt_exists->execute([(int)$row['ID_usuario'], $action_url]);
        if ((int)$stmt_exists->fetchColumn() === 0) {
            $stmt_notify->execute([(int)$row['ID_usuario'], $mensaje, $action_url, 'Confirmar llegada', $report_url, 'Reportar conductor']);
        }
    }

    $stmt_finished_drivers = $pdo->prepare("
        SELECT p.ID_publicacion, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida,
               c.ID_conductor, c.ID_usuario AS conductor_usuario_id
        FROM Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        WHERE p.Estado = 'Activa'
          AND p.HoraSalida < NOW()
    ");
    $stmt_finished_drivers->execute();
    $finished_drivers = $stmt_finished_drivers->fetchAll(PDO::FETCH_ASSOC);

    foreach ($finished_drivers as $row) {
        $action_url = BASE_URL . 'conductor/confirmar_viaje_ok.php?id=' . (int)$row['ID_publicacion'];
        $report_url = BASE_URL . 'conductor/ver_reservas.php?id=' . (int)$row['ID_publicacion'] . '&post_viaje=1';
        $fecha = date('d/m/Y H:i', strtotime($row['HoraSalida']));
        $mensaje = "Tu viaje de {$row['CiudadOrigen']} a {$row['CiudadDestino']} del {$fecha} finalizo. Indica si estuvo todo bien o reporta a un pasajero del viaje.";

        $stmt_exists->execute([(int)$row['conductor_usuario_id'], $action_url]);
        if ((int)$stmt_exists->fetchColumn() === 0) {
            $stmt_notify->execute([(int)$row['conductor_usuario_id'], $mensaje, $action_url, 'Todo bien', $report_url, 'Reportar pasajero']);
        }
    }

    $stmt = $pdo->prepare("UPDATE Publicaciones SET Estado = 'Finalizada' WHERE Estado = 'Activa' AND HoraSalida < NOW()");
    $stmt->execute();
}
