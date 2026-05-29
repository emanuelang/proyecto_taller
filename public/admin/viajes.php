<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/trips.php';

sync_finished_trips($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['viaje_id'])) {
    require_csrf();
    $viaje_target = (int)$_POST['viaje_id'];

    if ($_POST['accion'] === 'eliminar_viaje') {
        $stmt_del = $pdo->prepare("DELETE FROM Publicaciones WHERE ID_publicacion = ?");
        $stmt_del->execute([$viaje_target]);
        $msg_exito = "Viaje eliminado permanentemente del sistema.";
    }
}

$tipo_viajes = ($_GET['tipo'] ?? 'activos') === 'finalizados' ? 'finalizados' : 'activos';
$estado_filtro = $tipo_viajes === 'finalizados' ? 'Finalizada' : 'Activa';

$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM Publicaciones WHERE Estado = ?");
$stmt_count->execute([$estado_filtro]);
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = (int)ceil($total_registros / $limite);

$stmt_count_activos = $pdo->prepare("SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Activa'");
$stmt_count_activos->execute();
$total_activos = (int)$stmt_count_activos->fetchColumn();

$stmt_count_finalizados = $pdo->prepare("SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Finalizada'");
$stmt_count_finalizados->execute();
$total_finalizados = (int)$stmt_count_finalizados->fetchColumn();

