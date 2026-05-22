<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';

if (!isset($_GET['id'])) {
    die('Viaje no especificado.');
}

$viaje_id = (int) $_GET['id'];

$sql = "
    SELECT p.ID_publicacion AS id,
           p.CiudadOrigen AS origen_nombre,
           p.CiudadDestino AS destino_nombre,
           p.CalleSalida AS calle_salida,
           p.HoraSalida AS fecha,
           p.Precio AS precio,
           p.Estado AS estado,
           p.DistanciaKM AS distancia_km,
           p.DuracionMinutos AS duracion_minutos,
           u.Nombre AS conductor_nombre,
           u.FotoPerfil AS foto_perfil,
           veh.Marca AS marca,
           veh.Modelo AS modelo,
           veh.Color AS color,
           veh.Patente AS patente,
           veh.FotoCostado AS vehiculo_foto,
           veh.CantidadAsientos AS asientos,
           (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') AS ocupados,
           (SELECT AVG(Puntuacion) FROM Calificaciones calif WHERE calif.ID_conductor = c.ID_conductor) AS promedio_calif,
           c.ID_conductor AS conductor_id,
           NULL AS observaciones
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    JOIN Vehiculos veh ON p.ID_vehiculo = veh.ID_vehiculo
    WHERE p.ID_publicacion = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die('El viaje no existe.');
}

$asientos_disponibles = (int)$viaje['asientos'] - (int)$viaje['ocupados'];
$asientos_disponibles = max(0, $asientos_disponibles);

