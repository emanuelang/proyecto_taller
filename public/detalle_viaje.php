<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_GET['id'])) {
    die("Viaje no especificado.");
}
$viaje_id = (int) $_GET['id'];

$sql = "
    SELECT p.ID_publicacion AS id, p.CiudadOrigen AS origen_nombre, p.CiudadDestino AS destino_nombre, p.CalleSalida AS calle_salida, p.HoraSalida AS fecha, p.Precio AS precio, 
           p.Estado AS estado,
           u.Nombre AS conductor_nombre, NULL AS foto_perfil,
           veh.Marca AS marca, veh.Modelo AS modelo, veh.Color AS color, veh.Patente AS patente, veh.Foto AS vehiculo_foto, veh.CantidadAsientos AS asientos,
           (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') as ocupados,
           (SELECT AVG(Puntuacion) FROM Calificaciones calif WHERE calif.ID_conductor = c.ID_conductor) as promedio_calif,
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
    die("El viaje no existe.");
}
$asientos_disponibles = $viaje['asientos'] - $viaje['ocupados'];

// Controlar si el usuario actual ya tiene su asiento asegurado
$ya_reservado = false;
if (isset($_SESSION['user_id'])) {
    $stmt_ya = $pdo->prepare("
        SELECT COUNT(*) FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        WHERE r.ID_publicacion = ? AND pas.ID_usuario = ? AND r.Estado = 'Completada'
    ");
    $stmt_ya->execute([$viaje_id, $_SESSION['user_id']]);
    $ya_reservado = $stmt_ya->fetchColumn() > 0;
}
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
<div class="nav-menu">
        <h2>Detalle del Viaje</h2>
        <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Volver al inicio</a>
    </div>

    <div class="flex-container">
        <div class="col detail-box">
            <h3 style="margin-top:0; color:var(--primary);">Ruta y Horario</h3>
            <p><strong>Origen:</strong> <?= htmlspecialchars($viaje['origen_nombre']) ?></p>
            <p><strong>Destino:</strong> <?= htmlspecialchars($viaje['destino_nombre']) ?></p>
            <?php if (!empty($viaje['calle_salida'])): ?>
                <p><strong>📍 Calle de Salida:</strong> <span style="color: var(--primary); font-weight: 500;"><?= htmlspecialchars($viaje['calle_salida']) ?></span></p>
            <?php endif; ?>
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
            <p style="margin-top: 15px;">
                <a href="<?= BASE_URL ?>reportar.php?conductor_id=<?= $viaje['conductor_id'] ?>" style="color:#d32f2f; text-decoration: underline; font-size: 0.95em;">
                    ⚠️ Reportar problema con este conductor
                </a>
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
        <?php elseif ($ya_reservado): ?>
            <button disabled style="padding: 15px 35px; background-color: #ecfdf5; color: var(--success); border: 2px solid var(--success); font-size: 1.1em; font-weight: bold; border-radius: 6px; cursor: default;">✅ Ya tienes tu asiento asegurado en este viaje</button>
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
