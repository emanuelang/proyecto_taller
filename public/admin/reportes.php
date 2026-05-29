<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/account_lifecycle.php';

function conductor_suspension_reason_from_report(PDO $pdo, int $conductor_id, int $reporte_id = 0): string
{
    $params = [$conductor_id];
    $report_filter = '';
    if ($reporte_id > 0) {
        $report_filter = ' AND r.ID_reporte = ?';
        $params[] = $reporte_id;
    }

    $stmt = $pdo->prepare("
        SELECT r.ID_reporte, r.Descripcion, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida
        FROM Reportes r
        LEFT JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        WHERE r.ID_conductor = ? $report_filter
        ORDER BY r.Fecha DESC, r.Hora DESC
        LIMIT 1
    ");
    $stmt->execute($params);
    $reporte = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reporte && $reporte_id > 0) {
        return conductor_suspension_reason_from_report($pdo, $conductor_id, 0);
    }

    if ($reporte) {
        $viaje = trim((string)($reporte['CiudadOrigen'] ?? '')) !== ''
            ? " Viaje: {$reporte['CiudadOrigen']} -> {$reporte['CiudadDestino']} (" . date('d/m/Y H:i', strtotime($reporte['HoraSalida'])) . ")."
            : '';
        $detalle = trim((string)($reporte['Descripcion'] ?? ''));
        return "Motivo: reporte #" . (int)$reporte['ID_reporte'] . "." . $viaje . " Detalle: " . ($detalle !== '' ? $detalle : 'Sin detalle adicional.');
    }

    return "Motivo: decision de un administrador.";
}

