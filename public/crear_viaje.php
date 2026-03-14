<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$ciudades = $pdo->query("SELECT * FROM ciudades ORDER BY nombre")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];
    $observaciones = $_POST['observaciones'] ?? '';

    // Simulación simple de API de distancia geométrica/estática
    $diff = abs($origen - $destino);
    $distancia_km = ($diff == 0) ? 15 : ($diff * 50 + rand(5, 30));
    
    $horas = floor($distancia_km / 80);
    $mins = round((($distancia_km % 80) / 80) * 60);
    $duracion_estimada = "{$horas}h {$mins}m";

    $stmt = $pdo->prepare("
        INSERT INTO viajes 
        (conductor_id, origen_id, destino_id, fecha, precio, estado, observaciones, distancia_km, duracion_estimada, creado_en)
        VALUES (?, ?, ?, ?, ?, 'activo', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $_SESSION['conductor_id'],
        $origen,
        $destino,
        $fecha,
        $precio,
        $observaciones,
        $distancia_km,
        $duracion_estimada
    ]);

    header("Location: " . BASE_URL . "conductor/viajes.php");
    exit;
}
?>

<?php
$origen_def = $_GET['origen'] ?? '';
$destino_def = $_GET['destino'] ?? '';
$precio_def = $_GET['precio'] ?? '';
$obs_def = $_GET['observaciones'] ?? '';
?>

<h2>Crear viaje</h2>
<a href="<?= BASE_URL ?>conductor/dashboard.php">← Volver</a>
<hr>

<form method="POST">

    <select name="origen" required>
        <option value="">Origen</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($origen_def == $c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <select name="destino" required>
        <option value="">Destino</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($destino_def == $c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <input type="datetime-local" name="fecha" required><br><br>
    <input type="number" name="precio" placeholder="Precio" value="<?= htmlspecialchars($precio_def) ?>" required><br><br>

    <textarea name="observaciones" placeholder="Observaciones"><?= htmlspecialchars($obs_def) ?></textarea><br><br>

    <button type="submit">Crear viaje</button>

</form>
