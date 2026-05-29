<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/account_lifecycle.php';

// Procesar acciones de aprobar/rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['conductor_id'])) {
    require_csrf();
    $conductor_id = (int)$_POST['conductor_id'];
    $accion = $_POST['accion']; // 'aprobar' o 'rechazar'
    
    if ($accion === 'aprobar') {
        $stmt = $pdo->prepare("UPDATE Conductores SET Estado = 'Aceptada' WHERE ID_conductor = ?");
        $stmt->execute([$conductor_id]);
        $msg = "Conductor aprobado con Ã©xito.";
    } elseif ($accion === 'rechazar' || $accion === 'eliminar') {
        try {
            $pdo->beginTransaction();

            // 1. Buscar viajes vinculados para reembolsar
            $stmt_pub = $pdo->prepare("SELECT p.ID_publicacion, p.Precio, p.CiudadOrigen, p.CiudadDestino FROM Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion WHERE cp.ID_conductor = ?");
            $stmt_pub->execute([$conductor_id]);
            $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

            foreach ($publicaciones as $pub) {
                // Reembolsar a pasajeros
                $stmt_res = $pdo->prepare("
                    SELECT u.ID_usuario
                    FROM Reservas r
                    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
                    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
                    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
                    WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
                ");
                $stmt_res->execute([$pub['ID_publicacion']]);
                $reservas = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

                foreach ($reservas as $res) {
                    if (PAYMENTS_ENABLED) {
                        $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")->execute([$pub['Precio'], $res['ID_usuario']]);
                    }
                    $mensaje = "El conductor de tu viaje (" . $pub['CiudadOrigen'] . " â†’ " . $pub['CiudadDestino'] . ") ha sido eliminado por la administraciÃ³n. Se reembolsaron $" . number_format($pub['Precio'], 2) . ".";
                    if (!PAYMENTS_ENABLED) {
                        $mensaje = "El conductor de tu viaje (" . $pub['CiudadOrigen'] . " -> " . $pub['CiudadDestino'] . ") ha sido eliminado por la administracion.";
                    }
                    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$res['ID_usuario'], $mensaje]);
                }
                
                // Marcar publicaciÃ³n como cancelada
                $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$pub['ID_publicacion']]);
            }

            // 2. Obtener vehÃ­culos para borrar despuÃ©s
            $stmt_vehiculo = $pdo->prepare("SELECT ID_vehiculo FROM ConductorVehiculo WHERE ID_conductor = ?");
            $stmt_vehiculo->execute([$conductor_id]);
            $vehiculos = $stmt_vehiculo->fetchAll(PDO::FETCH_ASSOC);

            // 3. Borrar el conductor (borrado fÃ­sico del conductor segÃºn lo actual)
            if ($accion === 'eliminar') {
                $pdo->prepare("UPDATE Conductores SET Estado = 'Eliminado', BaneadoHasta = NULL WHERE ID_conductor = ?")->execute([$conductor_id]);
            } else {
                $pdo->prepare("UPDATE Conductores SET Estado = 'Rechazado', BaneadoHasta = NULL WHERE ID_conductor = ?")->execute([$conductor_id]);
            }

            // 4. Borrar vehÃ­culos
            foreach ($vehiculos as $v) {
                if ($accion === 'eliminar') {
                    $pdo->prepare("UPDATE Vehiculos SET Estado = 'Rechazado' WHERE ID_vehiculo = ?")->execute([$v['ID_vehiculo']]);
                }
            }

            $pdo->commit();
            $msg = ($accion === 'rechazar') ? "Conductor rechazado y viajes cancelados." : "Conductor eliminado y viajes cancelados.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    } elseif ($accion === 'banear_conductor') {
        $fecha_ban = $_POST['fecha_ban'] ?? '';
        if (!empty($fecha_ban)) {
            try {
                $pdo->beginTransaction();
                
                $stmt_ban = $pdo->prepare("UPDATE Conductores SET BaneadoHasta = ? WHERE ID_conductor = ?");
                $stmt_ban->execute([$fecha_ban, $conductor_id]);
                
                // Buscar viajes activos para cancelar y reembolsar
                $stmt_viajes = $pdo->prepare("SELECT p.ID_publicacion FROM Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion WHERE cp.ID_conductor = ? AND p.Estado = 'Activa' AND p.HoraSalida >= NOW() AND p.HoraSalida <= ?");
                $stmt_viajes->execute([$conductor_id, $fecha_ban]);
                $viajes_activos = $stmt_viajes->fetchAll(PDO::FETCH_COLUMN);

                foreach ($viajes_activos as $publicacion_id) {
                    cancel_publication_with_refunds($pdo, (int)$publicacion_id, 'El conductor fue suspendido temporalmente por la administracion.');
                    continue;
                    $stmt_res = $pdo->prepare("
                        SELECT u.ID_usuario
                        FROM Reservas r
                        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
                        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
                        JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
                        WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
                    ");
                    $stmt_res->execute([$v['ID_publicacion']]);
                    $pasajeros = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($pasajeros as $pas) {
                        $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")->execute([$v['Precio'], $pas['ID_usuario']]);
                        $mensaje = "El conductor de tu viaje (" . $v['CiudadOrigen'] . " â†’ " . $v['CiudadDestino'] . ") ha sido suspendido temporalmente. Se han reembolsado $" . number_format($v['Precio'], 2) . ".";
                        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$pas['ID_usuario'], $mensaje]);
                    }
                    
                    $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$v['ID_publicacion']]);
                }

                $pdo->commit();
                $msg = "Conductor suspendido correctamente hasta el $fecha_ban. Se cancelaron solo los viajes dentro del periodo de suspension.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "Error: " . $e->getMessage();
            }
        }
    }

}

