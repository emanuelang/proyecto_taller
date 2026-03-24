<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

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

<h2>Editar vehículo</h2>

<form method="post">
    Marca:
    <input name="marca"
           value="<?= htmlspecialchars($vehiculo['marca']) ?>"
           required><br>

    Modelo:
    <input name="modelo"
           value="<?= htmlspecialchars($vehiculo['modelo']) ?>"
           required><br>

    Color:
    <input name="color"
           value="<?= htmlspecialchars($vehiculo['color']) ?>"
           required><br>

    Patente:
    <input name="patente"
           value="<?= htmlspecialchars($vehiculo['patente']) ?>"
           required><br>

    Asientos:
    <input type="number"
           name="asientos"
           min="1"
           value="<?= $vehiculo['asientos'] ?>"
           required><br><br>

    <button>Guardar cambios</button>
</form>

<a href="vehiculos.php">Cancelar</a>
