<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$viaje_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.id AS reserva_id, u.nombre, u.email
    FROM reservas r
    JOIN usuarios u ON r.usuario_id = u.id
    WHERE r.viaje_id = ?
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
