<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

$stmt_users = $pdo->query("
    SELECT COUNT(*)
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
    AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta < NOW())
    AND (u.Estado IS NULL OR u.Estado <> 'Inactivo')
");
$usuarios_activos = (int)$stmt_users->fetchColumn();

$total_viajes = (int)$pdo->query("SELECT COUNT(*) FROM Publicaciones")->fetchColumn();
$conductores_pendientes = (int)$pdo->query("SELECT COUNT(*) FROM Conductores WHERE Estado = 'Esperando'")->fetchColumn();

$total_pagos = (float)($pdo->query("SELECT COALESCE(SUM(Monto),0) FROM Pagos WHERE Estado = 'Completado'")->fetchColumn() ?: 0);
$rentabilidad_plataforma = $total_pagos * 0.10;

$inicio_mes = date('Y-m-01 00:00:00');
$inicio_mes_siguiente = date('Y-m-01 00:00:00', strtotime('first day of next month'));
$inicio_mes_anterior = date('Y-m-01 00:00:00', strtotime('first day of previous month'));

$stmt_mes = $pdo->prepare("SELECT COALESCE(SUM(Monto),0) FROM Pagos WHERE Estado = 'Completado' AND Fecha >= ? AND Fecha < ?");
$stmt_mes->execute([$inicio_mes, $inicio_mes_siguiente]);
$ingresos_mes = (float)$stmt_mes->fetchColumn();

$stmt_mes->execute([$inicio_mes_anterior, $inicio_mes]);
$ingresos_mes_anterior = (float)$stmt_mes->fetchColumn();

$ingresos_admin_mes = $ingresos_mes * 0.10;
$ingresos_admin_anterior = $ingresos_mes_anterior * 0.10;
$trend_up = $ingresos_admin_mes >= $ingresos_admin_anterior;
$max_chart = max($ingresos_admin_mes, $ingresos_admin_anterior, 1);
$bar_actual = max(8, (int)round(($ingresos_admin_mes / $max_chart) * 130));
$bar_anterior = max(8, (int)round(($ingresos_admin_anterior / $max_chart) * 130));
$diferencia = $ingresos_admin_mes - $ingresos_admin_anterior;
$porcentaje = $ingresos_admin_anterior > 0 ? ($diferencia / $ingresos_admin_anterior) * 100 : ($ingresos_admin_mes > 0 ? 100 : 0);
$chart_color = $trend_up ? '#009b6b' : '#ef4444';

$stmt_recientes = $pdo->query("
    SELECT ID_publicacion, CiudadOrigen, CiudadDestino, HoraSalida, Precio, Estado
    FROM Publicaciones
    ORDER BY HoraSalida DESC
    LIMIT 5
");
$viajes_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);

