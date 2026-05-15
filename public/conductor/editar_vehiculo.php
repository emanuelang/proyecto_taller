<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT v.ID_vehiculo AS id, v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, v.Patente AS patente,
           v.FotoFrente, v.FotoCostado, v.FotoAtras
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE v.ID_vehiculo = ? AND cv.ID_conductor = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) {
    die('Vehículo no encontrado');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Información del Vehículo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .photo-container {
            text-align: center;
        }
        .photo-container img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .photo-label {
            display: block;
            margin-top: 5px;
            font-size: 0.85em;
            color: #64748b;
            font-weight: 600;
        }
        .info-group {
            margin-bottom: 15px;
        }
        .info-group label {
            display: block;
            color: #64748b;
            font-size: 0.9em;
            margin-bottom: 3px;
        }
        .info-value {
            font-weight: 500;
            color: var(--text-main);
            background: #f8fafc;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<div class="nav-menu">
    <h2>Información del vehículo</h2>
    <a href="vehiculos.php" style="margin-left: auto;">← Volver</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        <div class="info-group">
            <label>Marca:</label>
            <div class="info-value"><?= htmlspecialchars($vehiculo['marca']) ?></div>
        </div>

        <div class="info-group">
            <label>Modelo:</label>
            <div class="info-value"><?= htmlspecialchars($vehiculo['modelo']) ?></div>
        </div>

        <div class="info-group">
            <label>Color:</label>
            <div class="info-value"><?= htmlspecialchars($vehiculo['color']) ?></div>
        </div>

        <div class="info-group">
            <label>Patente:</label>
            <div class="info-value"><?= htmlspecialchars($vehiculo['patente']) ?></div>
        </div>

        <div class="info-group">
            <label>Asientos:</label>
            <div class="info-value"><?= $vehiculo['asientos'] ?></div>
        </div>
    </div>

    <h3 style="margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 20px; color: var(--primary);">Fotos del Vehículo</h3>
    <div class="photo-grid">
        <div class="photo-container">
            <?php if ($vehiculo['FotoFrente']): ?>
                <img src="<?= $vehiculo['FotoFrente'] ?>" alt="Frente">
            <?php else: ?>
                <div style="height: 150px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">Sin foto</div>
            <?php endif; ?>
            <span class="photo-label">Frente</span>
        </div>
        <div class="photo-container">
            <?php if ($vehiculo['FotoCostado']): ?>
                <img src="<?= $vehiculo['FotoCostado'] ?>" alt="Costado">
            <?php else: ?>
                <div style="height: 150px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">Sin foto</div>
            <?php endif; ?>
            <span class="photo-label">Costado</span>
        </div>
        <div class="photo-container">
            <?php if ($vehiculo['FotoAtras']): ?>
                <img src="<?= $vehiculo['FotoAtras'] ?>" alt="Atrás">
            <?php else: ?>
                <div style="height: 150px; background: #f1f5f9; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">Sin foto</div>
            <?php endif; ?>
            <span class="photo-label">Atrás</span>
        </div>
    </div>
</div>

</body>
</html>
