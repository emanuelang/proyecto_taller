<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

// Obtenemos ciudades para los select
$ciudades = $pdo->query("SELECT * FROM ciudades ORDER BY nombre")->fetchAll();

// NUEVO: Obtenemos los vehículos del conductor logueado
$stmt_v = $pdo->prepare("SELECT id, marca, modelo, patente FROM vehiculos WHERE conductor_id = ?");
$stmt_v->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt_v->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];
    $vehiculo_id = $_POST['vehiculo_id']; // Capturamos el vehículo seleccionado
    $observaciones = $_POST['observaciones'] ?? '';

    // Simulación de distancia
    $diff = abs($origen - $destino);
    $distancia_km = ($diff == 0) ? 15 : ($diff * 50 + rand(5, 30));
    
    $horas = floor($distancia_km / 80);
    $mins = round((($distancia_km % 80) / 80) * 60);
    $duracion_estimada = "{$horas}h {$mins}m";

    // INSERT CORREGIDO: Ahora incluye vehiculo_id
    $stmt = $pdo->prepare("
        INSERT INTO viajes 
        (conductor_id, vehiculo_id, origen_id, destino_id, fecha, precio, estado, observaciones, distancia_km, duracion_estimada, creado_en)
        VALUES (?, ?, ?, ?, ?, ?, 'activo', ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $_SESSION['conductor_id'],
        $vehiculo_id, // Se inserta el ID del vehículo
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

<div class="nav-menu">
    <h2>Crear viaje</h2>
    <a href="<?= BASE_URL ?>conductor/dashboard.php" style="margin-left: auto;">← Volver</a>
</div>

<form method="POST">
    <h3 style="margin-top:0; color:var(--primary);">Detalles de la Publicación</h3>
    
    <label>Selecciona tu vehículo:</label>
    <select name="vehiculo_id" required>
        <option value="">-- Mis Vehículos --</option>
        <?php foreach ($vehiculos as $v): ?>
            <option value="<?= $v['id'] ?>">
                <?= htmlspecialchars($v['marca'] . " " . $v['modelo'] . " [" . $v['patente'] . "]") ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (empty($vehiculos)): ?>
        <p style="color:red; font-size: 0.9em; margin-top: 0;">⚠️ No tienes vehículos registrados. <a href="registrar_vehiculo.php">Registra uno aquí</a>.</p>
    <?php endif; ?>

    <div style="display: flex; gap: 15px; margin-top: 10px;">
        <div style="flex: 1;">
            <label>Origen:</label>
            <select name="origen" required>
                <option value="">Seleccionar Origen</option>
                <?php foreach ($ciudades as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($origen_def == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 1;">
            <label>Destino:</label>
            <select name="destino" required>
                <option value="">Seleccionar Destino</option>
                <?php foreach ($ciudades as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($destino_def == $c['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <label>Fecha y Hora:</label>
    <input type="datetime-local" name="fecha" required>

    <label>Precio por persona ($):</label>
    <input type="number" name="precio" placeholder="Ej: 2500" value="<?= htmlspecialchars($precio_def) ?>" required>

    <label>Observaciones:</label>
    <textarea name="observaciones" placeholder="Ej: No se aceptan mascotas" rows="4"><?= htmlspecialchars($obs_def) ?></textarea>

    <button type="submit" <?= empty($vehiculos) ? 'disabled' : '' ?> style="width: 100%; margin-top: 15px; background-color: var(--success);">Publicar viaje</button>
</form>