// Filtro de bÃºsqueda
$search = $_GET['search'] ?? '';
$tipo_conductores = ($_GET['tipo'] ?? 'pendientes') === 'aprobados' ? 'aprobados' : 'pendientes';
$estado_aprobados = $_GET['estado'] ?? 'activos';
if (!in_array($estado_aprobados, ['activos', 'suspendidos', 'eliminados'], true)) {
    $estado_aprobados = 'activos';
}
$search_sql = '';
$params_pendientes = [];
$params_aceptados = [];

if ($search !== '') {
    $search_sql = " AND (u.Nombre LIKE ? OR u.DNI LIKE ? OR u.Correo LIKE ?) ";
    $params_pendientes = ["%$search%", "%$search%", "%$search%"];
    $params_aceptados = ["%$search%", "%$search%", "%$search%"];
}

$stmt_total_pendientes = $pdo->prepare("
    SELECT COUNT(*) FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.Estado = 'Esperando' $search_sql
");
$stmt_total_pendientes->execute($params_pendientes);
$total_pendientes = (int)$stmt_total_pendientes->fetchColumn();

// Obtener la lista de conductores pendientes y sus vehÃ­culos
$sql1 = "
    SELECT c.ID_conductor AS id, c.LicenciaConducir, c.SeguroVehiculo, c.CuentaBancaria, c.Estado, c.FechaRegistro AS creado_en,
           c.TelefonoContacto, c.AliasMP, c.FotoCarnet, c.FotoCara,
           u.ID_usuario AS usuario_id, u.Nombre AS nombre, u.Correo AS email, u.DNI,
           v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, 
           v.Foto AS vehiculo_doc, v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN ConductorVehiculo cv ON c.ID_conductor = cv.ID_conductor
    LEFT JOIN Vehiculos v ON cv.ID_vehiculo = v.ID_vehiculo
    WHERE c.Estado = 'Esperando' $search_sql
    ORDER BY c.FechaRegistro DESC
";
$stmt = $pdo->prepare($sql1);
$stmt->execute($params_pendientes);
$pendientes = $stmt->fetchAll();

// PaginaciÃ³n para Aceptados
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$aprobados_estado_sql = "c.Estado = 'Aceptada' AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW())";
if ($estado_aprobados === 'suspendidos') {
    $aprobados_estado_sql = "c.Estado = 'Aceptada' AND c.BaneadoHasta IS NOT NULL AND c.BaneadoHasta > NOW()";
} elseif ($estado_aprobados === 'eliminados') {
    $aprobados_estado_sql = "c.Estado IN ('Eliminado', 'Rechazado')";
}

$count_estado_sql = "
    SELECT
        SUM(CASE WHEN c.Estado = 'Aceptada' AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW()) THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN c.Estado = 'Aceptada' AND c.BaneadoHasta IS NOT NULL AND c.BaneadoHasta > NOW() THEN 1 ELSE 0 END) AS suspendidos,
        SUM(CASE WHEN c.Estado IN ('Eliminado', 'Rechazado') THEN 1 ELSE 0 END) AS eliminados
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.Estado <> 'Esperando' $search_sql
";
$stmt_estado_count = $pdo->prepare($count_estado_sql);
$stmt_estado_count->execute($params_aceptados);
$totales_estado = $stmt_estado_count->fetch(PDO::FETCH_ASSOC) ?: ['activos' => 0, 'suspendidos' => 0, 'eliminados' => 0];
$total_activos = (int)$totales_estado['activos'];
$total_suspendidos = (int)$totales_estado['suspendidos'];
$total_eliminados = (int)$totales_estado['eliminados'];

