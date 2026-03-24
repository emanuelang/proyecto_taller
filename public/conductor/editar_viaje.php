<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

// Traer viaje
$stmt = $pdo->prepare("
    SELECT p.* 
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);
$viaje = $stmt->fetch();

if (!$viaje) {
    die('Viaje no encontrado');
}

// Traer vehículos del conductor
$stmt = $pdo->prepare("
    SELECT v.* 
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE cv.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ciudades list for the select dropdowns
$stmt_ciudades = $pdo->query("SELECT DISTINCT CiudadOrigen AS nombre FROM Publicaciones UNION SELECT DISTINCT CiudadDestino AS nombre FROM Publicaciones ORDER BY nombre");
$ciudades = $stmt_ciudades->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $stmt = $pdo->prepare("
        UPDATE Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        SET p.CiudadOrigen = ?, p.CiudadDestino = ?, p.HoraSalida = ?, p.ID_vehiculo = ?
        WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
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
    <select name="origen" required>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= $c['nombre'] == $viaje['CiudadOrigen'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br>

    Destino:
    <select name="destino" required>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= $c['nombre'] == $viaje['CiudadDestino'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br>

    Fecha:
    <input type="datetime-local"
           name="fecha"
           value="<?= date('Y-m-d\TH:i', strtotime($viaje['HoraSalida'])) ?>"
           required><br>

    Vehículo:
    <select name="vehiculo_id" required>
        <?php foreach ($vehiculos as $v): ?>
            <option value="<?= $v['ID_vehiculo'] ?>"
                <?= $v['ID_vehiculo'] == $viaje['ID_vehiculo'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['Marca']) ?>
                <?= htmlspecialchars($v['Modelo']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <button>Guardar cambios</button>
</form>

<a href="viajes.php">Cancelar</a>