function notify_conductor_suspension(PDO $pdo, int $conductor_id, string $fecha_ban, string $reason): void
{
    $stmt = $pdo->prepare("SELECT ID_usuario FROM Conductores WHERE ID_conductor = ?");
    $stmt->execute([$conductor_id]);
    $usuario_id = (int)($stmt->fetchColumn() ?: 0);
    if ($usuario_id <= 0) {
        return;
    }

    $mensaje = "Tu cuenta de conductor fue suspendida hasta " . date('d/m/Y H:i', strtotime($fecha_ban)) . ". " . $reason;
    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$usuario_id, $mensaje]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    require_csrf();

    if ($_POST['accion'] === 'eliminar_reporte' && isset($_POST['reporte_id'])) {
        $stmt_del = $pdo->prepare("DELETE FROM Reportes WHERE ID_reporte = ?");
        $stmt_del->execute([(int)$_POST['reporte_id']]);
        $msg_exito = "Reporte descartado.";
    } elseif ($_POST['accion'] === 'eliminar_reporte_pasajero' && isset($_POST['reporte_pasajero_id'])) {
        $stmt_del = $pdo->prepare("DELETE FROM ReportesPasajeros WHERE ID_reporte_pasajero = ?");
        $stmt_del->execute([(int)$_POST['reporte_pasajero_id']]);
        $msg_exito = "Reporte de pasajero descartado.";
    } elseif ($_POST['accion'] === 'suspender_usuario_reportado' && isset($_POST['usuario_id'])) {
        $usuario_target = (int)$_POST['usuario_id'];
        $fecha_ban = $_POST['fecha_ban'] ?? '';

        if ($fecha_ban !== '') {
            if ($usuario_target === (int)$_SESSION['user_id']) {
                $msg_exito = "No puedes aplicarte sanciones a ti mismo.";
            } else {
                $stmt_check_admin = $pdo->prepare("SELECT COUNT(*) FROM Administradores WHERE ID_usuario = ?");
                $stmt_check_admin->execute([$usuario_target]);
                if ((int)$stmt_check_admin->fetchColumn() > 0) {
                    $msg_exito = "No puedes sancionar a otro administrador.";
                } else {
                    $stmt_ban = $pdo->prepare("UPDATE Usuarios SET BaneadoHasta = ? WHERE ID_usuario = ?");
                    $stmt_ban->execute([$fecha_ban, $usuario_target]);
                    $msg_exito = "Usuario suspendido temporalmente hasta el $fecha_ban.";
                }
            }
        }
    } elseif ($_POST['accion'] === 'sancionar_usuario_reportado' && isset($_POST['usuario_id'])) {
        $usuario_target = (int)$_POST['usuario_id'];

        if ($usuario_target === (int)$_SESSION['user_id']) {
            $msg_exito = "No puedes aplicarte sanciones a ti mismo.";
        } else {
            $stmt_check_admin = $pdo->prepare("SELECT COUNT(*) FROM Administradores WHERE ID_usuario = ?");
            $stmt_check_admin->execute([$usuario_target]);
            if ((int)$stmt_check_admin->fetchColumn() > 0) {
                $msg_exito = "No puedes sancionar a otro administrador.";
            } else {
                try {
                    $pdo->beginTransaction();
                    deactivate_user_account($pdo, $usuario_target, 'El usuario fue sancionado permanentemente por administracion.');
                    $pdo->commit();
                    $msg_exito = "Usuario eliminado permanentemente. Sus viajes y reservas activas fueron cancelados.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log("Error sancionando usuario desde reportes: " . $e->getMessage());
                    $msg_exito = "No se pudo eliminar permanentemente al usuario.";
                }
            }
        }
    } elseif ($_POST['accion'] === 'sancionar_conductor' && isset($_POST['conductor_id'])) {
        $conductor_target = (int)$_POST['conductor_id'];
        $stmt_ban = $pdo->prepare("UPDATE Conductores SET Estado = 'Eliminado', BaneadoHasta = NULL WHERE ID_conductor = ?");
        $stmt_ban->execute([$conductor_target]);

        $stmt_cancel_viajes = $pdo->prepare("
            UPDATE Publicaciones p
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            SET p.Estado = 'Cancelada'
            WHERE cp.ID_conductor = ?
        ");
        $stmt_cancel_viajes->execute([$conductor_target]);
        $msg_exito = "Conductor eliminado permanentemente y viajes cancelados.";
    } elseif ($_POST['accion'] === 'suspender_conductor' && isset($_POST['conductor_id'])) {
        $conductor_target = (int)$_POST['conductor_id'];
        $fecha_ban = $_POST['fecha_ban'] ?? '';
        $reporte_origen = (int)($_POST['reporte_id'] ?? 0);

        if ($fecha_ban !== '') {
            $pdo->beginTransaction();
            $reason = conductor_suspension_reason_from_report($pdo, $conductor_target, $reporte_origen);
            $stmt_ban = $pdo->prepare("UPDATE Conductores SET BaneadoHasta = ? WHERE ID_conductor = ?");
            $stmt_ban->execute([$fecha_ban, $conductor_target]);

            $stmt_suspend_vehiculos = $pdo->prepare("
                UPDATE Vehiculos v
                JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
                SET v.Estado = 'Suspendido'
                WHERE cv.ID_conductor = ?
                  AND v.Estado = 'Aceptado'
            ");
            $stmt_suspend_vehiculos->execute([$conductor_target]);

            $stmt_viajes = $pdo->prepare("
                SELECT p.ID_publicacion
                FROM Publicaciones p
                JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
                WHERE cp.ID_conductor = ?
                  AND p.Estado = 'Activa'
                  AND p.HoraSalida >= NOW()
                  AND p.HoraSalida <= ?
            ");
            $stmt_viajes->execute([$conductor_target, $fecha_ban]);
            foreach ($stmt_viajes->fetchAll(PDO::FETCH_COLUMN) as $publicacion_id) {
                cancel_publication_with_refunds($pdo, (int)$publicacion_id, 'El conductor fue suspendido temporalmente por la administracion.');
            }

            notify_conductor_suspension($pdo, $conductor_target, $fecha_ban, $reason);
            $pdo->commit();
            $msg_exito = "Conductor suspendido temporalmente hasta el $fecha_ban. Tambien se suspendieron sus vehiculos aprobados.";
        }
    }
}

$tipo_reportes = ($_GET['tipo'] ?? 'conductores') === 'pasajeros' ? 'pasajeros' : 'conductores';
$publicacion_filtro = isset($_GET['publicacion_id']) ? max(0, (int)$_GET['publicacion_id']) : 0;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;

$where_conductores = '';
$params_conductores = [];
if ($publicacion_filtro > 0) {
    $where_conductores = 'WHERE r.ID_publicacion = ?';
    $params_conductores[] = $publicacion_filtro;
}

$stmt_total_conductores = $pdo->prepare("SELECT COUNT(*) FROM Reportes r $where_conductores");
$stmt_total_conductores->execute($params_conductores);
$total_registros = (int)$stmt_total_conductores->fetchColumn();
$total_paginas = (int)ceil($total_registros / $limite);

$stmt = $pdo->prepare("
    SELECT r.ID_reporte AS id, r.Fecha, r.Hora, r.Descripcion,
           c.ID_conductor AS conductor_id,
           u.Nombre AS conductor_nombre, u.Apellido AS conductor_apellido,
           c.Estado AS conductor_estado,
           u_rep.Nombre AS reportante_nombre, u_rep.Apellido AS reportante_apellido,
           u_rep.DNI AS reportante_dni, u_rep.Telefono AS reportante_telefono,
           u_rep.Correo AS reportante_correo,
           p.CiudadOrigen, p.CiudadDestino, p.HoraSalida
    FROM Reportes r
    JOIN Conductores c ON r.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN Usuarios u_rep ON r.ID_usuario_reportante = u_rep.ID_usuario
    LEFT JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
    $where_conductores
    ORDER BY r.Fecha DESC, r.Hora DESC
    LIMIT $limite OFFSET $offset
");
$stmt->execute($params_conductores);
$reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$where_pasajeros = '';
$params_pasajeros = [];
if ($publicacion_filtro > 0) {
    $where_pasajeros = 'WHERE p.ID_publicacion = ?';
    $params_pasajeros[] = $publicacion_filtro;
}

$stmt_reportes_pasajeros = $pdo->prepare("
    SELECT rp.ID_reporte_pasajero AS id, rp.Fecha, rp.Motivo, rp.Descripcion, rp.Estado,
           rp.ID_reserva,
           u_reportado.ID_usuario AS reportado_usuario_id,
           u_reportado.Nombre AS reportado_nombre, u_reportado.Apellido AS reportado_apellido,
           u_reportado.DNI AS reportado_dni, u_reportado.Telefono AS reportado_telefono,
           u_reportado.Correo AS reportado_correo, u_reportado.BaneadoHasta AS reportado_baneado_hasta,
           u_resp.Nombre AS responsable_nombre, u_resp.Apellido AS responsable_apellido,
           u_resp.DNI AS responsable_dni, u_resp.Telefono AS responsable_telefono,
           u_cond.Nombre AS conductor_nombre, u_cond.Apellido AS conductor_apellido,
           u_cond.DNI AS conductor_dni, u_cond.Telefono AS conductor_telefono, u_cond.Correo AS conductor_correo,
           p.CiudadOrigen, p.CiudadDestino, p.HoraSalida
    FROM ReportesPasajeros rp
    JOIN Usuarios u_reportado ON rp.ID_usuario_reportado = u_reportado.ID_usuario
    LEFT JOIN Usuarios u_resp ON rp.ID_usuario_responsable = u_resp.ID_usuario
    JOIN Conductores c ON rp.ID_conductor = c.ID_conductor
    JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
    JOIN Reservas r ON rp.ID_reserva = r.ID_reserva
    JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
    $where_pasajeros
    ORDER BY rp.Fecha DESC
    LIMIT 20
");
$stmt_reportes_pasajeros->execute($params_pasajeros);
$reportes_pasajeros = $stmt_reportes_pasajeros->fetchAll(PDO::FETCH_ASSOC);
$stmt_total_pasajeros = $pdo->prepare("
    SELECT COUNT(*)
    FROM ReportesPasajeros rp
    JOIN Reservas r ON rp.ID_reserva = r.ID_reserva
    " . ($publicacion_filtro > 0 ? "WHERE r.ID_publicacion = ?" : "")
);
$stmt_total_pasajeros->execute($params_pasajeros);
$total_reportes_pasajeros = (int)$stmt_total_pasajeros->fetchColumn();

require_once __DIR__ . '/../header.php';
include __DIR__ . '/_nav.php';
?>

<div style="padding: 20px;">
    <h2>Reportes de usuarios</h2>
    <p>Lista de reportes enviados en la plataforma. La identidad del reportante es visible para administracion, pero no para la persona reportada.</p>

    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if ($publicacion_filtro > 0): ?>
        <div class="card" style="background:#eff6ff; color:#1d4ed8;">
            Mostrando reportes del viaje #<?= $publicacion_filtro ?>.
            <a href="viajes.php?tipo=finalizados" style="margin-left:12px;">Volver a viajes finalizados</a>
            <a href="reportes.php?tipo=<?= $tipo_reportes ?>" style="margin-left:12px;">Ver todos los reportes</a>
        </div>
    <?php endif; ?>

    <div class="tabs" style="max-width:520px; margin:20px 0 24px;">
        <a href="reportes.php?tipo=conductores<?= $publicacion_filtro > 0 ? '&publicacion_id=' . $publicacion_filtro : '' ?>" class="tab <?= $tipo_reportes === 'conductores' ? 'active' : '' ?>">
            Conductores <span class="badge badge-orange" style="margin-left:8px;"><?= $total_registros ?></span>
        </a>
        <a href="reportes.php?tipo=pasajeros<?= $publicacion_filtro > 0 ? '&publicacion_id=' . $publicacion_filtro : '' ?>" class="tab <?= $tipo_reportes === 'pasajeros' ? 'active' : '' ?>">
            Pasajeros <span class="badge badge-orange" style="margin-left:8px;"><?= $total_reportes_pasajeros ?></span>
        </a>
    </div>

    <?php if ($tipo_reportes === 'pasajeros'): ?>
        <h2>Reportes de pasajeros</h2>
        <p class="results-count"><?= $total_reportes_pasajeros ?> total</p>

        <?php if (empty($reportes_pasajeros)): ?>
            <p>No hay reportes de pasajeros pendientes o recientes.</p>
        <?php else: ?>
            <div style="overflow-x:auto; max-width:100%;">
                <table class="table-admin" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Reportante</th>
                            <th>Pasajero / responsable</th>
                            <th>Viaje</th>
                            <th>Motivo</th>
                            <th style="width:250px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportes_pasajeros as $rp): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($rp['Fecha'])) ?></td>
                            <td>
                                <strong><?= htmlspecialchars(trim($rp['conductor_nombre'] . ' ' . $rp['conductor_apellido'])) ?></strong><br>
                                DNI: <?= htmlspecialchars($rp['conductor_dni'] ?: 'Sin DNI') ?><br>
                                Tel: <?= htmlspecialchars($rp['conductor_telefono'] ?: 'Sin telefono') ?><br>
                                <span class="text-muted"><?= htmlspecialchars($rp['conductor_correo'] ?: 'Sin correo') ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars(trim($rp['reportado_nombre'] . ' ' . $rp['reportado_apellido'])) ?></strong><br>
                                DNI: <?= htmlspecialchars($rp['reportado_dni'] ?: 'Sin DNI') ?> - Tel: <?= htmlspecialchars($rp['reportado_telefono'] ?: 'Sin telefono') ?>
                                <br><span class="text-muted"><?= htmlspecialchars($rp['reportado_correo'] ?: 'Sin correo') ?></span>
                                <?php if (!empty($rp['reportado_baneado_hasta']) && strtotime($rp['reportado_baneado_hasta']) > time()): ?>
                                    <br><span class="badge badge-orange" style="margin-top:6px;">Suspendido hasta <?= date('d/m/Y H:i', strtotime($rp['reportado_baneado_hasta'])) ?></span>
                                <?php endif; ?>
                                <?php if ($rp['responsable_nombre']): ?>
                                    <br><span class="text-muted">Responsable: <?= htmlspecialchars(trim($rp['responsable_nombre'] . ' ' . $rp['responsable_apellido'])) ?> - DNI <?= htmlspecialchars($rp['responsable_dni'] ?: 'Sin DNI') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($rp['CiudadOrigen']) ?> -> <?= htmlspecialchars($rp['CiudadDestino']) ?></strong><br>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($rp['HoraSalida'])) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($rp['Motivo']) ?></strong><br>
                                <span class="text-muted"><?= nl2br(htmlspecialchars($rp['Descripcion'] ?: 'Sin detalle adicional.')) ?></span>
                            </td>
                            <td>
                                <form method="post" style="display:inline-block; width:100%;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reporte_pasajero_id" value="<?= (int)$rp['id'] ?>">
                                    <input type="hidden" name="accion" value="eliminar_reporte_pasajero">
                                    <button type="submit" class="btn-rechazar" style="width:100%;" onclick="return confirm('Descartar este reporte?');">Descartar</button>
                                </form>
                                <form method="post" style="display:inline-block; margin-top:6px; text-align:left; background:#f9f9f9; padding:5px; border:1px solid #ddd; width:100%; box-sizing:border-box;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="usuario_id" value="<?= (int)$rp['reportado_usuario_id'] ?>">
                                    <input type="hidden" name="accion" value="suspender_usuario_reportado">
                                    <label style="font-size:0.8em; font-weight:bold;">Suspender usuario:</label><br>
                                    <input type="datetime-local" name="fecha_ban" required style="width:100%; box-sizing:border-box; margin-bottom:5px; font-size:0.85em;">
                                    <button type="submit" style="background-color:#f0ad4e; color:white; padding:4px; border:none; cursor:pointer; border-radius:3px; width:100%; font-size:0.85em;">Suspender usuario</button>
                                </form>
                                <form method="post" style="display:inline-block; margin-top:6px; width:100%;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="usuario_id" value="<?= (int)$rp['reportado_usuario_id'] ?>">
                                    <input type="hidden" name="accion" value="sancionar_usuario_reportado">
                                    <button type="submit" class="btn-sancionar" style="background-color:#333; width:100%; font-size:0.85em;" onclick="return confirm('Esto eliminara permanentemente al usuario reportado. Proceder?');">Eliminar usuario</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($reportes)): ?>
            <p>No hay reportes recientes.</p>
        <?php else: ?>
            <table class="table-admin">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha y hora</th>
                        <th>Reportante</th>
                        <th>Conductor reportado</th>
                        <th>Viaje</th>
                        <th>Detalle del reporte</th>
                        <th style="width:250px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportes as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($r['Fecha'])) ?><br>A las <?= substr($r['Hora'], 0, 5) ?></td>
                        <td>
                            <?php if ($r['reportante_nombre']): ?>
                                <strong><?= htmlspecialchars(trim($r['reportante_nombre'] . ' ' . $r['reportante_apellido'])) ?></strong><br>
                                DNI: <?= htmlspecialchars($r['reportante_dni'] ?: 'Sin DNI') ?><br>
                                Tel: <?= htmlspecialchars($r['reportante_telefono'] ?: 'Sin telefono') ?><br>
                                <span class="text-muted"><?= htmlspecialchars($r['reportante_correo'] ?: 'Sin correo') ?></span>
                            <?php else: ?>
                                <span class="text-muted">Sin reportante registrado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars($r['conductor_nombre'] . ' ' . $r['conductor_apellido']) ?></strong><br>
                            Estado actual: <?= htmlspecialchars($r['conductor_estado']) ?>
                        </td>
                        <td>
                            <?php if (!empty($r['CiudadOrigen'])): ?>
                                <strong><?= htmlspecialchars($r['CiudadOrigen']) ?> -> <?= htmlspecialchars($r['CiudadDestino']) ?></strong><br>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($r['HoraSalida'])) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Sin viaje asociado</span>
                            <?php endif; ?>
                        </td>
                        <td><?= nl2br(htmlspecialchars($r['Descripcion'])) ?></td>
                        <td style="text-align: center;">
                            <form method="post" style="display:inline-block; margin-bottom: 5px; width: 100%;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reporte_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="accion" value="eliminar_reporte">
                                <button type="submit" class="btn-rechazar" style="width: 100%;" onclick="return confirm('Descartar este reporte?');">Descartar</button>
                            </form>

                            <form method="post" style="display:inline-block; margin-bottom: 5px; text-align: left; background: #f9f9f9; padding: 5px; border: 1px solid #ddd; width: 100%; box-sizing: border-box;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="reporte_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="conductor_id" value="<?= (int)$r['conductor_id'] ?>">
                                <input type="hidden" name="accion" value="suspender_conductor">
                                <label style="font-size: 0.8em; font-weight: bold;">Suspender temporalmente:</label><br>
                                <input type="datetime-local" name="fecha_ban" required style="width: 100%; box-sizing: border-box; margin-bottom: 5px; font-size: 0.85em;">
                                <button type="submit" style="background-color: #f0ad4e; color: white; padding: 4px; border: none; cursor: pointer; border-radius: 3px; width: 100%; font-size: 0.85em;">Suspender conductor</button>
                            </form>

                            <form method="post" style="display:inline-block; width: 100%;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="conductor_id" value="<?= (int)$r['conductor_id'] ?>">
                                <input type="hidden" name="accion" value="sancionar_conductor">
                                <button type="submit" class="btn-sancionar" style="background-color: #333; width: 100%; font-size: 0.85em;" onclick="return confirm('Esto eliminara permanentemente al conductor. Proceder?');">Eliminar conductor</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($tipo_reportes === 'conductores' && $total_paginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="?tipo=conductores<?= $publicacion_filtro > 0 ? '&publicacion_id=' . $publicacion_filtro : '' ?>&pagina=<?= $pagina - 1 ?>">&laquo; Anterior</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?tipo=conductores<?= $publicacion_filtro > 0 ? '&publicacion_id=' . $publicacion_filtro : '' ?>&pagina=<?= $i ?>" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
                <a href="?tipo=conductores<?= $publicacion_filtro > 0 ? '&publicacion_id=' . $publicacion_filtro : '' ?>&pagina=<?= $pagina + 1 ?>">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
