<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$ciudades = [];
$stmt_ciudades = $pdo->query("SELECT DISTINCT CiudadOrigen AS nombre FROM Publicaciones UNION SELECT DISTINCT CiudadDestino AS nombre FROM Publicaciones ORDER BY nombre");
$ciudades = $stmt_ciudades->fetchAll(PDO::FETCH_ASSOC);

// If no cities exist, we can pre-populate a few common ones for dropdowns
if (empty($ciudades)) {
    $ciudades = [
        ['nombre' => 'Buenos Aires'],
        ['nombre' => 'Córdoba'],
        ['nombre' => 'Rosario'],
        ['nombre' => 'La Plata']
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];
    $observaciones = $_POST['observaciones'] ?? '';

    // Get the first vehicle for this conductor to attach the trip to
    $stmt_vehiculo = $pdo->prepare("SELECT ID_vehiculo FROM ConductorVehiculo WHERE ID_conductor = ? LIMIT 1");
    $stmt_vehiculo->execute([$_SESSION['conductor_id']]);
    $vehiculo = $stmt_vehiculo->fetch();
    
    if (!$vehiculo) {
        die("Error: No tienes un vehículo asignado para publicar el viaje.");
    }
    
    $vehiculo_id = $vehiculo['ID_vehiculo'];

    $stmt = $pdo->prepare("
        INSERT INTO Publicaciones 
        (CiudadOrigen, CiudadDestino, HoraSalida, Precio, Estado, ID_vehiculo)
        VALUES (?, ?, ?, ?, 'Activa', ?)
    ");

    $stmt->execute([
        $origen,
        $destino,
        $fecha,
        $precio,
        $vehiculo_id
    ]);

    $publicacion_id = $pdo->lastInsertId();

    $stmt2 = $pdo->prepare("INSERT INTO ConductorPublicacion (ID_conductor, ID_publicacion) VALUES (?, ?)");
    $stmt2->execute([$_SESSION['conductor_id'], $publicacion_id]);

    header("Location: " . BASE_URL . "conductor/viajes.php");
    exit;
}
?>

<h2>Crear viaje</h2>
<a href="<?= BASE_URL ?>conductor/dashboard.php">← Volver</a>
<hr>

<form method="POST">

    <select name="origen" required>
        <option value="">Origen</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>">
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <select name="destino" required>
        <option value="">Destino</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>">
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <input type="datetime-local" name="fecha" required><br><br>
    <input type="number" name="precio" placeholder="Precio" required><br><br>

    <textarea name="observaciones" placeholder="Observaciones"></textarea><br><br>

    <button type="submit">Crear viaje</button>

</form>
