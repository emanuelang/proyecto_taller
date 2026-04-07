<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mis Vehículos</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="margin-bottom: 20px;">
    <a href="crear_vehiculo.php" class="btn" style="background-color: var(--success);">Agregar vehículo</a>
</div>

<?php if (empty($vehiculos)): ?>
    <div class="card" style="text-align: center; color: #64748b; padding: 40px;">
        <p style="font-size: 1.2em;">No tenés vehículos registrados.</p>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <?php foreach ($vehiculos as $v): ?>
        <div class="card" style="margin-bottom: 0;">
            <h3 style="margin-top: 0; color: var(--primary);">
                <?= htmlspecialchars($v['marca']) ?> <?= htmlspecialchars($v['modelo']) ?>
            </h3>
            <p><strong>Color:</strong> <?= htmlspecialchars($v['color']) ?></p>
            <p><strong>Patente:</strong> <?= htmlspecialchars($v['patente']) ?></p>
            <p><strong>Asientos:</strong> <?= $v['asientos'] ?></p>

            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <a href="editar_vehiculo.php?id=<?= $v['id'] ?>" class="btn" style="flex: 1; text-align: center;">Editar</a>
                <a href="eliminar_vehiculo.php?id=<?= $v['id'] ?>" class="btn" style="flex: 1; text-align: center; background-color: #ef4444;" onclick="return confirm('¿Eliminar vehículo?')">Eliminar</a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

</body>
</html>