$count_sql = "
    SELECT COUNT(*) FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE $aprobados_estado_sql $search_sql
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params_aceptados);
$total_filtrado_aprobados = (int)$stmt_count->fetchColumn();
$total_aceptados = $total_activos + $total_suspendidos + $total_eliminados;
$total_paginas = ceil($total_filtrado_aprobados / $limite);

// Obtener la lista de conductores aceptados
$sql2 = "
    SELECT c.ID_conductor AS id, c.LicenciaConducir, c.SeguroVehiculo, c.CuentaBancaria, c.Estado, c.FechaRegistro AS creado_en, c.BaneadoHasta,
           c.TelefonoContacto, c.AliasMP, c.FotoCarnet, c.FotoCara,
           u.ID_usuario AS usuario_id, u.Nombre AS nombre, u.Correo AS email, u.DNI,
           v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, 
           v.Foto AS vehiculo_doc, v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN ConductorVehiculo cv ON c.ID_conductor = cv.ID_conductor
    LEFT JOIN Vehiculos v ON cv.ID_vehiculo = v.ID_vehiculo
    WHERE $aprobados_estado_sql $search_sql
    ORDER BY c.FechaRegistro DESC
    LIMIT $limite OFFSET $offset
";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($params_aceptados);
$aceptados = $stmt2->fetchAll();
require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2>Conductores</h2>
    <p>Aqui puedes buscar y revisar las solicitudes y conductores activos.</p>
    
    <div class="tabs" style="max-width:520px; margin:20px 0 24px;">
        <a href="conductores.php?tipo=pendientes<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" class="tab <?= $tipo_conductores === 'pendientes' ? 'active' : '' ?>">
            Pendientes <span class="badge badge-orange" style="margin-left:8px;"><?= $total_pendientes ?></span>
        </a>
        <a href="conductores.php?tipo=aprobados&estado=<?= urlencode($estado_aprobados) ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#conductores-aprobados" class="tab <?= $tipo_conductores === 'aprobados' ? 'active' : '' ?>">
            Aprobados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_aceptados ?></span>
        </a>
    </div>

    <form method="GET" action="conductores.php<?= $tipo_conductores === 'aprobados' ? '#conductores-aprobados' : '' ?>" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 500px;">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_conductores) ?>">
        <?php if ($tipo_conductores === 'aprobados'): ?>
            <input type="hidden" name="estado" value="<?= htmlspecialchars($estado_aprobados) ?>">
        <?php endif; ?>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por Nombre, DNI o Correo" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Buscar</button>
        <?php if($search): ?>
            <a href="conductores.php?tipo=<?= urlencode($tipo_conductores) ?>&estado=<?= urlencode($estado_aprobados) ?><?= $tipo_conductores === 'aprobados' ? '#conductores-aprobados' : '' ?>" style="padding: 10px; background-color: #ccc; color: black; border-radius: 4px; text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (isset($msg)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <?php if ($tipo_conductores === 'pendientes'): ?>
    <?php if (empty($pendientes)): ?>
        <p>No hay solicitudes de conductores pendientes.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Perfil y Licencia</th>
                    <th>VehÃ­culo Inicial</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendientes as $c): ?>
                <tr>
                    <td>
                        <strong>Nom:</strong> <?= htmlspecialchars($c['nombre']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($c['email']) ?><br>
                        <strong>ID:</strong> <?= $c['usuario_id'] ?>
                    </td>
                    <td>
                        <ul class="details-list">
                            <li><strong>Tel:</strong> <?= htmlspecialchars($c['TelefonoContacto'] ?? '---') ?></li>
                            <li><strong>Licencia NÂ°:</strong> <?= htmlspecialchars($c['LicenciaConducir']) ?></li>
                            <li><strong>Seguro policial:</strong> <?= htmlspecialchars($c['SeguroVehiculo']) ?></li>
                            <li><strong>CBU Bancario:</strong> <?= htmlspecialchars($c['CuentaBancaria']) ?></li>
                            <li><strong>Alias MP:</strong> <?= htmlspecialchars($c['AliasMP'] ?? '---') ?></li>
                            
                            <?php if($c['FotoCara']): ?>
                                <li style="margin-top: 5px;"><strong>Foto Cara:</strong><br><img src="<?= $c['FotoCara'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                            <?php if($c['FotoCarnet']): ?>
                                <li style="margin-top: 5px;"><strong>Carnet Conducir:</strong><br><img src="<?= $c['FotoCarnet'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                        </ul>
                    </td>
                    <td>
                        <?php if($c['marca']): ?>
                            <ul class="details-list">
                                <li><strong>Auto:</strong> <?= htmlspecialchars($c['marca'] . ' ' . $c['modelo']) ?></li>
                                <li><strong>Color:</strong> <?= htmlspecialchars($c['color']) ?></li>
                                <li><strong>Asientos disp:</strong> <?= $c['asientos'] ?></li>
                                
                                <li style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px;">
                                    <?php if($c['PapelesAuto']): ?>
                                        <div><small>Papeles</small><br><img src="<?= $c['PapelesAuto'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($c['FotoFrente']): ?>
                                        <div><small>Frente</small><br><img src="<?= $c['FotoFrente'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($c['FotoCostado']): ?>
                                        <div><small>Costado</small><br><img src="<?= $c['FotoCostado'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($c['FotoAtras']): ?>
                                        <div><small>AtrÃ¡s</small><br><img src="<?= $c['FotoAtras'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <em>No registrÃ³ vehÃ­culo</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="margin-bottom: 5px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conductor_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar" onclick="return confirm('Â¿Aprobar a este conductor?');">Aprobar</button>
                        </form>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conductor_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('Â¿Rechazar a este conductor?');">Rechazar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($tipo_conductores === 'aprobados'): ?>
    <h2 id="conductores-aprobados">Conductores Aprobados</h2>
    <p>Lista de conductores activos en la plataforma. Puedes eliminarlos si infringen las reglas.</p>

    <div class="tabs" style="max-width:720px; margin:20px 0 24px;">
        <a href="conductores.php?tipo=aprobados&estado=activos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#conductores-aprobados" class="tab <?= $estado_aprobados === 'activos' ? 'active' : '' ?>">
            Activos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_activos ?></span>
        </a>
        <a href="conductores.php?tipo=aprobados&estado=suspendidos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#conductores-aprobados" class="tab <?= $estado_aprobados === 'suspendidos' ? 'active' : '' ?>">
            Suspendidos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_suspendidos ?></span>
        </a>
        <a href="conductores.php?tipo=aprobados&estado=eliminados<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#conductores-aprobados" class="tab <?= $estado_aprobados === 'eliminados' ? 'active' : '' ?>">
            Eliminados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_eliminados ?></span>
        </a>
    </div>

    <?php if (empty($aceptados)): ?>
        <p>No hay conductores en este filtro.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Perfil y Licencia</th>
                    <th>VehÃ­culo Asociado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aceptados as $a): ?>
                <tr>
                    <td>
                        <strong>Nom:</strong> <?= htmlspecialchars($a['nombre']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($a['email']) ?><br>
                        <strong>ID:</strong> <?= $a['usuario_id'] ?>
                    </td>
                    <td>
                        <ul class="details-list">
                            <li><strong>Tel:</strong> <?= htmlspecialchars($a['TelefonoContacto'] ?? '---') ?></li>
                            <li><strong>Licencia NÂ°:</strong> <?= htmlspecialchars($a['LicenciaConducir']) ?></li>
                            <li><strong>Seguro policial:</strong> <?= htmlspecialchars($a['SeguroVehiculo']) ?></li>
                            <li><strong>CBU Bancario:</strong> <?= htmlspecialchars($a['CuentaBancaria']) ?></li>
                            <li><strong>Alias MP:</strong> <?= htmlspecialchars($a['AliasMP'] ?? '---') ?></li>
                            <li><strong>Registrado el:</strong> <?= htmlspecialchars($a['creado_en']) ?></li>
                            
                            <?php if ($a['BaneadoHasta'] && strtotime($a['BaneadoHasta']) > time()): ?>
                                <li><strong style="color:red;">Suspendido hasta:</strong><br><span style="color:red;"><?= date('d/m/Y H:i', strtotime($a['BaneadoHasta'])) ?></span></li>
                            <?php endif; ?>

                            <?php if (in_array($a['Estado'], ['Eliminado', 'Rechazado'], true)): ?>
                                <li><strong style="color:red;">Estado:</strong> <?= htmlspecialchars($a['Estado']) ?></li>
                            <?php endif; ?>

                            <?php if($a['FotoCara']): ?>
                                <li style="margin-top: 5px;"><strong>Foto Cara:</strong><br><img src="<?= $a['FotoCara'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                            <?php if($a['FotoCarnet']): ?>
                                <li style="margin-top: 5px;"><strong>Carnet Conducir:</strong><br><img src="<?= $a['FotoCarnet'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                        </ul>
                    </td>
                    <td>
                        <?php if($a['marca']): ?>
                            <ul class="details-list">
                                <li><strong>Auto:</strong> <?= htmlspecialchars($a['marca'] . ' ' . $a['modelo']) ?></li>
                                <li><strong>Color:</strong> <?= htmlspecialchars($a['color']) ?></li>
                                <li><strong>Asientos disp:</strong> <?= $a['asientos'] ?></li>
                                
                                <li style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px;">
                                    <?php if($a['PapelesAuto']): ?>
                                        <div><small>Papeles</small><br><img src="<?= $a['PapelesAuto'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($a['FotoFrente']): ?>
                                        <div><small>Frente</small><br><img src="<?= $a['FotoFrente'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($a['FotoCostado']): ?>
                                        <div><small>Costado</small><br><img src="<?= $a['FotoCostado'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($a['FotoAtras']): ?>
                                        <div><small>AtrÃ¡s</small><br><img src="<?= $a['FotoAtras'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <em>No registrÃ³ vehÃ­culo</em>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                        <?php if (in_array($a['Estado'], ['Eliminado', 'Rechazado'], true)): ?>
                            <span class="badge badge-orange">Eliminado permanente</span>
                        <?php else: ?>
                        <form method="post" style="margin-bottom: 5px; text-align: left; background: #f9f9f9; padding: 5px; border: 1px solid #ddd;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conductor_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="accion" value="banear_conductor">
                            <label style="font-size: 0.8em; font-weight: bold;">Suspender conductor hasta:</label><br>
                            <input type="datetime-local" name="fecha_ban" required style="width: 100%; box-sizing: border-box; margin-bottom: 5px; font-size: 0.85em;">
                            <button type="submit" style="background-color: #f0ad4e; color: white; padding: 4px; border: none; cursor: pointer; border-radius: 3px; width: 100%; font-size: 0.85em;">Suspender</button>
                        </form>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conductor_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="btn-rechazar" style="width: 100%; font-size: 0.85em;" onclick="return confirm('Â¿Seguro que deseas ELIMINAR a este conductor de la plataforma de forma permanente? Se borrarÃ¡n sus viajes y vehÃ­culos.');">Eliminar Permanente</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (isset($total_paginas) && $total_paginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?tipo=aprobados&estado=<?= urlencode($estado_aprobados) ?>&pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>#conductores-aprobados">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?tipo=aprobados&estado=<?= urlencode($estado_aprobados) ?>&pagina=<?= $i ?>&search=<?= urlencode($search) ?>#conductores-aprobados" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?tipo=aprobados&estado=<?= urlencode($estado_aprobados) ?>&pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>#conductores-aprobados">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal para ver imÃ¡genes en tamaÃ±o completo -->
<div id="imageModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <span onclick="document.getElementById('imageModal').style.display='none'" style="position:absolute; top:20px; right:35px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="modalImage" style="max-width:90%; max-height:90%; object-fit:contain; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
</div>
<script>
function openModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'flex';
}
document.querySelectorAll('.details-list img').forEach(img => {
    img.style.cursor = 'pointer';
    img.onclick = () => openModal(img.src);
});
// Cerrar modal al clickear fuera de la imagen
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

</body>
</html>
