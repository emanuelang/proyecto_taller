<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

/* Datos del conductor */
$stmt = $pdo->prepare("
    SELECT u.Nombre as nombre, u.Correo as email, c.Estado as estado
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$conductor = $stmt->fetch();

/* Vehículos */
$stmt = $pdo->prepare("
    SELECT v.*
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE cv.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div class="card">
    <h3 style="margin-top: 0; color: var(--primary);">Mi Perfil</h3>
    <p><strong>Nombre:</strong> <?= htmlspecialchars($conductor['nombre']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($conductor['email']) ?></p>
    <p><strong>Estado:</strong> <?= htmlspecialchars($conductor['estado']) ?></p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; color: var(--primary);">Vehículos registrados</h3>
        <?php if (empty($vehiculos)): ?>
            <p>No tenés vehículos registrados.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($vehiculos as $v): ?>
                    <li>
                        <?= htmlspecialchars($v['Marca']) ?>
                        <?= htmlspecialchars($v['Modelo']) ?> -
                        <?= htmlspecialchars($v['Color']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a href="vehiculos.php" class="btn" style="display: block; text-align: center; margin-top: 15px;">Administrar vehículos</a>
    </div>

    <div class="card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; color: var(--primary);">Mis viajes</h3>
        <p>Administrá los viajes que publicaste.</p>
        <a href="viajes.php" class="btn" style="display: block; text-align: center; margin-bottom: 10px;">Ver mis viajes</a>
        <a href="<?= BASE_URL ?>crear_viaje.php" class="btn" style="display: block; text-align: center; background-color: var(--success);">Crear nuevo viaje</a>
    </div>
</div>

</body>
</html>
