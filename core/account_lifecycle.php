<?php

function cancel_publication_with_refunds(PDO $pdo, int $publicacion_id, string $motivo): array
{
    $stmt_pub = $pdo->prepare("
        SELECT ID_publicacion, CiudadOrigen, CiudadDestino, Precio, Estado
        FROM Publicaciones
        WHERE ID_publicacion = ?
        FOR UPDATE
    ");
    $stmt_pub->execute([$publicacion_id]);
    $pub = $stmt_pub->fetch(PDO::FETCH_ASSOC);

    if (!$pub || in_array($pub['Estado'], ['Cancelada', 'Rechazada'], true)) {
        return ['reservas' => 0, 'reembolsos' => 0];
    }

    $stmt_reservas = $pdo->prepare("
        SELECT r.ID_reserva, u.ID_usuario
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
        WHERE r.ID_publicacion = ?
          AND r.Estado = 'Completada'
    ");
    $stmt_reservas->execute([$publicacion_id]);
    $reservas = $stmt_reservas->fetchAll(PDO::FETCH_ASSOC);

    $reembolsos = 0;
    foreach ($reservas as $reserva) {
        $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")
            ->execute([(float)$pub['Precio'], (int)$reserva['ID_usuario']]);

        $mensaje = $motivo . " Viaje: " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] .
            ". Se reembolsaron $" . number_format((float)$pub['Precio'], 2, ',', '.') . " a tu saldo.";
        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")
            ->execute([(int)$reserva['ID_usuario'], $mensaje]);
        $reembolsos++;
    }

    $pdo->prepare("
        UPDATE Reservas
        SET Estado = 'Cancelada'
        WHERE ID_publicacion = ?
          AND Estado NOT IN ('Cancelada', 'Rechazada')
    ")->execute([$publicacion_id]);

    $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")
        ->execute([$publicacion_id]);

    return ['reservas' => count($reservas), 'reembolsos' => $reembolsos];
}

function deactivate_user_account(PDO $pdo, int $user_id, string $motivo = 'Tu cuenta fue desactivada.'): array
{
    $stats = [
        'viajes_cancelados' => 0,
        'reservas_canceladas' => 0,
        'reembolsos' => 0,
    ];

    $stmt_user = $pdo->prepare("SELECT ID_usuario FROM Usuarios WHERE ID_usuario = ? FOR UPDATE");
    $stmt_user->execute([$user_id]);
    if (!$stmt_user->fetch()) {
        throw new RuntimeException('Usuario no encontrado.');
    }

    $stmt_cond = $pdo->prepare("SELECT ID_conductor FROM Conductores WHERE ID_usuario = ?");
    $stmt_cond->execute([$user_id]);
    $conductores = $stmt_cond->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conductores as $conductor) {
        $conductor_id = (int)$conductor['ID_conductor'];

        $stmt_viajes = $pdo->prepare("
            SELECT p.ID_publicacion
            FROM Publicaciones p
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            WHERE cp.ID_conductor = ?
              AND p.Estado = 'Activa'
              AND p.HoraSalida >= NOW()
        ");
        $stmt_viajes->execute([$conductor_id]);
        $viajes = $stmt_viajes->fetchAll(PDO::FETCH_COLUMN);

        foreach ($viajes as $publicacion_id) {
            $result = cancel_publication_with_refunds($pdo, (int)$publicacion_id, $motivo);
            $stats['viajes_cancelados']++;
            $stats['reservas_canceladas'] += $result['reservas'];
            $stats['reembolsos'] += $result['reembolsos'];
        }

        $pdo->prepare("UPDATE Conductores SET Estado = 'Rechazado', BaneadoHasta = NULL WHERE ID_conductor = ?")
            ->execute([$conductor_id]);
    }

    $stmt_reservas_pasajero = $pdo->prepare("
        SELECT r.ID_reserva, p.ID_publicacion, p.CiudadOrigen, p.CiudadDestino, u_cond.ID_usuario AS conductor_usuario_id
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
        WHERE pas.ID_usuario = ?
          AND r.Estado = 'Completada'
          AND p.Estado = 'Activa'
          AND p.HoraSalida >= NOW()
    ");
    $stmt_reservas_pasajero->execute([$user_id]);
    $reservas_pasajero = $stmt_reservas_pasajero->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservas_pasajero as $reserva) {
        $pdo->prepare("UPDATE Reservas SET Estado = 'Cancelada' WHERE ID_reserva = ?")
            ->execute([(int)$reserva['ID_reserva']]);

        $mensaje_cond = "Un pasajero desactivo su cuenta y se cancelo su reserva para el viaje " .
            $reserva['CiudadOrigen'] . " a " . $reserva['CiudadDestino'] . ".";
        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")
            ->execute([(int)$reserva['conductor_usuario_id'], $mensaje_cond]);

        $stats['reservas_canceladas']++;
    }

    $anon_email = 'deleted_' . $user_id . '@deleted.moveon.local';
    $anon_dni = 'deleted_' . $user_id;

    $pdo->prepare("
        UPDATE Usuarios
        SET estado = 'suspendido',
            BaneadoHasta = NULL,
            Correo = ?,
            DNI = ?,
            Telefono = NULL,
            TokenRecuperacion = NULL,
            ExpiracionToken = NULL
        WHERE ID_usuario = ?
    ")->execute([$anon_email, $anon_dni, $user_id]);

    return $stats;
}
