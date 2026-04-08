<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['conductor_id'])) {
    header("Location: ../login.php");
    exit;
}

$conductor_id = $_SESSION['conductor_id'];

/* Obtener vehículos */
$stmt = $pdo->prepare("SELECT * FROM vehiculos WHERE conductor_id = ?");
$stmt->execute([$conductor_id]);
$vehiculos = $stmt->fetchAll();

/* Guardar cambios */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $vehiculo_activo_id = $_POST['vehiculo_activo_id'] ?? null;

    $stmt = $pdo->prepare("
        UPDATE conductores 
        SET vehiculo_activo_id = ?
        WHERE id = ?
    ");

    $stmt->execute([$vehiculo_activo_id, $conductor_id]);

    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar Perfil</title>
    <link rel="stylesheet" href="<?= BASE_URL ?? '../../main.css' ?>">
</head>
<body>

<?php include __DIR__ . '/_nav.php'; ?>

<div class="form-container" style="max-width: 600px; margin: 0 auto;">
    <h2 style="color: var(--primary);">Configurar Vehículo Activo</h2>

    <form method="POST" style="box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <label>Vehículo que vas a usar:</label>
        <select name="vehiculo_activo_id" required>
            <option value="">Seleccionar vehículo...</option>
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

</body>
</html>
