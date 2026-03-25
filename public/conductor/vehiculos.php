<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$stmt = $pdo->prepare("
    SELECT v.ID_vehiculo AS id, v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, v.Patente AS patente
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE cv.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Mis vehículos</h2>

<a href="crear_vehiculo.php">Agregar vehículo</a>
<hr>

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
