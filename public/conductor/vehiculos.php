<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$stmt = $pdo->prepare("
    SELECT * FROM vehiculos
    WHERE conductor_id = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll();
require_once __DIR__ . '/../../config/app.php';
?>
<?php require_once __DIR__ . '/../header.php'; ?>

<div class="nav-menu">
    <h2>Mis vehículos</h2>
    <div>
        <a href="crear_vehiculo.php" class="btn" style="margin-right: 10px;">+ Agregar vehículo</a>
        <a href="dashboard.php" class="btn" style="background-color: #64748b;">← Volver</a>
    </div>
</div>

<?php if (empty($vehiculos)): ?>
    <p>No tenés vehículos registrados.</p>
<?php endif; ?>

<?php foreach ($vehiculos as $v): ?>
    <div>
        <strong><?= htmlspecialchars($v['marca']) ?> <?= htmlspecialchars($v['modelo']) ?></strong><br>
        Color: <?= htmlspecialchars($v['color']) ?><br>
        Patente: <?= htmlspecialchars($v['patente']) ?><br>
        Asientos: <?= $v['asientos'] ?><br><br>

        <a href="editar_vehiculo.php?id=<?= $v['id'] ?>">Editar</a> |
        <a href="eliminar_vehiculo.php?id=<?= $v['id'] ?>"
           onclick="return confirm('¿Eliminar vehículo?')">Eliminar</a>
    </div>
    <hr>
<?php endforeach; ?>

<a href="dashboard.php">Volver</a>
