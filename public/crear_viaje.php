<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$ciudades_predefinidas = [
    'Paraná', 'Concordia', 'Gualeguaychú', 'Concepción del Uruguay', 
    'Gualeguay', 'Colón', 'Federación', 'La Paz', 'Villaguay', 
    'Victoria', 'Chajarí', 'Crespo', 'Diamante', 'Federal', 
    'Nogoyá', 'Rosario del Tala', 'San Salvador', 'San José de Feliciano', 
    'Santa Elena', 'Oro Verde', 'Buenos Aires', 'Córdoba', 'Rosario', 'La Plata'
];

$stmt_ciudades = $pdo->query("SELECT DISTINCT CiudadOrigen AS nombre FROM Publicaciones UNION SELECT DISTINCT CiudadDestino AS nombre FROM Publicaciones");
$ciudades_db = $stmt_ciudades->fetchAll(PDO::FETCH_COLUMN);

$todas_las_ciudades = array_unique(array_merge($ciudades_predefinidas, $ciudades_db));
sort($todas_las_ciudades);

$ciudades = [];
foreach ($todas_las_ciudades as $c) {
    if (trim($c) !== '') {
        $ciudades[] = ['nombre' => trim($c)];
    }
}

// NUEVO: Obtenemos los vehículos del conductor logueado
$stmt_v = $pdo->prepare("SELECT v.ID_vehiculo AS id, v.Marca AS marca, v.Modelo AS modelo, v.Patente AS patente FROM Vehiculos v JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo WHERE cv.ID_conductor = ?");
$stmt_v->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt_v->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $calle_salida = trim($_POST['calle_salida']);
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];
    $vehiculo_id = $_POST['vehiculo_id']; // Capturamos el vehículo seleccionado
    $observaciones = $_POST['observaciones'] ?? '';

    $errores = [];
    if (strlen($calle_salida) > 200) {
        $errores[] = "La calle de salida es muy larga.";
    }

    if (strtotime($fecha) < strtotime('+23 hours 50 minutes')) { // Permitimos un margen de 10 min por demoras
        $errores[] = "El viaje debe programarse con al menos 24 horas de anticipación.";
    }

    // Get the first vehicle for this conductor to attach the trip to
    $stmt_vehiculo = $pdo->prepare("SELECT ID_vehiculo FROM ConductorVehiculo WHERE ID_conductor = ? LIMIT 1");
    $stmt_vehiculo->execute([$_SESSION['conductor_id']]);
    $vehiculo = $stmt_vehiculo->fetch();
    
    if (!$vehiculo) {
        die("Error: No tienes un vehículo asignado para publicar el viaje.");
    }
    
    $vehiculo_id = $vehiculo['ID_vehiculo'];

    if (empty($errores)) {
        $stmt = $pdo->prepare("
        INSERT INTO Publicaciones 
        (CiudadOrigen, CiudadDestino, CalleSalida, HoraSalida, Precio, Estado, ID_vehiculo)
        VALUES (?, ?, ?, ?, ?, 'Activa', ?)
    ");

    $stmt->execute([
        $origen,
        $destino,
        $calle_salida,
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
}
?>

<?php
$origen_def = $_GET['origen'] ?? '';
$destino_def = $_GET['destino'] ?? '';
$precio_def = $_GET['precio'] ?? '';
$obs_def = $_GET['observaciones'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear Viaje</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<div class="nav-menu">
    <h2>Crear viaje</h2>
    <a href="<?= BASE_URL ?>conductor/dashboard.php" style="margin-left: auto;">← Volver al Dashboard</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form method="POST">

        <?php if (!empty($errores)): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <label>Origen:</label>
        <select name="origen" required>
            <option value="">Origen</option>
            <?php foreach ($ciudades as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($origen_def === $c['nombre']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if (empty($vehiculos)): ?>
            <div style="padding: 10px; margin-bottom: 15px; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px;">
                ⚠️ No tienes vehículos registrados. <a href="<?= BASE_URL ?>conductor/crear_vehiculo.php" style="font-weight: bold; color: #856404; text-decoration: underline;">Registra uno aquí</a>.
            </div>
        <?php endif; ?>

        <label>Destino:</label>
        <select name="destino" required>
            <option value="">Destino</option>
            <?php foreach ($ciudades as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($destino_def === $c['nombre']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <label>Calle de Salida:</label>
        <input type="text" name="calle_salida" placeholder="Ej: Av. Corrientes 1234, esquina Callao" required maxlength="200">

        <label>Fecha y Hora:</label>
        <input type="datetime-local" name="fecha" required min="<?= date('Y-m-d\TH:i', strtotime('+24 hours')) ?>">

        <label>Precio por persona ($):</label>
        <input type="number" name="precio" placeholder="Ej: 2500" value="<?= htmlspecialchars($precio_def) ?>" required min="0" step="0.01">

        <label>Observaciones:</label>
        <textarea name="observaciones" placeholder="Ej: No se aceptan mascotas" rows="4"><?= htmlspecialchars($obs_def) ?></textarea>

        <button type="submit" <?= empty($vehiculos) ? 'disabled' : '' ?> class="btn" style="width: 100%; margin-top: 15px; background-color: var(--success);">Publicar viaje</button>
    </form>
</div>

</body>
</html>