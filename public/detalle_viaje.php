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
           p.Estado AS estado, p.DistanciaKM as distancia_km, p.DuracionMinutos as duracion_minutos,
           u.Nombre AS conductor_nombre, u.FotoPerfil AS foto_perfil,
           veh.Marca AS marca, veh.Modelo AS modelo, veh.Color AS color, veh.Patente AS patente, veh.FotoCostado AS vehiculo_foto, veh.CantidadAsientos AS asientos,
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
        <div class="col detail-box" style="padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; padding-bottom: 20px; margin-bottom: 20px;">
                <h3 style="margin:0; color:var(--text-main); font-size: 1.5em; gap: 10px; display: flex; align-items: center;">📍 Ruta y Horario</h3>
                <span class="badge badge-success" style="font-size: 1em; padding: 8px 15px;">$<?= number_format($viaje['precio'], 2) ?></span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <span class="text-muted">Origen</span>
                    <div style="font-size: 1.1em; font-weight: 500;"><?= htmlspecialchars($viaje['origen_nombre']) ?></div>
                </div>
                <div>
                    <span class="text-muted">Destino</span>
                    <div style="font-size: 1.1em; font-weight: 500;"><?= htmlspecialchars($viaje['destino_nombre']) ?></div>
                </div>
            </div>

            <?php if (!empty($viaje['calle_salida'])): ?>
                <div style="margin-bottom: 15px;">
                    <span class="text-muted">Punto de encuentro</span>
                    <div style="color: var(--primary); font-weight: 500;">📌 <?= htmlspecialchars($viaje['calle_salida']) ?></div>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; background-color: #F8FAFC; padding: 15px; border-radius: 8px;">
                <div>
                    <span class="text-muted">🗓️ Fecha de salida</span>
                    <div style="font-weight: 500; margin-top: 5px;"><?= date('d/m/Y H:i', strtotime($viaje['fecha'])) ?> hs</div>
                </div>
                <div>
                    <span class="text-muted">🏁 Llegada estimada</span>
                    <?php if (isset($viaje['distancia_km']) && $viaje['distancia_km'] > 0): ?>
                        <div style="font-weight: 500; margin-top: 5px;"><?= date('d/m/Y H:i', strtotime($viaje['fecha']) + ($viaje['duracion_minutos'] * 60)) ?> hs</div>
                        <div style="font-size: 0.85em; color: #94A3B8; margin-top: 5px;">Distancia: <?= ceil($viaje['distancia_km']) ?> km</div>
                    <?php else: ?>
                        <div style="font-weight: 500; margin-top: 5px; color:#94A3B8;">No disponible por el momento</div>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <span class="text-muted">💺 Disponibilidad</span>
                <div style="font-weight: 500;">Quedan <?= max(0, $asientos_disponibles) ?> de <?= $viaje['asientos'] ?> asientos</div>
            </div>

            <?php if (!empty($viaje['observaciones'])): ?>
                <div style="margin-top: 20px; border-top: 1px dashed #E2E8F0; padding-top: 15px;">
                    <span class="text-muted">📝 Observaciones del conductor</span>
                    <p style="margin-top: 5px; font-style: italic; color: #475569;">"<?= nl2br(htmlspecialchars($viaje['observaciones'])) ?>"</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col" style="display: flex; flex-direction: column; gap: 20px;">
            <!-- Box Conductor -->
            <div class="detail-box" style="margin-bottom: 0;">
                <h3 style="margin-top:0; color:var(--text-main); font-size: 1.3em; display:flex; align-items: center; gap: 8px; border-bottom: 1px solid #E2E8F0; padding-bottom: 15px;">👤 Información del Conductor</h3>
                
                <div style="display: flex; align-items: center; gap: 20px; margin-top: 15px;">
                    <div style="display: flex; flex-direction: column; align-items: center; width: 80px;">
                        <?php if ($viaje['foto_perfil']): ?>
                            <img src="<?= $viaje['foto_perfil'] ?>" alt="Conductor" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <?php else: ?>
                            <div style="width: 80px; height: 80px; border-radius: 50%; background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 2em; color: #94a3b8;">👤</div>
                        <?php endif; ?>
                        
                        <?php if ($viaje['promedio_calif']): ?>
                            <div style="background-color: black; color: white; padding: 2px 10px; border-radius: 10px; font-size: 0.8em; font-weight: bold; margin-top: -10px; z-index: 1;">
                                ⭐ <?= number_format(floor($viaje['promedio_calif'] * 10) / 10, 1, ',', '.') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div style="flex: 1;">
                        <div style="font-size: 1.3em; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($viaje['conductor_nombre']) ?></div>
                        <?php if (!$viaje['promedio_calif']): ?>
                            <div style="color: #16a34a; font-size: 0.95em; margin-top: 4px; display: flex; align-items: center; gap: 5px; font-weight: 600;">
                                ✅ Conductor verificado
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Box Vehículo -->
            <div class="detail-box" style="margin-bottom: 0;">
                <h3 style="margin-top:0; color:var(--text-main); font-size: 1.3em; display:flex; align-items: center; gap: 8px; border-bottom: 1px solid #E2E8F0; padding-bottom: 15px;">🚗 Vehículo Asignado</h3>
                
                <div style="margin-top: 15px; display: flex; gap: 15px; align-items: center;">
                    <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span class="text-muted">Modelo</span>
                            <span style="font-weight: 500;"><?= htmlspecialchars($viaje['marca'] . ' ' . $viaje['modelo']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span class="text-muted">Color</span>
                            <span style="font-weight: 500; font-style: italic;"><?= htmlspecialchars($viaje['color']) ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; background-color: #F1F5F9; padding: 8px 12px; border-radius: 6px; margin-top: 5px;">
                            <span style="font-size: 0.85em; font-weight: bold; color: #64748B;">MATRÍCULA</span>
                            <span style="font-family: monospace; font-size: 1.2em; font-weight: bold; letter-spacing: 2px; color: #0F172A;"><?= htmlspecialchars($viaje['patente']) ?></span>
                        </div>
                    </div>
                    
                    <div style="width: 120px; display: flex; align-items: center; justify-content: center;">
                        <?php if ($viaje['vehiculo_foto']): ?>
                            <img src="<?= $viaje['vehiculo_foto'] ?>" alt="Vehículo" style="max-width: 100%; max-height: 100px; object-fit: contain;">
                        <?php else: ?>
                            <div style="font-size: 3em; opacity: 0.2;">🚗</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <a href="<?= BASE_URL ?>login.php" class="btn">Iniciá sesión para reservar</a>
        <?php elseif ($ya_reservado): ?>
            <div style="background-color: #F0FDF4; border: 1px solid #BBF7D0; color: #166534; padding: 20px 35px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(22, 101, 52, 0.1);">
                <div style="font-size: 1.5em; margin-bottom: 5px;">✅</div>
                <div style="font-size: 1.2em; font-weight: bold;">Asiento Confirmado</div>
                <div style="font-size: 0.95em; margin-top: 5px; opacity: 0.9;">¡Ya formas parte de este viaje! Revisa tu Panel para ver más detalles.</div>
            </div>
        <?php elseif ($asientos_disponibles <= 0): ?>
            <button disabled style="padding: 15px 35px; background-color: #F1F5F9; color: #94A3B8; border: none; font-size: 1.1em; border-radius: 8px;">Viaje Agotado</button>
        <?php elseif (isset($_SESSION['is_conductor']) && $_SESSION['is_conductor'] && isset($_SESSION['conductor_id']) && $_SESSION['conductor_id'] == $viaje['conductor_id']): ?>
            <div style="padding: 15px 35px; background-color: #F8FAFC; color: #475569; border: 1px dashed #CBD5E1; font-size: 1.1em; border-radius: 8px; display: inline-block;">
                🚗 Este es un viaje publicado por ti.
            </div>
        <?php else: ?>
            <a href="<?= BASE_URL ?>reservar_viaje.php?id=<?= $viaje['id'] ?>" class="btn success-bg" style="padding: 15px 40px; font-size: 1.2em; border-radius: 8px; display: inline-block; box-shadow: 0 4px 15px -3px rgba(132, 204, 22, 0.4);">💳 Continuar a la Reserva</a>
        <?php endif; ?>
    </div>
</body>
</html>
