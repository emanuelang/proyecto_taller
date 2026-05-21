<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$vista = $_GET['vista'] ?? 'activos';
$vista = $vista === 'historial' ? 'historial' : 'activos';

$condicion_fecha = $vista === 'historial'
    ? "AND (p.HoraSalida < NOW() OR p.Estado IN ('Finalizada', 'Completada'))"
    : "AND p.HoraSalida >= NOW() AND p.Estado NOT IN ('Cancelada', 'Rechazada', 'Finalizada', 'Completada')";

$sql = "
    SELECT p.*,
           (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') AS pasajeros_confirmados
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE cp.ID_conductor = ?
    $condicion_fecha
    ORDER BY p.HoraSalida " . ($vista === 'historial' ? 'DESC' : 'ASC');

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['conductor_id']]);
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_nav.php';
?>

<div class="page-shell">
    <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:22px;">
        <div>
            <h2 style="margin:0;">Mis viajes</h2>
            <p class="text-muted" style="margin:6px 0 0;">Gestioná tus próximos viajes y revisá tu historial.</p>
        </div>
        <a href="<?= BASE_URL ?>crear_viaje.php" class="btn success-bg">Crear viaje</a>
    </div>

    <div class="tabs">
        <a href="viajes.php?vista=activos" class="tab <?= $vista === 'activos' ? 'active' : '' ?>">Activos</a>
        <a href="viajes.php?vista=historial" class="tab <?= $vista === 'historial' ? 'active' : '' ?>">Historial</a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="card" style="background:#f0fdf4; color:#047857;">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($viajes)): ?>
        <div class="card" style="text-align:center; padding:48px;">
            <h3 style="margin-top:0;"><?= $vista === 'activos' ? 'No tenés viajes activos' : 'Todavía no tenés viajes finalizados' ?></h3>
            <p class="text-muted">
                <?= $vista === 'activos'
                    ? 'Cuando publiques un viaje pendiente o próximo, va a aparecer acá.'
                    : 'Cuando tus viajes pasen su fecha de salida, se van a listar en este historial.' ?>
            </p>
            <?php if ($vista === 'activos'): ?>
                <a href="<?= BASE_URL ?>crear_viaje.php" class="btn" style="margin-top:12px;">Crear viaje</a>
            <?php else: ?>
                <a href="viajes.php?vista=activos" class="btn btn-outline" style="margin-top:12px;">Ver viajes activos</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="travel-grid">
            <?php foreach ($viajes as $v): ?>
                <article class="card trip-card" style="margin-bottom:0;">
                    <div>
                        <div class="trip-top">
                            <span class="badge <?= $vista === 'activos' ? 'badge-success' : 'badge-primary' ?>">
                                <?= $vista === 'activos' ? 'Activo' : 'Finalizado' ?>
                            </span>
                            <span class="trip-price">$<?= number_format($v['Precio'], 0, ',', '.') ?></span>
                        </div>

                        <div class="trip-route">
                            <div>
                                <small>Salida</small>
                                <strong><?= htmlspecialchars($v['CiudadOrigen']) ?></strong>
                            </div>
                            <div class="route-arrow">→</div>
                            <div style="text-align:right;">
                                <small>Llegada</small>
                                <strong><?= htmlspecialchars($v['CiudadDestino']) ?></strong>
                            </div>
                        </div>

                        <div class="trip-meta">
                            <span>▣ <?= date('d M Y', strtotime($v['HoraSalida'])) ?></span>
                            <span>◷ <?= date('H:i', strtotime($v['HoraSalida'])) ?> hs</span>
                            <span>♙ <?= (int)$v['pasajeros_confirmados'] ?> pasajeros</span>
                        </div>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px; margin-top:22px;">
                        <a href="<?= BASE_URL ?>conductor/ver_reservas.php?id=<?= $v['ID_publicacion'] ?>" class="btn success-bg">
                            Ver pasajeros / validar
                        </a>

                        <a href="<?= BASE_URL ?>crear_viaje.php?origen=<?= urlencode($v['CiudadOrigen']) ?>&destino=<?= urlencode($v['CiudadDestino']) ?>&precio=<?= urlencode($v['Precio']) ?>" class="btn btn-outline">
                            Reutilizar como plantilla
                        </a>

                        <?php if ($vista === 'activos'): ?>
                            <a href="<?= BASE_URL ?>conductor/eliminar_viaje.php?id=<?= $v['ID_publicacion'] ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este viaje? Se cancelarán reservas activas y se harán reembolsos cuando corresponda.')">
                                Eliminar viaje
                            </a>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $v['ID_publicacion'] ?>" class="btn btn-outline">
                                Ver detalle
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
