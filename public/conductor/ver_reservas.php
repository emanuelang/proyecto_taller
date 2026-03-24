<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$viaje_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.ID_reserva AS reserva_id, u.Nombre AS nombre, u.Correo AS email
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
    WHERE r.ID_publicacion = ?
");
$stmt->execute([$viaje_id]);
$reservas = $stmt->fetchAll();
?>

<h2>Reservas del viaje</h2>

<?php if (empty($reservas)): ?>
    <p>No hay reservas.</p>
<?php endif; ?>

<?php foreach ($reservas as $r): ?>
    <p>
        <?= htmlspecialchars($r['nombre']) ?> (<?= htmlspecialchars($r['email']) ?>)
        <a href="eliminar_reserva.php?id=<?= $r['reserva_id'] ?>&viaje=<?= $viaje_id ?>"
           onclick="return confirm('¿Eliminar reserva?')">Eliminar</a>
    </p>
<?php endforeach; ?>

<a href="viajes.php">Volver</a>