$stmt = $pdo->prepare("
    SELECT p.ID_publicacion AS id, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida, p.Precio, p.Estado,
           u.Nombre, u.Apellido, v.Marca, v.Modelo, v.Patente,
           (SELECT COUNT(*) FROM Reportes rep WHERE rep.ID_publicacion = p.ID_publicacion) AS reportes_conductor,
           (
               SELECT COUNT(*)
               FROM ReportesPasajeros rp
               JOIN Reservas rr ON rp.ID_reserva = rr.ID_reserva
               WHERE rr.ID_publicacion = p.ID_publicacion
            ) AS reportes_pasajeros,
           (
                SELECT COUNT(*)
                FROM Reservas rr
                WHERE rr.ID_publicacion = p.ID_publicacion
                  AND rr.Estado = 'Completada'
           ) AS reservas_total,
           (
                SELECT COUNT(*)
                FROM ConfirmacionesViaje cv
                WHERE cv.ID_publicacion = p.ID_publicacion
                  AND cv.ConfirmoLlegada = 1
           ) AS confirmaciones_llegada
    FROM Publicaciones p
    LEFT JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    LEFT JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    LEFT JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    WHERE p.Estado = ?
    ORDER BY p.HoraSalida DESC
    LIMIT $limite OFFSET $offset
");
$stmt->execute([$estado_filtro]);
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$confirmaciones_por_viaje = [];
if ($tipo_viajes === 'finalizados' && !empty($viajes)) {
    $ids_viajes = array_map(static fn($v) => (int)$v['id'], $viajes);
    $placeholders = implode(',', array_fill(0, count($ids_viajes), '?'));
    $stmt_conf = $pdo->prepare("
        SELECT r.ID_publicacion, r.ID_reserva,
               COALESCE(NULLIF(r.PasajeroNombre, ''), u.Nombre) AS nombre,
               COALESCE(NULLIF(r.PasajeroApellido, ''), u.Apellido) AS apellido,
               cv.FechaConfirmacion
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
        JOIN ConfirmacionesViaje cv ON r.ID_reserva = cv.ID_reserva
        WHERE r.ID_publicacion IN ($placeholders)
          AND cv.ConfirmoLlegada = 1
        ORDER BY cv.FechaConfirmacion DESC
    ");
    $stmt_conf->execute($ids_viajes);
    foreach ($stmt_conf->fetchAll(PDO::FETCH_ASSOC) as $conf) {
        $confirmaciones_por_viaje[(int)$conf['ID_publicacion']][] = $conf;
    }
}

require_once __DIR__ . '/../header.php';
include __DIR__ . '/_nav.php';
?>

<div style="padding: 20px;">
    <h2>Gestion de viajes</h2>
    <p>Filtra los viajes activos y finalizados. En finalizados podes revisar si tuvieron reportes asociados.</p>

    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <div class="tabs" style="max-width:520px; margin:20px 0 24px;">
        <a href="viajes.php?tipo=activos" class="tab <?= $tipo_viajes === 'activos' ? 'active' : '' ?>">
            Activos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_activos ?></span>
        </a>
        <a href="viajes.php?tipo=finalizados" class="tab <?= $tipo_viajes === 'finalizados' ? 'active' : '' ?>">
            Finalizados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_finalizados ?></span>
        </a>
    </div>

    <?php if (empty($viajes)): ?>
        <p>No hay viajes <?= $tipo_viajes === 'finalizados' ? 'finalizados' : 'activos' ?> en el sistema.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ruta y fecha</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th>Conductor</th>
                    <th>Vehiculo</th>
                    <?php if ($tipo_viajes === 'finalizados'): ?>
                        <th>Confirmaciones</th>
                        <th>Reportes</th>
                    <?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viajes as $v): ?>
                <?php
                    $reportes_conductor = (int)$v['reportes_conductor'];
                    $reportes_pasajeros = (int)$v['reportes_pasajeros'];
                    $total_reportes_viaje = $reportes_conductor + $reportes_pasajeros;
                ?>
                <tr>
                    <td><?= (int)$v['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($v['CiudadOrigen']) ?> -> <?= htmlspecialchars($v['CiudadDestino']) ?></strong><br>
                        <?= date('d/m/Y H:i', strtotime($v['HoraSalida'])) ?>
                    </td>
                    <td>$<?= number_format((float)$v['Precio'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars($v['Estado']) ?></td>
                    <td><?= htmlspecialchars(trim(($v['Nombre'] ?? '---') . ' ' . ($v['Apellido'] ?? ''))) ?></td>
                    <td>
                        <?= htmlspecialchars($v['Marca'] ?? '???') ?> <?= htmlspecialchars($v['Modelo'] ?? '') ?><br>
                        <em><?= htmlspecialchars($v['Patente'] ?? 'Sin patente') ?></em>
                    </td>
                    <?php if ($tipo_viajes === 'finalizados'): ?>
                        <td>
                            <?php
                                $total_reservas = (int)$v['reservas_total'];
                                $total_confirmadas = (int)$v['confirmaciones_llegada'];
                                $confirmaciones = $confirmaciones_por_viaje[(int)$v['id']] ?? [];
                            ?>
                            <span class="badge badge-primary"><?= $total_confirmadas ?> / <?= $total_reservas ?> asistieron</span>
                            <?php if (!empty($confirmaciones)): ?>
                                <div style="margin-top:10px; display:grid; gap:6px;">
                                    <?php foreach ($confirmaciones as $conf): ?>
                                        <div class="text-muted" style="font-size:14px;">
                                            <strong><?= htmlspecialchars(trim($conf['nombre'] . ' ' . $conf['apellido'])) ?></strong><br>
                                            <?= date('d/m/Y H:i', strtotime($conf['FechaConfirmacion'])) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted" style="margin-top:8px;">Sin confirmaciones</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($total_reportes_viaje > 0): ?>
                                <span class="badge badge-orange"><?= $total_reportes_viaje ?> reporte(s)</span>
                                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;">
                                    <?php if ($reportes_conductor > 0): ?>
                                        <a href="reportes.php?tipo=conductores&publicacion_id=<?= (int)$v['id'] ?>" class="btn btn-outline" style="padding:8px 12px;">Conductores</a>
                                    <?php endif; ?>
                                    <?php if ($reportes_pasajeros > 0): ?>
                                        <a href="reportes.php?tipo=pasajeros&publicacion_id=<?= (int)$v['id'] ?>" class="btn btn-outline" style="padding:8px 12px;">Pasajeros</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">Sin reportes</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td style="text-align: center;">
                        <form method="post" style="display:inline-block;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="viaje_id" value="<?= (int)$v['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar_viaje">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('Estas seguro de eliminar este viaje permanentemente?');">Eliminar viaje</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($total_paginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?tipo=<?= $tipo_viajes ?>&pagina=<?= $pagina - 1 ?>">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?tipo=<?= $tipo_viajes ?>&pagina=<?= $i ?>" class="<?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?tipo=<?= $tipo_viajes ?>&pagina=<?= $pagina + 1 ?>">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</body>
</html>
