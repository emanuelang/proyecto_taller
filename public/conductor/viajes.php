<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$sql = "SELECT v.*, 
               c1.nombre AS origen_nombre,
               c2.nombre AS destino_nombre
        FROM viajes v
        JOIN ciudades c1 ON v.origen_id = c1.id
        JOIN ciudades c2 ON v.destino_id = c2.id
        WHERE v.conductor_id = ?
        ORDER BY v.fecha ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['conductor_id']]);
$viajes = $stmt->fetchAll();
?>

<h2>Mis viajes</h2>
<a href="<?= BASE_URL ?>conductor/dashboard.php">← Volver</a>
<hr>

<?php if (empty($viajes)): ?>
    <p>No creaste viajes todavía.</p>
<?php endif; ?>

<?php foreach ($viajes as $v): ?>
    <div>
        <strong>
            <?= htmlspecialchars($v['origen_nombre']) ?>
            →
            <?= htmlspecialchars($v['destino_nombre']) ?>
        </strong><br>

        Fecha: <?= $v['fecha'] ?><br>
        Precio: $<?= $v['precio'] ?><br><br>

        <a href="<?= BASE_URL ?>conductor/eliminar_viaje.php?id=<?= $v['id'] ?>">
            Eliminar viaje
        </a>
        &nbsp;|&nbsp;
        <a href="<?= BASE_URL ?>crear_viaje.php?origen=<?= $v['origen_id'] ?>&destino=<?= $v['destino_id'] ?>&precio=<?= $v['precio'] ?>&observaciones=<?= urlencode($v['observaciones'] ?? '') ?>">
            📋 Reutilizar como Plantilla
        </a>
    </div>
    <hr>
<?php endforeach; ?>
