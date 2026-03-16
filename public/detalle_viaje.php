<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_GET['id'])) {
    die("Viaje no especificado.");
}
$viaje_id = (int) $_GET['id'];

$sql = "
    SELECT v.*, 
           u.nombre AS conductor_nombre, u.foto_perfil,
           c1.nombre AS origen_nombre,
           c2.nombre AS destino_nombre,
           veh.marca, veh.modelo, veh.color, veh.patente, veh.foto AS vehiculo_foto, veh.asientos,
           (SELECT COUNT(*) FROM reservas r WHERE r.viaje_id = v.id AND r.estado = 'activa') as ocupados,
           (SELECT AVG(puntaje) FROM calificaciones calif WHERE calif.conductor_id = v.conductor_id) as promedio_calif
    FROM viajes v
    JOIN conductores c ON v.conductor_id = c.id
    JOIN usuarios u ON c.usuario_id = u.id
    JOIN vehiculos veh ON v.vehiculo_id = veh.id
    JOIN ciudades c1 ON v.origen_id = c1.id
    JOIN ciudades c2 ON v.destino_id = c2.id
    WHERE v.id = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje no existe.");
}
$asientos_disponibles = $viaje['asientos'] - $viaje['ocupados'];
?>
<?php require_once __DIR__ . '/header.php'; ?>

    <style>
        .detail-box { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .flex-container { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 300px; }
    </style>

    <h2>Detalle del Viaje</h2>
    <a href="<?= BASE_URL ?>index.php" style="margin-bottom: 20px; display: inline-block;">← Volver al inicio</a>

    <div class="flex-container">
        <div class="col detail-box">
            <h3 style="margin-top:0; color:var(--primary);">Ruta y Horario</h3>
            <p><strong>Origen:</strong> <?= htmlspecialchars($viaje['origen_nombre']) ?></p>
            <p><strong>Destino:</strong> <?= htmlspecialchars($viaje['destino_nombre']) ?></p>
            <p><strong>Fecha y Hora:</strong> <?= date('d/m/Y H:i', strtotime($viaje['fecha'])) ?></p>
            <?php if (isset($viaje['distancia_km']) && $viaje['distancia_km'] > 0): ?>
                <p><strong>Distancia:</strong> <?= $viaje['distancia_km'] ?> km</p>
                <p><strong>Duración Estimada:</strong> <?= htmlspecialchars($viaje['duracion_estimada'] ?? 'N/A') ?></p>
            <?php endif; ?>
            <p style="font-size: 1.1em;"><strong>Precio:</strong> <span style="color:var(--success); font-weight:bold;">$<?= number_format($viaje['precio'], 2) ?></span></p>
            <p><strong>Asientos disponibles:</strong> <?= max(0, $asientos_disponibles) ?> de <?= $viaje['asientos'] ?></p>
            <p><strong>Observaciones:</strong> <em><?= nl2br(htmlspecialchars($viaje['observaciones'] ?? 'Sin observaciones')) ?></em></p>
        </div>

        <div class="col detail-box">
            <h3 style="margin-top:0; color:var(--primary);">Conductor</h3>
            <p><strong>Nombre:</strong> <?= htmlspecialchars($viaje['conductor_nombre']) ?></p>
            <p><strong>Calificación:</strong> 
                <?= $viaje['promedio_calif'] ? number_format($viaje['promedio_calif'], 1) . ' ⭐' : 'Sin calificaciones aún' ?>
            </p>
            <hr>
            <h3 style="color:var(--primary);">Vehículo asignado</h3>
            <p><strong>Modelo:</strong> <?= htmlspecialchars($viaje['marca'] . ' ' . $viaje['modelo']) ?></p>
            <p><strong>Color:</strong> <?= htmlspecialchars($viaje['color']) ?></p>
            <p><strong>Patente:</strong> <?= htmlspecialchars($viaje['patente']) ?></p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <p><a href="<?= BASE_URL ?>login.php" class="btn">Iniciá sesión para reservar</a></p>
        <?php elseif ($asientos_disponibles <= 0): ?>
            <button disabled style="padding: 12px 25px;">El viaje está lleno</button>
        <?php elseif (isset($_SESSION['is_conductor']) && $_SESSION['is_conductor'] && isset($_SESSION['conductor_id']) && $_SESSION['conductor_id'] == $viaje['conductor_id']): ?>
            <p style="color: #64748b;"><em>⚠️ Este es tu viaje publicado.</em></p>
        <?php else: ?>
            <a href="<?= BASE_URL ?>reservar_viaje.php?id=<?= $viaje['id'] ?>" class="btn success-bg" style="padding: 12px 25px; font-size: 1.1em; display: inline-block;">Comenzar Reserva</a>
        <?php endif; ?>
    </div>
</body>
</html>
