<?php
require_once __DIR__ . '/../config/database.php';

$now = new DateTimeImmutable();

function demo_svg(string $title, string $subtitle, string $bg, string $fg = '#07142b'): string
{
    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $subtitle = htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8');
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="400" viewBox="0 0 640 400">
  <rect width="640" height="400" rx="28" fill="$bg"/>
  <rect x="34" y="34" width="572" height="332" rx="22" fill="#ffffff" fill-opacity="0.72"/>
  <text x="64" y="155" fill="$fg" font-family="Arial, sans-serif" font-size="46" font-weight="700">$title</text>
  <text x="64" y="215" fill="$fg" font-family="Arial, sans-serif" font-size="28">$subtitle</text>
  <text x="64" y="305" fill="$fg" fill-opacity="0.62" font-family="Arial, sans-serif" font-size="22">Imagen demo para revision</text>
</svg>
SVG;

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function demo_user(PDO $pdo, string $email, string $nombre, string $apellido, string $dni, ?string $baneadoHasta = null, string $estado = 'activo'): int
{
    $dniFrente = demo_svg('DNI frente', $nombre . ' ' . $apellido, '#dbeafe');
    $dniDorso = demo_svg('DNI dorso', 'Documento ' . $dni, '#e0f2fe');
    $perfil = demo_svg('Perfil', $nombre . ' ' . $apellido, '#dcfce7');

    $stmt = $pdo->prepare("SELECT ID_usuario FROM Usuarios WHERE Correo = ?");
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare("
            UPDATE Usuarios
            SET Nombre = ?, Apellido = ?, DNI = ?, Telefono = ?, BaneadoHasta = ?, estado = ?,
                DniFrenteImagen = ?, DniDorsoImagen = ?, FotoPerfil = ?
            WHERE ID_usuario = ?
        ")->execute([$nombre, $apellido, $dni, '3435000000', $baneadoHasta, $estado, $dniFrente, $dniDorso, $perfil, $id]);
        return (int)$id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO Usuarios
            (Nombre, Apellido, DNI, Correo, Telefono, `Contraseña`, BaneadoHasta, estado, DniFrenteImagen, DniDorsoImagen, FotoPerfil, Saldo)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $stmt->execute([$nombre, $apellido, $dni, $email, '3435000000', password_hash('Demo1234', PASSWORD_DEFAULT), $baneadoHasta, $estado, $dniFrente, $dniDorso, $perfil]);
    return (int)$pdo->lastInsertId();
}

function demo_pasajero(PDO $pdo, int $usuarioId): int
{
    $stmt = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
    $stmt->execute([$usuarioId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int)$id;
    }

    $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)")->execute([$usuarioId]);
    return (int)$pdo->lastInsertId();
}

function demo_conductor(PDO $pdo, int $usuarioId, string $estado, ?string $baneadoHasta = null): int
{
    $fotoCara = demo_svg('Foto cara', 'Conductor #' . $usuarioId, '#fef3c7');
    $fotoCarnet = demo_svg('Carnet', 'Licencia demo #' . $usuarioId, '#ede9fe');

    $stmt = $pdo->prepare("SELECT ID_conductor FROM Conductores WHERE ID_usuario = ?");
    $stmt->execute([$usuarioId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare("
            UPDATE Conductores
            SET Estado = ?, BaneadoHasta = ?, TelefonoContacto = ?, AliasMP = ?, FotoCarnet = ?, FotoCara = ?
            WHERE ID_conductor = ?
        ")->execute([$estado, $baneadoHasta, '3435000000', 'demo.moveon', $fotoCarnet, $fotoCara, $id]);
        return (int)$id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO Conductores
            (LicenciaConducir, SeguroVehiculo, CuentaBancaria, Estado, BaneadoHasta, TelefonoContacto, AliasMP, FotoCarnet, FotoCara, ID_usuario)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['LIC-DEMO-' . $usuarioId, 'Seguro demo vigente', 'CBU-DEMO-' . $usuarioId, $estado, $baneadoHasta, '3435000000', 'demo.moveon', $fotoCarnet, $fotoCara, $usuarioId]);
    return (int)$pdo->lastInsertId();
}

function demo_vehicle(PDO $pdo, int $conductorId, string $patente, string $estado, string $marca, string $modelo): int
{
    $papeles = demo_svg('Papeles auto', $patente, '#fee2e2');
    $frente = demo_svg('Foto frente', $marca . ' ' . $modelo, '#dbeafe');
    $costado = demo_svg('Foto costado', $patente, '#dcfce7');
    $atras = demo_svg('Foto atras', $marca . ' ' . $modelo, '#fef3c7');

    $stmt = $pdo->prepare("SELECT ID_vehiculo FROM Vehiculos WHERE Patente = ?");
    $stmt->execute([$patente]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare("
            UPDATE Vehiculos
            SET Estado = ?, Marca = ?, Modelo = ?, Color = ?, CantidadAsientos = ?,
                PapelesAuto = ?, FotoFrente = ?, FotoCostado = ?, FotoAtras = ?, Foto = ?
            WHERE ID_vehiculo = ?
        ")->execute([$estado, $marca, $modelo, 'Gris', 4, $papeles, $frente, $costado, $atras, $frente, $id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO Vehiculos
                (CantidadAsientos, Color, Modelo, Marca, Patente, Estado, PapelesAuto, FotoFrente, FotoCostado, FotoAtras, Foto)
            VALUES
                (4, 'Gris', ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$modelo, $marca, $patente, $estado, $papeles, $frente, $costado, $atras, $frente]);
        $id = (int)$pdo->lastInsertId();
    }

    $stmt_link = $pdo->prepare("SELECT COUNT(*) FROM ConductorVehiculo WHERE ID_conductor = ? AND ID_vehiculo = ?");
    $stmt_link->execute([$conductorId, $id]);
    if ((int)$stmt_link->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO ConductorVehiculo (ID_conductor, ID_vehiculo) VALUES (?, ?)")->execute([$conductorId, $id]);
    }

    return (int)$id;
}

function demo_publicacion(PDO $pdo, int $conductorId, int $vehiculoId, string $origen, string $destino, string $fecha, string $estado, float $precio): int
{
    $stmt = $pdo->prepare("
        SELECT p.ID_publicacion
        FROM Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        WHERE cp.ID_conductor = ? AND p.ID_vehiculo = ? AND p.CiudadOrigen = ? AND p.CiudadDestino = ?
        ORDER BY p.ID_publicacion DESC
        LIMIT 1
    ");
    $stmt->execute([$conductorId, $vehiculoId, $origen, $destino]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE Publicaciones SET Estado = ?, Precio = ?, HoraSalida = ? WHERE ID_publicacion = ?")->execute([$estado, $precio, $fecha, $id]);
        return (int)$id;
    }

    $stmt = $pdo->prepare("
        INSERT INTO Publicaciones (CiudadOrigen, CiudadDestino, CalleSalida, HoraSalida, Precio, Estado, DistanciaKM, DuracionMinutos, ID_vehiculo)
        VALUES (?, ?, 'Plaza principal', ?, ?, ?, 120, 95, ?)
    ");
    $stmt->execute([$origen, $destino, $fecha, $precio, $estado, $vehiculoId]);
    $id = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO ConductorPublicacion (ID_conductor, ID_publicacion) VALUES (?, ?)")->execute([$conductorId, $id]);
    return $id;
}

function cancelar_publicaciones_demo_duplicadas(PDO $pdo, int $conductorId, int $vehiculoId, string $origen, string $destino, int $publicacionActiva): void
{
    $stmt = $pdo->prepare("
        UPDATE Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        SET p.Estado = 'Cancelada'
        WHERE cp.ID_conductor = ?
          AND p.ID_vehiculo = ?
          AND p.CiudadOrigen = ?
          AND p.CiudadDestino = ?
          AND p.ID_publicacion <> ?
          AND p.Estado IN ('Activa', 'Finalizada')
    ");
    $stmt->execute([$conductorId, $vehiculoId, $origen, $destino, $publicacionActiva]);
}

function demo_reserva(PDO $pdo, int $publicacionId, int $pasajeroId, int $usuarioId, string $codigo): int
{
    $stmt = $pdo->prepare("SELECT ID_reserva FROM Reservas WHERE CodigoAcceso = ?");
    $stmt->execute([$codigo]);
    $id = $stmt->fetchColumn();
    if ($id) {
        $pdo->prepare("UPDATE Reservas SET Estado = 'Completada', ID_publicacion = ?, ID_usuario_responsable = ? WHERE ID_reserva = ?")
            ->execute([$publicacionId, $usuarioId, $id]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO Reservas (Estado, ID_publicacion, CodigoAcceso, ID_usuario_responsable)
            VALUES ('Completada', ?, ?, ?)
        ");
        $stmt->execute([$publicacionId, $codigo, $usuarioId]);
        $id = (int)$pdo->lastInsertId();
    }

    $stmt_link = $pdo->prepare("SELECT COUNT(*) FROM PasajerosReservas WHERE ID_pasajero = ? AND ID_reserva = ?");
    $stmt_link->execute([$pasajeroId, $id]);
    if ((int)$stmt_link->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)")->execute([$pasajeroId, $id]);
    }
    return (int)$id;
}

$pdo->beginTransaction();

try {
    $activo = demo_user($pdo, 'demo.activo@moveon.local', 'Demo', 'Activo', '90000001');
    $suspendido = demo_user($pdo, 'demo.suspendido@moveon.local', 'Demo', 'Suspendido', '90000002', $now->modify('+10 days')->format('Y-m-d H:i:s'));
    demo_user($pdo, 'deleted_demo_profesor@deleted.moveon.local', 'Demo', 'Eliminado', 'deleted_demo_profesor');

    $pasajeroUsuario = demo_user($pdo, 'demo.pasajero@moveon.local', 'Paula', 'Pasajera', '90000003');
    $pasajeroId = demo_pasajero($pdo, $pasajeroUsuario);
    $activoPasajeroId = demo_pasajero($pdo, $activo);
    $suspendidoPasajeroId = demo_pasajero($pdo, $suspendido);

    $conductorPendUser = demo_user($pdo, 'demo.conductor.pendiente@moveon.local', 'Carlos', 'Pendiente', '90000004');
    $conductorOkUser = demo_user($pdo, 'demo.conductor.activo@moveon.local', 'Andres', 'Activo', '90000005');
    $conductorSuspUser = demo_user($pdo, 'demo.conductor.suspendido@moveon.local', 'Sofia', 'Suspendida', '90000006');
    $conductorElimUser = demo_user($pdo, 'demo.conductor.eliminado@moveon.local', 'Mario', 'Rechazado', '90000007');

    $condPend = demo_conductor($pdo, $conductorPendUser, 'Esperando');
    $condOk = demo_conductor($pdo, $conductorOkUser, 'Aceptada');
    $condSusp = demo_conductor($pdo, $conductorSuspUser, 'Aceptada', $now->modify('+7 days')->format('Y-m-d H:i:s'));
    $condElim = demo_conductor($pdo, $conductorElimUser, 'Rechazado');

    demo_vehicle($pdo, $condPend, 'DEM-001', 'Pendiente', 'Fiat', 'Cronos');
    $vehOk = demo_vehicle($pdo, $condOk, 'DEM-002', 'Aceptado', 'Toyota', 'Etios');
    demo_vehicle($pdo, $condOk, 'DEM-005', 'Suspendido', 'Chevrolet', 'Onix');
    demo_vehicle($pdo, $condSusp, 'DEM-003', 'Suspendido', 'Renault', 'Logan');
    demo_vehicle($pdo, $condElim, 'DEM-004', 'Rechazado', 'Ford', 'Ka');

    $viajeActivo = demo_publicacion($pdo, $condOk, $vehOk, 'Parana', 'Crespo', $now->modify('+3 days')->format('Y-m-d H:i:s'), 'Activa', 3500);
    $viajeFinalizado = demo_publicacion($pdo, $condOk, $vehOk, 'Nogoya', 'Parana', $now->modify('-5 days')->format('Y-m-d H:i:s'), 'Finalizada', 4200);
    cancelar_publicaciones_demo_duplicadas($pdo, $condOk, $vehOk, 'Parana', 'Crespo', $viajeActivo);
    cancelar_publicaciones_demo_duplicadas($pdo, $condOk, $vehOk, 'Nogoya', 'Parana', $viajeFinalizado);
    demo_reserva($pdo, $viajeActivo, $activoPasajeroId, $activo, 'DEMO-ACT-01');
    demo_reserva($pdo, $viajeActivo, $pasajeroId, $pasajeroUsuario, 'DEMO-ACT-02');
    $reserva = demo_reserva($pdo, $viajeFinalizado, $pasajeroId, $pasajeroUsuario, 'DEMO-HIST-01');

    $stmt_emanuel = $pdo->prepare("SELECT ID_usuario FROM Usuarios WHERE Correo = ? LIMIT 1");
    $stmt_emanuel->execute(['emanuel.angel.rebecchi@gmail.com']);
    $emanuelId = (int)($stmt_emanuel->fetchColumn() ?: 0);
    if ($emanuelId > 0) {
        $emanuelPasajero = demo_pasajero($pdo, $emanuelId);
        $viajeEmanuel = demo_publicacion($pdo, $condOk, $vehOk, 'Crespo', 'Parana', $now->modify('-4 days')->format('Y-m-d H:i:s'), 'Finalizada', 3900);
        cancelar_publicaciones_demo_duplicadas($pdo, $condOk, $vehOk, 'Crespo', 'Parana', $viajeEmanuel);
        $reservaEmanuel = demo_reserva($pdo, $viajeEmanuel, $emanuelPasajero, $emanuelId, 'DEMO-EMA-01');
        $pdo->prepare("DELETE FROM ConfirmacionesViaje WHERE ID_reserva = ?")->execute([$reservaEmanuel]);
        $pdo->prepare("DELETE FROM Calificaciones WHERE ID_reserva = ?")->execute([$reservaEmanuel]);

        $actionUrl = '/proyecto_taller/public/reservas/confirmar_llegada.php?reserva_id=' . $reservaEmanuel;
        $reportUrl = '/proyecto_taller/public/reportar.php?conductor_id=' . $condOk . '&publicacion_id=' . $viajeEmanuel;
        $mensaje = 'Demo profesor: tu viaje de Crespo a Parana ya finalizo. Confirma tu llegada para calificar al conductor o reporta un problema si corresponde.';
        $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM Notificaciones WHERE ID_usuario = ? AND AccionURL = ?");
        $stmt_notif->execute([$emanuelId, $actionUrl]);
        if ((int)$stmt_notif->fetchColumn() === 0) {
            $pdo->prepare("
                INSERT INTO Notificaciones (ID_usuario, Mensaje, AccionURL, AccionLabel, AccionSecundariaURL, AccionSecundariaLabel, Leida)
                VALUES (?, ?, ?, 'Confirmar llegada', ?, 'Reportar conductor', 0)
            ")->execute([$emanuelId, $mensaje, $actionUrl, $reportUrl]);
        }
    }

    $stmt_pago = $pdo->prepare("SELECT COUNT(*) FROM Pagos WHERE ID_reserva = ?");
    $stmt_pago->execute([$reserva]);
    if ((int)$stmt_pago->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO Pagos (Monto, Estado, ID_reserva) VALUES (4200, 'Completado', ?)")->execute([$reserva]);
    }

    foreach ([
        ['Demo: problema con reserva', 'El pasajero no puede ver el codigo de acceso.', 'Pendiente'],
        ['Demo: consulta resuelta', 'Caso de ejemplo ya resuelto para revision.', 'Resuelto'],
    ] as $ticket) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Soporte WHERE ID_usuario = ? AND Asunto = ?");
        $stmt->execute([$pasajeroUsuario, $ticket[0]]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO Soporte (ID_usuario, Asunto, Mensaje, Estado) VALUES (?, ?, ?, ?)")
                ->execute([$pasajeroUsuario, $ticket[0], $ticket[1], $ticket[2]]);
        }
    }

    $stmt_rep = $pdo->prepare("SELECT COUNT(*) FROM Reportes WHERE ID_publicacion = ? AND Descripcion = ?");
    $stmt_rep->execute([$viajeFinalizado, 'Demo: el conductor llego tarde y no aviso.']);
    if ((int)$stmt_rep->fetchColumn() === 0) {
        $pdo->prepare("INSERT INTO Reportes (Hora, Fecha, Descripcion, ID_conductor, ID_publicacion, ID_usuario_reportante) VALUES ('10:30:00', CURDATE(), ?, ?, ?, ?)")
            ->execute(['Demo: el conductor llego tarde y no aviso.', $condOk, $viajeFinalizado, $pasajeroUsuario]);
    }

    $stmt_rep_pas = $pdo->prepare("SELECT COUNT(*) FROM ReportesPasajeros WHERE ID_reserva = ? AND Motivo = ?");
    $stmt_rep_pas->execute([$reserva, 'Demo revision']);
    if ((int)$stmt_rep_pas->fetchColumn() === 0) {
        $pdo->prepare("
            INSERT INTO ReportesPasajeros (ID_reserva, ID_usuario_reportado, ID_usuario_responsable, ID_conductor, Motivo, Descripcion)
            VALUES (?, ?, ?, ?, 'Demo revision', 'Demo: pasajero no se presento en el punto acordado.')
        ")->execute([$reserva, $pasajeroUsuario, $conductorOkUser, $condOk]);
    }

    $pdo->commit();
    echo "Datos demo de admin listos.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
