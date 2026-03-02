<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT * FROM vehiculos
    WHERE id = ? AND conductor_id = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);
$vehiculo = $stmt->fetch();

if (!$vehiculo) {
    die('Vehículo no encontrado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE vehiculos
        SET marca = ?, modelo = ?, color = ?, patente = ?, asientos = ?
        WHERE id = ? AND conductor_id = ?
    ");

    $stmt->execute([
        $_POST['marca'],
        $_POST['modelo'],
        $_POST['color'],
        $_POST['patente'],
        $_POST['asientos'],
        $id,
        $_SESSION['conductor_id']
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
