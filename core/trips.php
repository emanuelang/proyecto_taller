<?php

function refresh_finished_trip_notifications(PDO $pdo): void {
    $base_path = parse_url(BASE_URL, PHP_URL_PATH) ?: BASE_URL;
    $base_path = rtrim($base_path, '/') . '/';

    $stmt_invalid_actions = $pdo->prepare("
        DELETE n
        FROM Notificaciones n
        WHERE (
            n.AccionURL LIKE '%reservas/confirmar_llegada.php?reserva_id=%'
            OR n.AccionURL LIKE '%reservas/calificar.php?reserva_id=%'
        )
        AND NOT EXISTS (
            SELECT 1
            FROM Reservas r
            JOIN PasajerosReservas pr ON pr.ID_reserva = r.ID_reserva
            JOIN Pasajeros pas ON pas.ID_pasajero = pr.ID_pasajero
            WHERE r.ID_reserva = CAST(SUBSTRING_INDEX(n.AccionURL, 'reserva_id=', -1) AS UNSIGNED)
              AND pas.ID_usuario = n.ID_usuario
        )
    ");
    $stmt_invalid_actions->execute();

    $stmt_duplicate_actions = $pdo->prepare("
        DELETE n_old
        FROM Notificaciones n_old
        JOIN Notificaciones n_new
          ON n_old.ID_usuario = n_new.ID_usuario
         AND CAST(SUBSTRING_INDEX(n_old.AccionURL, 'reserva_id=', -1) AS UNSIGNED) = CAST(SUBSTRING_INDEX(n_new.AccionURL, 'reserva_id=', -1) AS UNSIGNED)
         AND n_old.ID_notificacion < n_new.ID_notificacion
        WHERE (
            n_old.AccionURL LIKE '%reservas/confirmar_llegada.php?reserva_id=%'
            OR n_old.AccionURL LIKE '%reservas/calificar.php?reserva_id=%'
        )
        AND (
            n_new.AccionURL LIKE '%reservas/confirmar_llegada.php?reserva_id=%'
            OR n_new.AccionURL LIKE '%reservas/calificar.php?reserva_id=%'
        )
    ");
    $stmt_duplicate_actions->execute();

    $stmt_calificadas = $pdo->prepare("
        UPDATE Notificaciones n
        JOIN Pasajeros pas ON n.ID_usuario = pas.ID_usuario
        JOIN PasajerosReservas pr ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Reservas r ON r.ID_reserva = pr.ID_reserva
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Calificaciones cal ON cal.ID_reserva = r.ID_reserva AND cal.ID_pasajero = pr.ID_pasajero
        SET n.Mensaje = CONCAT(
                'Tu llegada al viaje de ', p.CiudadOrigen, ' a ', p.CiudadDestino,
                ' del ', DATE_FORMAT(p.HoraSalida, '%d/%m/%Y %H:%i'),
                ' ya fue confirmada y la calificacion ya fue enviada.'
            ),
            n.AccionURL = NULL,
            n.AccionLabel = NULL,
            n.AccionSecundariaURL = CONCAT(?, 'reportar.php?conductor_id=', cp.ID_conductor, '&publicacion_id=', p.ID_publicacion),
            n.AccionSecundariaLabel = 'Reportar conductor'
        WHERE r.Estado = 'Completada'
          AND p.HoraSalida < NOW()
          AND (
              n.AccionURL = CONCAT(?, 'reservas/confirmar_llegada.php?reserva_id=', r.ID_reserva)
              OR n.AccionURL = CONCAT(?, 'reservas/confirmar_llegada.php?reserva_id=', r.ID_reserva)
              OR n.AccionURL = CONCAT(?, 'reservas/calificar.php?reserva_id=', r.ID_reserva)
              OR n.AccionURL = CONCAT(?, 'reservas/calificar.php?reserva_id=', r.ID_reserva)
          )
    ");
    $stmt_calificadas->execute([BASE_URL, BASE_URL, $base_path, BASE_URL, $base_path]);

    $stmt_confirmadas = $pdo->prepare("
        UPDATE Notificaciones n
        JOIN Pasajeros pas ON n.ID_usuario = pas.ID_usuario
        JOIN PasajerosReservas pr ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Reservas r ON r.ID_reserva = pr.ID_reserva
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN ConfirmacionesViaje cv ON cv.ID_reserva = r.ID_reserva AND cv.ID_usuario = pas.ID_usuario AND cv.ConfirmoLlegada = 1
        LEFT JOIN Calificaciones cal ON cal.ID_reserva = r.ID_reserva AND cal.ID_pasajero = pr.ID_pasajero
        SET n.Mensaje = CONCAT(
                'Tu llegada al viaje de ', p.CiudadOrigen, ' a ', p.CiudadDestino,
                ' del ', DATE_FORMAT(p.HoraSalida, '%d/%m/%Y %H:%i'),
                ' ya fue confirmada. Ahora podes calificar al conductor.'
            ),
            n.AccionURL = CONCAT(?, 'reservas/calificar.php?reserva_id=', r.ID_reserva),
            n.AccionLabel = 'Calificar conductor',
            n.AccionSecundariaURL = CONCAT(?, 'reportar.php?conductor_id=', cp.ID_conductor, '&publicacion_id=', p.ID_publicacion),
            n.AccionSecundariaLabel = 'Reportar conductor'
        WHERE r.Estado = 'Completada'
          AND p.HoraSalida < NOW()
          AND cal.ID_calificacion IS NULL
          AND (
              n.AccionURL = CONCAT(?, 'reservas/confirmar_llegada.php?reserva_id=', r.ID_reserva)
              OR n.AccionURL = CONCAT(?, 'reservas/confirmar_llegada.php?reserva_id=', r.ID_reserva)
              OR n.AccionURL = CONCAT(?, 'reservas/calificar.php?reserva_id=', r.ID_reserva)
              OR n.AccionURL = CONCAT(?, 'reservas/calificar.php?reserva_id=', r.ID_reserva)
          )
    ");
    $stmt_confirmadas->execute([BASE_URL, BASE_URL, BASE_URL, $base_path, BASE_URL, $base_path]);
}

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
        WHERE p.Estado IN ('Activa', 'Finalizada')
          AND p.HoraSalida < NOW()
          AND r.Estado = 'Completada'
          AND NOT EXISTS (
              SELECT 1
              FROM ConfirmacionesViaje cv
              WHERE cv.ID_reserva = r.ID_reserva
                AND cv.ID_usuario = pas.ID_usuario
                AND cv.ConfirmoLlegada = 1
          )
          AND NOT EXISTS (
              SELECT 1
              FROM Calificaciones cal
              WHERE cal.ID_reserva = r.ID_reserva
                AND cal.ID_pasajero = pr.ID_pasajero
          )
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

    refresh_finished_trip_notifications($pdo);
}
