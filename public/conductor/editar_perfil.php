<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if (!isset($_SESSION['conductor_id'])) {
    header("Location: ../login.php");
    exit;
}

$conductor_id = $_SESSION['conductor_id'];

/* Obtener vehiculos */
$stmt = $pdo->prepare("
    SELECT
        v.ID_vehiculo AS id,
        v.Marca AS marca,
        v.Modelo AS modelo,
        v.Patente AS patente
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE cv.ID_conductor = ?
    ORDER BY v.Marca, v.Modelo
");
$stmt->execute([$conductor_id]);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Guardar cambios */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    header("Location: dashboard.php");
    exit;
}
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div class="form-container" style="max-width: 600px; margin: 0 auto;">
    <h2 style="color: var(--primary);">Configurar Vehiculo Activo</h2>

    <form method="POST" style="box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <?= csrf_field() ?>
        <label>Vehiculo que vas a usar:</label>
        <select name="vehiculo_activo_id" required>
            <option value="">Seleccionar vehiculo...</option>
            <?php foreach ($vehiculos as $v): ?>
                <option value="<?= $v['id'] ?>">
                    <?= htmlspecialchars($v['marca']) ?>
                    <?= htmlspecialchars($v['modelo']) ?>
                    (<?= htmlspecialchars($v['patente']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn" style="width: 100%; margin-top: 15px;">Guardar cambios</button>
    </form>
</div>
