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

<h2>Editar Perfil del Conductor</h2>

<form method="POST">

    <label>Vehículo activo:</label>
    <select name="vehiculo_activo_id" required>
        <option value="">Seleccionar vehículo</option>

        <?php foreach ($vehiculos as $v): ?>
            <option value="<?= $v['id'] ?>">
                <?= htmlspecialchars($v['marca']) ?> 
                <?= htmlspecialchars($v['modelo']) ?> 
                (<?= htmlspecialchars($v['patente']) ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <button type="submit">Guardar cambios</button>
</form>

<br>
<a href="dashboard.php">Volver</a>