$stmt_pendientes = $pdo->query("
    SELECT c.ID_conductor, c.LicenciaConducir, u.Nombre, u.Apellido, u.Correo
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.Estado = 'Esperando'
    ORDER BY c.FechaRegistro DESC
    LIMIT 4
");
$pendientes = $stmt_pendientes->fetchAll(PDO::FETCH_ASSOC);

$db_size_mb = 0;
try {
    $stmt_db = $pdo->query("
        SELECT COALESCE(SUM(data_length + index_length),0) / 1024 / 1024
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
    ");
    $db_size_mb = (float)$stmt_db->fetchColumn();
} catch (Exception $e) {
    $db_size_mb = 0;
}

require_once __DIR__ . '/../header.php';
include __DIR__ . '/_nav.php';
?>

<div class="admin-grid">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'import_success'): ?>
        <div class="card" style="background:#f0fdf4; color:#047857;">Base de datos restaurada correctamente a partir del backup.</div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="card" style="background:#fff1f2; color:#be123c;">
            Error en la importación:
            <?php
                if ($_GET['error'] === 'upload_failed') echo "No se pudo subir el archivo.";
                elseif ($_GET['error'] === 'invalid_extension') echo "El archivo debe tener extensión .SQL.";
                elseif ($_GET['error'] === 'invalid_signature') echo "Firma inválida. Este archivo SQL no fue generado por el sistema.";
                elseif ($_GET['error'] === 'import_exception') echo "La consulta SQL contenía errores o era demasiado grande.";
                else echo "Error desconocido.";
            ?>
        </div>
    <?php endif; ?>

    <section class="admin-kpis">
        <article class="admin-kpi">
            <span class="admin-kpi-icon">♧</span>
            <p class="admin-kpi-value"><?= number_format($usuarios_activos) ?></p>
            <p class="admin-kpi-label">Usuarios activos</p>
            <div class="admin-kpi-note">Cuentas habilitadas</div>
        </article>

        <article class="admin-kpi">
            <span class="admin-kpi-icon" style="background:#f4efff; color:#7c3aed;">▰</span>
            <p class="admin-kpi-value"><?= number_format($total_viajes) ?></p>
            <p class="admin-kpi-label">Viajes registrados</p>
            <div class="admin-kpi-note">Histórico total</div>
        </article>

        <article class="admin-kpi">
            <span class="admin-kpi-icon" style="background:#fff7ed; color:#f59e0b;">⚠</span>
            <p class="admin-kpi-value"><?= number_format($conductores_pendientes) ?></p>
            <p class="admin-kpi-label">Conductores pendientes</p>
            <div class="admin-kpi-note">Requieren revisión</div>
        </article>

        <article class="admin-kpi">
            <span class="admin-kpi-icon" style="background:#ecfdf5; color:var(--success);">↗</span>
            <p class="admin-kpi-value">$<?= number_format($rentabilidad_plataforma, 0, ',', '.') ?></p>
            <p class="admin-kpi-label">Rentabilidad (10%)</p>
            <div class="admin-kpi-note">Comisiones cobradas</div>
        </article>
    </section>

    <section class="admin-dashboard-layout">
        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Ingresos del mes</h2>
                <span class="<?= $trend_up ? 'trend-up' : 'trend-down' ?>">
                    <?= $trend_up ? '↗' : '↘' ?> <?= number_format(abs($porcentaje), 1, ',', '.') ?>%
                </span>
            </div>
            <div class="admin-chart">
                <div class="admin-chart-summary">
                    <div>
                        <p class="admin-chart-value">$<?= number_format($ingresos_admin_mes, 2, ',', '.') ?></p>
                        <div class="text-muted">Comisión del mes actual vs mes anterior</div>
                    </div>
                    <div style="text-align:right;">
                        <strong class="<?= $trend_up ? 'trend-up' : 'trend-down' ?>">
                            <?= $trend_up ? '+' : '-' ?>$<?= number_format(abs($diferencia), 2, ',', '.') ?>
                        </strong>
                        <div class="text-muted">Diferencia</div>
                    </div>
                </div>
                <svg viewBox="0 0 520 180" width="100%" height="180" role="img" aria-label="Comparación de ingresos">
                    <line x1="42" y1="150" x2="490" y2="150" stroke="#dbe4f0" stroke-width="2"/>
                    <rect x="120" y="<?= 150 - $bar_anterior ?>" width="82" height="<?= $bar_anterior ?>" rx="12" fill="#cbd5e1"/>
                    <rect x="320" y="<?= 150 - $bar_actual ?>" width="82" height="<?= $bar_actual ?>" rx="12" fill="<?= $chart_color ?>"/>
                    <text x="161" y="172" text-anchor="middle" fill="#5d718f" font-size="14">Mes anterior</text>
                    <text x="361" y="172" text-anchor="middle" fill="#5d718f" font-size="14">Este mes</text>
                    <text x="161" y="<?= max(22, 142 - $bar_anterior) ?>" text-anchor="middle" fill="#07142b" font-size="15" font-weight="700">$<?= number_format($ingresos_admin_anterior, 0, ',', '.') ?></text>
                    <text x="361" y="<?= max(22, 142 - $bar_actual) ?>" text-anchor="middle" fill="#07142b" font-size="15" font-weight="700">$<?= number_format($ingresos_admin_mes, 0, ',', '.') ?></text>
                </svg>
            </div>
        </article>

        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Sistema</h2>
            </div>
            <div class="admin-system-body">
                <p style="font-size:20px; margin-top:0;"><span class="trend-up">↯</span> Estado: <strong class="trend-up">Operativo</strong></p>
                <div class="info-grid" style="grid-template-columns:1fr; gap:10px;">
                    <div style="display:flex; justify-content:space-between;"><span class="text-muted">Versión</span><strong>v1.4.2</strong></div>
                    <div style="display:flex; justify-content:space-between;"><span class="text-muted">DB size</span><strong><?= number_format($db_size_mb, 1, ',', '.') ?> MB</strong></div>
                </div>

                <a href="backup.php" class="btn" style="width:100%; margin-top:22px;" target="_blank">Exportar Backup</a>

                <form action="import_backup.php" method="POST" enctype="multipart/form-data" style="margin-top:14px;">
                    <?= csrf_field() ?>
                    <input type="file" name="backup_file" accept=".sql" required>
                    <button type="submit" class="btn btn-outline" style="width:100%;">Importar Backup</button>
                </form>
            </div>
        </article>
    </section>

    <section class="admin-dashboard-layout">
        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Viajes recientes</h2>
                <span class="results-count"><?= count($viajes_recientes) ?> total</span>
            </div>
            <?php if (empty($viajes_recientes)): ?>
                <div class="admin-list-row"><span class="text-muted">Todavía no hay viajes registrados.</span></div>
            <?php else: ?>
                <?php foreach ($viajes_recientes as $v): ?>
                    <div class="admin-list-row">
                        <strong><?= htmlspecialchars($v['CiudadOrigen']) ?> <span style="color:var(--text-muted);">→</span> <?= htmlspecialchars($v['CiudadDestino']) ?></strong>
                        <span class="text-muted"><?= date('d M Y', strtotime($v['HoraSalida'])) ?></span>
                        <strong class="trend-up">$<?= number_format($v['Precio'], 0, ',', '.') ?></strong>
                        <span class="badge <?= $v['Estado'] === 'Activa' ? 'badge-success' : 'badge-primary' ?>"><?= htmlspecialchars($v['Estado']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </article>

        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Atajos</h2>
            </div>
            <div class="admin-system-body">
                <a href="conductores.php" class="btn btn-outline" style="width:100%; margin-bottom:12px;">Revisar conductores</a>
                <a href="vehiculos.php" class="btn btn-outline" style="width:100%; margin-bottom:12px;">Revisar vehículos</a>
                <a href="pagos.php" class="btn btn-outline" style="width:100%;">Ver pagos</a>
            </div>
        </article>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>Conductores pendientes de aprobación</h2>
            <span class="badge badge-orange"><?= count($pendientes) ?></span>
        </div>

        <?php if (empty($pendientes)): ?>
            <div class="admin-pending-row">
                <span class="text-muted">No hay solicitudes pendientes.</span>
            </div>
        <?php else: ?>
            <?php foreach ($pendientes as $c): ?>
                <div class="admin-pending-row">
                    <div class="driver-chip">
                        <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($c['Nombre'], 0, 1))) ?></span>
                        <div>
                            <strong><?= htmlspecialchars(trim($c['Nombre'] . ' ' . $c['Apellido'])) ?></strong>
                            <div class="text-muted"><?= htmlspecialchars($c['Correo']) ?> · <?= htmlspecialchars($c['LicenciaConducir']) ?></div>
                        </div>
                    </div>
                    <div class="admin-actions">
                        <form method="POST" action="conductores.php">
                            <input type="hidden" name="conductor_id" value="<?= $c['ID_conductor'] ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar">Aprobar</button>
                        </form>
                        <form method="POST" action="conductores.php">
                            <input type="hidden" name="conductor_id" value="<?= $c['ID_conductor'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Rechazar este conductor?');">Rechazar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

</body>
</html>
