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
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Detalle del Viaje - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .detail-box { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .flex-container { display: flex; gap: 20px; flex-wrap: wrap; }
        .col { flex: 1; min-width: 300px; }
    </style>
</head>
<body>
    <h1>Detalle del Viaje</h1>
    <a href="<?= BASE_URL ?>index.php">← Volver al inicio</a>
    <hr>

    <div class="flex-container">
        <div class="col detail-box">
            <h2>Ruta y Horario</h2>
            <p><strong>Origen:</strong> <?= htmlspecialchars($viaje['origen_nombre']) ?></p>
            <p><strong>Destino:</strong> <?= htmlspecialchars($viaje['destino_nombre']) ?></p>
            <p><strong>Fecha y Hora:</strong> <?= $viaje['fecha'] ?></p>
            <?php if (isset($viaje['distancia_km']) && $viaje['distancia_km'] > 0): ?>
                <p><strong>Distancia:</strong> <?= $viaje['distancia_km'] ?> km</p>
                <p><strong>Duración Estimada:</strong> <?= htmlspecialchars($viaje['duracion_estimada'] ?? 'N/A') ?></p>
            <?php endif; ?>
            <p><strong>Precio:</strong> $<?= number_format($viaje['precio'], 2) ?></p>
            <p><strong>Asientos disponibles:</strong> <?= max(0, $asientos_disponibles) ?> de <?= $viaje['asientos'] ?></p>
            <p><strong>Observaciones:</strong> <?= nl2br(htmlspecialchars($viaje['observaciones'] ?? 'Sin observaciones')) ?></p>
        </div>

        <div class="col detail-box">
            <h2>Conductor</h2>
            <p><strong>Nombre:</strong> <?= htmlspecialchars($viaje['conductor_nombre']) ?></p>
            <p><strong>Calificación:</strong> 
                <?= $viaje['promedio_calif'] ? number_format($viaje['promedio_calif'], 1) . ' ⭐' : 'Sin calificaciones aún' ?>
            </p>
            
            <h3>Vehículo asignado</h3>
            <p><?= htmlspecialchars($viaje['marca'] . ' ' . $viaje['modelo']) ?> (<?= htmlspecialchars($viaje['color']) ?>)</p>
            <p><strong>Patente:</strong> <?= htmlspecialchars($viaje['patente']) ?></p>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <p><a href="<?= BASE_URL ?>login.php">Iniciá sesión para reservar</a></p>
        <?php elseif ($asientos_disponibles <= 0): ?>
            <button disabled style="padding: 10px 20px;">El viaje está lleno</button>
        <?php elseif (isset($_SESSION['is_conductor']) && $_SESSION['is_conductor'] && isset($_SESSION['conductor_id']) && $_SESSION['conductor_id'] == $viaje['conductor_id']): ?>
            <p><em>Este es tu viaje publicado.</em></p>
        <?php else: ?>
            <a href="<?= BASE_URL ?>reservar_viaje.php?id=<?= $viaje['id'] ?>" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-size: 1.2em;">Comenzar Reserva</a>
        <?php endif; ?>
    </div>
</body>
</html>
