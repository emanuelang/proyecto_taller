<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$stmt = $pdo->prepare(
    "SELECT * FROM viajes WHERE conductor_id = ? ORDER BY fecha ASC"
);
$stmt->execute([$_SESSION['conductor_id']]);
$viajes = $stmt->fetchAll();
require_once __DIR__ . '/../../config/app.php';
?>
<?php require_once __DIR__ . '/../header.php'; ?>

<div class="nav-menu">
    <h2>Mis viajes</h2>
    <a href="dashboard.php" style="margin-left: auto;">← Volver al panel</a>
</div>

<?php if (!$viajes): ?>
    <p>No creaste viajes todavía.</p>
<?php endif; ?>

<?php foreach ($viajes as $v): ?>
    <div class="card">
        <strong><?= htmlspecialchars($v['origen']) ?> → <?= htmlspecialchars($v['destino']) ?></strong><br>
        Fecha: <?= $v['fecha'] ?><br>
        Asientos totales: <?= $v['asientos_totales'] ?><br>
        <a href="ver_reservas.php?id=<?= $v['id'] ?>">Ver pasajeros</a>
    </div>
<?php endforeach; ?>

<a href="dashboard.php">Volver al panel</a>
