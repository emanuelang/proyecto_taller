<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$viaje_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.ID_reserva AS reserva_id, r.Estado, r.CodigoAcceso, u.Nombre AS nombre, u.Correo AS email, u.Telefono
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
    WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
    ORDER BY u.Nombre ASC
");
$stmt->execute([$viaje_id]);
$reservas = $stmt->fetchAll();
?>

<h2>Reservas del viaje</h2>

<?php if (empty($reservas)): ?>
    <p>No hay reservas.</p>
<?php endif; ?>

<?php foreach ($reservas as $r): ?>
    <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; border-radius: 5px; background: #fff;">
        <h3 style="margin-top: 0; color: #333;"><?= htmlspecialchars($r['nombre']) ?></h3>
        <p style="margin: 5px 0;"><strong>Email:</strong> <?= htmlspecialchars($r['email']) ?></p>
        <p style="margin: 5px 0;"><strong>Teléfono:</strong> <?= htmlspecialchars($r['Telefono'] ?? 'No especificado') ?></p>
        <p style="margin: 5px 0;"><strong>Estado del pasaje:</strong> 
            <?php if ($r['Estado'] === 'Completada'): ?>
                <span style="color: green; font-weight: bold;">Pagado / Confirmado</span>
            <?php else: ?>
                <span style="color: orange; font-weight: bold;"><?= htmlspecialchars($r['Estado']) ?></span>
            <?php endif; ?>
        </p>
        <?php if ($r['CodigoAcceso']): ?>
            <p style="background: #eef; padding: 10px; border-radius: 4px; border: 1px dashed #007bff; margin: 15px 0;">
                <strong>Código de Validación del Pasajero:</strong> <span style="font-size: 1.2em; color: #007bff; letter-spacing: 1px;"><?= htmlspecialchars($r['CodigoAcceso']) ?></span><br>
                <small style="color: #666;">(Pídele este código al pasajero al subir para validar que sea él quién pagó el viaje.)</small>
            </p>
        <?php endif; ?>
        
        <div style="margin-top: 15px;">
            <a href="eliminar_reserva.php?id=<?= $r['reserva_id'] ?>&viaje=<?= $viaje_id ?>" class="btn-rechazar" style="background:#dc3545; color:white; padding:8px 12px; text-decoration:none; border-radius:3px; font-size:0.9em; border:none; display:inline-block;" onclick="return confirm('¿Eliminar/Rechazar esta reserva?')">❌ Cancelar Reserva</a>
        </div>
    </div>
<?php endforeach; ?>

<a href="viajes.php">Volver</a>
