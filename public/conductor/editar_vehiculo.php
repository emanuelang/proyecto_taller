<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT v.ID_vehiculo AS id, v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, v.Patente AS patente
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE v.ID_vehiculo = ? AND cv.ID_conductor = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);
$vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehiculo) {
    die('Vehículo no encontrado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE Vehiculos
        SET Marca = ?, Modelo = ?, Color = ?, Patente = ?, CantidadAsientos = ?
        WHERE ID_vehiculo = ?
    ");

    $stmt->execute([
        $_POST['marca'],
        $_POST['modelo'],
        $_POST['color'],
        $_POST['patente'],
        $_POST['asientos'],
        $id
    ]);

    header('Location: vehiculos.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar Vehículo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<div class="nav-menu">
    <h2>Editar vehículo</h2>
    <a href="vehiculos.php" style="margin-left: auto;">← Volver</a>
</div>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <form method="post">
        <label>Marca:</label>
        <input name="marca"
               value="<?= htmlspecialchars($vehiculo['marca']) ?>"
               required><br>

        <label>Modelo:</label>
        <input name="modelo"
               value="<?= htmlspecialchars($vehiculo['modelo']) ?>"
               required><br>

        <label>Color:</label>
        <input name="color"
               value="<?= htmlspecialchars($vehiculo['color']) ?>"
               required><br>

        <label>Patente:</label>
        <input name="patente"
               value="<?= htmlspecialchars($vehiculo['patente']) ?>"
               required><br>

        <label>Asientos:</label>
        <input type="number"
               name="asientos"
               min="1"
               value="<?= $vehiculo['asientos'] ?>"
               required><br><br>

        <button type="submit" class="btn" style="width: 100%; margin-top: 15px; background-color: var(--success);">Guardar cambios</button>
    </form>
</div>

</body>
</html>