$ya_reservado = false;
if (isset($_SESSION['user_id'])) {
    $stmt_ya = $pdo->prepare("
        SELECT COUNT(*)
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        WHERE r.ID_publicacion = ? AND pas.ID_usuario = ? AND r.Estado = 'Completada'
    ");
    $stmt_ya->execute([$viaje_id, $_SESSION['user_id']]);
    $ya_reservado = (int)$stmt_ya->fetchColumn() > 0;
}

$promedio = $viaje['promedio_calif']
    ? number_format(floor((float)$viaje['promedio_calif'] * 10) / 10, 1, ',', '.')
    : null;

$inicial_conductor = strtoupper(substr((string)$viaje['conductor_nombre'], 0, 1));
$fecha_salida = date('d/m/Y H:i', strtotime($viaje['fecha']));
$llegada_estimada = null;
if (!empty($viaje['distancia_km']) && (float)$viaje['distancia_km'] > 0 && !empty($viaje['duracion_minutos'])) {
    $llegada_estimada = date('d/m/Y H:i', strtotime($viaje['fecha']) + ((int)$viaje['duracion_minutos'] * 60));
}

require_once __DIR__ . '/header.php';
?>

<div class="page-shell">
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:26px;">
        <div>
            <h1 class="page-title">Detalle del viaje</h1>
            <p class="page-subtitle"><?= htmlspecialchars($viaje['origen_nombre']) ?> a <?= htmlspecialchars($viaje['destino_nombre']) ?></p>
        </div>
        <a href="<?= BASE_URL ?>index.php" class="btn btn-outline">Volver al inicio</a>
    </div>

    <div class="detail-layout">
        <section class="detail-box">
            <div class="detail-card-head">
                <h2>Ruta y horario</h2>
                <span class="trip-price">$<?= number_format((float)$viaje['precio'], 0, ',', '.') ?></span>
            </div>

            <div class="trip-route">
                <div>
                    <small>Salida</small>
                    <strong><?= htmlspecialchars($viaje['origen_nombre']) ?></strong>
                </div>
                <div class="route-arrow">→</div>
                <div style="text-align:right;">
                    <small>Llegada</small>
                    <strong><?= htmlspecialchars($viaje['destino_nombre']) ?></strong>
                </div>
            </div>

            <?php if (!empty($viaje['calle_salida'])): ?>
                <div class="info-tile" style="margin-bottom:18px;">
                    <span>Punto de encuentro</span>
                    <strong><?= htmlspecialchars($viaje['calle_salida']) ?></strong>
                </div>
            <?php endif; ?>

            <div class="detail-callout">
                <div>
                    <span class="text-muted">Fecha de salida</span>
                    <strong style="display:block; margin-top:8px;"><?= $fecha_salida ?> hs</strong>
                </div>
                <div>
                    <span class="text-muted">Llegada estimada</span>
                    <?php if ($llegada_estimada): ?>
                        <strong style="display:block; margin-top:8px;"><?= $llegada_estimada ?> hs</strong>
                        <small class="text-muted">Distancia aproximada: <?= ceil((float)$viaje['distancia_km']) ?> km</small>
                    <?php else: ?>
                        <strong class="text-muted" style="display:block; margin-top:8px;">No disponible por el momento</strong>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-meta-grid" style="margin-top:18px;">
                <div class="info-tile">
                    <span>Disponibilidad</span>
                    <strong>Quedan <?= $asientos_disponibles ?> de <?= (int)$viaje['asientos'] ?> asientos</strong>
                </div>
                <div class="info-tile">
                    <span>Estado</span>
                    <strong><?= htmlspecialchars($viaje['estado']) ?></strong>
                </div>
            </div>

            <?php if (!empty($viaje['observaciones'])): ?>
                <div class="info-tile" style="margin-top:18px;">
                    <span>Observaciones del conductor</span>
                    <strong><?= nl2br(htmlspecialchars($viaje['observaciones'])) ?></strong>
                </div>
            <?php endif; ?>
        </section>

        <aside class="detail-stack">
            <section class="detail-box">
                <div class="detail-card-head">
                    <h3>Conductor</h3>
                    <span class="badge badge-success">Cuenta verificada</span>
                </div>

                <div class="detail-person">
                    <div class="detail-avatar-wrap">
                        <?php if (!empty($viaje['foto_perfil'])): ?>
                            <img src="<?= htmlspecialchars($viaje['foto_perfil']) ?>" class="detail-avatar" alt="Foto del conductor">
                        <?php else: ?>
                            <span class="detail-avatar"><?= htmlspecialchars($inicial_conductor) ?></span>
                        <?php endif; ?>
                        <span class="rating-pill"><?= $promedio ? '★ ' . $promedio : 'Nuevo' ?></span>
                    </div>
                    <div>
                        <h3 style="margin:0;"><?= htmlspecialchars($viaje['conductor_nombre']) ?></h3>
                        <p class="text-muted" style="margin:8px 0 0;">Perfil validado por MOVEON</p>
                    </div>
                </div>
            </section>

            <section class="detail-box">
                <div class="detail-card-head">
                    <h3>Vehiculo asignado</h3>
                </div>

                <div class="vehicle-summary">
                    <div>
                        <div class="info-tile" style="margin-bottom:12px;">
                            <span>Modelo</span>
                            <strong><?= htmlspecialchars(trim($viaje['marca'] . ' ' . $viaje['modelo'])) ?></strong>
                        </div>
                        <div class="info-tile">
                            <span>Color</span>
                            <strong><?= htmlspecialchars($viaje['color']) ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($viaje['vehiculo_foto'])): ?>
                        <img src="<?= htmlspecialchars($viaje['vehiculo_foto']) ?>" class="vehicle-photo" alt="Foto del vehiculo">
                    <?php else: ?>
                        <div class="vehicle-photo" style="display:flex; align-items:center; justify-content:center; color:var(--text-muted); font-weight:800;">Auto</div>
                    <?php endif; ?>
                </div>

                <div class="plate">
                    <span class="text-muted" style="font-weight:800; text-transform:uppercase;">Matricula</span>
                    <strong><?= htmlspecialchars($viaje['patente']) ?></strong>
                </div>
            </section>
        </aside>
    </div>

    <div class="reservation-status">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="<?= BASE_URL ?>login.php" class="btn">Inicia sesion para reservar</a>
        <?php elseif ($ya_reservado): ?>
            <div class="detail-box" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534;">
                <h3 style="margin:0 0 8px; color:#166534;">Asiento confirmado</h3>
                <p style="margin:0;">Ya formas parte de este viaje. Revisá tus reservas para ver más detalles.</p>
            </div>
        <?php elseif ($asientos_disponibles <= 0): ?>
            <button disabled>Viaje agotado</button>
        <?php elseif (!empty($_SESSION['is_conductor']) && isset($_SESSION['conductor_id']) && (int)$_SESSION['conductor_id'] === (int)$viaje['conductor_id']): ?>
            <div class="detail-box" style="border-style:dashed;">
                <strong>Este viaje fue publicado por vos.</strong>
            </div>
        <?php else: ?>
            <form method="POST" action="<?= BASE_URL ?>reservar_viaje.php" style="padding:0; border:0; box-shadow:none;">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$viaje['id'] ?>">
                <button type="submit" class="btn success-bg" style="min-width:260px;">Continuar reserva</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
