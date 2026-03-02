<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

// Traer viaje
$stmt = $pdo->prepare("
    SELECT * FROM viajes
    WHERE id = ? AND conductor_id = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);
$viaje = $stmt->fetch();

if (!$viaje) {
    die('Viaje no encontrado');
}

// Traer vehículos del conductor
$stmt = $pdo->prepare("
    SELECT * FROM vehiculos
    WHERE conductor_id = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE viajes
        SET origen = ?, destino = ?, fecha = ?, vehiculo_id = ?
        WHERE id = ? AND conductor_id = ?
    ");

    $stmt->execute([
        $_POST['origen'],
        $_POST['destino'],
        $_POST['fecha'],
        $_POST['vehiculo_id'],
        $id,
        $_SESSION['conductor_id']
    ]);

    header('Location: viajes.php');
    exit;
}
?>

<h2>Editar viaje</h2>

<form method="post">
    Origen:
    <input name="origen"
           value="<?= htmlspecialchars($viaje['origen']) ?>"
           required><br>

    Destino:
    <input name="destino"
           value="<?= htmlspecialchars($viaje['destino']) ?>"
           required><br>

    Fecha:
    <input type="datetime-local"
           name="fecha"
           value="<?= date('Y-m-d\TH:i', strtotime($viaje['fecha'])) ?>"
           required><br>

    Vehículo:
    <select name="vehiculo_id" required>
        <?php foreach ($vehiculos as $v): ?>
            <option value="<?= $v['id'] ?>"
                <?= $v['id'] == $viaje['vehiculo_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['marca']) ?>
                <?= htmlspecialchars($v['modelo']) ?> -
                <?= htmlspecialchars($v['patente']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <button>Guardar cambios</button>
</form>

<a href="viajes.php">Cancelar</a>
