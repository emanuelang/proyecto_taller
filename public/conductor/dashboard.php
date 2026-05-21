<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

/* Datos del conductor */
$stmt = $pdo->prepare("
    SELECT u.Nombre as nombre, u.Correo as email, c.Estado as estado
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$conductor = $stmt->fetch();

/* VehÃ­culos */
$stmt = $pdo->prepare("
    SELECT v.*
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE cv.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE cp.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$total_viajes = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Reservas r
    JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE cp.ID_conductor = ? AND r.Estado = 'Completada'
");
$stmt->execute([$_SESSION['conductor_id']]);
$pasajeros_llevados = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(Puntuacion) FROM Calificaciones WHERE ID_conductor = ?");
$stmt->execute([$_SESSION['conductor_id']]);
$promedio = $stmt->fetchColumn();
$promedio = $promedio ? number_format((float)$promedio, 1, '.', '') : 'Nuevo';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div class="page-shell">
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:20px; margin-bottom:28px; flex-wrap:wrap;">
            <div class="driver-chip">
                <span class="avatar" style="background:#dbeafe; color:var(--primary); width:70px; height:70px; font-size:26px;">
                    <?= htmlspecialchars(strtoupper(substr($conductor['nombre'], 0, 1))) ?>
                </span>
                <div>
                    <h2 style="margin:0 0 8px;"><?= htmlspecialchars($conductor['nombre']) ?></h2>
                    <span class="badge badge-success">Cuenta verificada</span>
                </div>
            </div>
            <a href="editar_perfil.php" class="btn btn-outline">Editar</a>
        </div>

        <div class="info-grid">
            <div class="info-tile">
                <span>Nombre</span>
                <strong><?= htmlspecialchars($conductor['nombre']) ?></strong>
            </div>
            <div class="info-tile">
                <span>Correo electrónico</span>
                <strong><?= htmlspecialchars($conductor['email']) ?></strong>
            </div>
            <div class="info-tile">
                <span>Estado de la cuenta</span>
                <strong><?= htmlspecialchars($conductor['estado']) ?></strong>
            </div>
            <div class="info-tile">
                <span>Vehículos registrados</span>
                <strong><?= count($vehiculos) ?></strong>
            </div>
        </div>
    </div>

    <div class="kpi-container">
        <div class="kpi-card">
            <div class="brand-icon" style="margin:0 auto 14px;">🚗</div>
            <p class="kpi-value"><?= $total_viajes ?></p>
            <div class="kpi-title">Viajes publicados</div>
        </div>
        <div class="kpi-card">
            <div class="brand-icon" style="margin:0 auto 14px; background:#fff7ed; color:#f59e0b;">☆</div>
            <p class="kpi-value"><?= htmlspecialchars($promedio) ?></p>
            <div class="kpi-title">Calificación</div>
        </div>
        <div class="kpi-card">
            <div class="brand-icon" style="margin:0 auto 14px; background:#ecfdf5; color:var(--success);">♙</div>
            <p class="kpi-value"><?= $pasajeros_llevados ?></p>
            <div class="kpi-title">Pasajeros llevados</div>
        </div>
    </div>

    <div class="travel-grid" style="margin-top:22px;">
        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-top:0;">Vehículos registrados</h3>
            <?php if (empty($vehiculos)): ?>
                <p class="text-muted">No tenés vehículos registrados.</p>
            <?php else: ?>
                <ul class="details-list">
                    <?php foreach ($vehiculos as $v): ?>
                        <li><?= htmlspecialchars($v['Marca']) ?> <?= htmlspecialchars($v['Modelo']) ?> - <?= htmlspecialchars($v['Color']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a href="vehiculos.php" class="btn btn-outline" style="width:100%; margin-top:18px;">Administrar vehículos</a>
        </div>

        <div class="card" style="margin-bottom:0;">
            <h3 style="margin-top:0;">Mis viajes</h3>
            <p class="text-muted">Administrá los viajes que publicaste y creá nuevas salidas.</p>
            <a href="viajes.php" class="btn btn-outline" style="width:100%; margin-bottom:10px;">Ver mis viajes</a>
            <a href="<?= BASE_URL ?>crear_viaje.php" class="btn success-bg" style="width:100%;">Crear nuevo viaje</a>
        </div>
    </div>
</div>
</body>
</html>

