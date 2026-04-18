<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* Convertir foto a Base64 */
    function procesarFotoBase64($campo) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName = $_FILES[$campo]['tmp_name'];
        $tipoMime = mime_content_type($tmpName);

        // Validar que sea realmente una imagen
        if (strpos($tipoMime, 'image/') !== 0) {
            return null;
        }

        $contenidoBinario = file_get_contents($tmpName);
        $base64 = base64_encode($contenidoBinario);
        
        return "data:" . $tipoMime . ";base64," . $base64;
    }

    $papeles_auto = procesarFotoBase64('papeles_auto');
    $foto_frente = procesarFotoBase64('foto_frente');
    $foto_costado = procesarFotoBase64('foto_costado');
    $foto_atras = procesarFotoBase64('foto_atras');

    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $patente = trim($_POST['patente']);
    $asientos = (int)$_POST['asientos'];

    $errores = [];
    if (strlen($marca) > 100) $errores[] = "La marca es muy larga.";
    if (strlen($modelo) > 100) $errores[] = "El modelo es muy largo.";
    if (strlen($color) > 50) $errores[] = "El color es muy largo.";
    if (!preg_match('/^[A-Za-z0-9]{6,7}$/', $patente)) $errores[] = "La patente debe tener 6 o 7 caracteres alfanuméricos.";

    if (empty($errores)) {
        $stmt = $pdo->prepare("
        INSERT INTO Vehiculos
        (Marca, Modelo, Color, Patente, CantidadAsientos, PapelesAuto, FotoFrente, FotoCostado, FotoAtras)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $marca,
        $modelo,
        $color,
        $patente,
        $asientos,
        $papeles_auto,
        $foto_frente,
        $foto_costado,
        $foto_atras
    ]);
    
        $vehiculo_id = $pdo->lastInsertId();
        
        $stmt_rel = $pdo->prepare("INSERT INTO ConductorVehiculo (ID_conductor, ID_vehiculo) VALUES (?, ?)");
        $stmt_rel->execute([$_SESSION['conductor_id'], $vehiculo_id]);

        header('Location: vehiculos.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registrar Vehículo</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<?php include __DIR__ . '/_nav.php'; ?>

<div class="card" style="max-width: 600px; margin: 40px auto; padding: 30px;">
    <h2 style="margin-top: 0; color: var(--primary); text-align: center;">Registrar vehículo</h2>

    <?php if (!empty($errores)): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Marca:</label> 
        <input type="text" name="marca" placeholder="Ej: Toyota" required maxlength="100">
        
        <label>Modelo:</label> 
        <input type="text" name="modelo" placeholder="Ej: Corolla" required maxlength="100">
        
        <label>Color:</label> 
        <input type="text" name="color" placeholder="Ej: Blanco" required maxlength="50">
        
        <label>Patente:</label> 
        <input type="text" name="patente" placeholder="Ej: AB123CD" required minlength="6" maxlength="7" pattern="[A-Za-z0-9]{6,7}" title="Debe contener 6 o 7 caracteres alfanuméricos">
        
        <label>Asientos disponibles para pasajeros:</label> 
        <input type="number" name="asientos" min="1" max="10" required>

        <label>Papeles del Auto (cédula verde/azul)</label>
        <input type="file" name="papeles_auto" accept="image/*" required>

        <label>Foto Frente del auto (donde se vea la patente si es posible)</label>
        <input type="file" name="foto_frente" accept="image/*" required>

        <label>Foto Costado del auto</label>
        <input type="file" name="foto_costado" accept="image/*" required>

        <label>Foto Atrás del auto</label>
        <input type="file" name="foto_atras" accept="image/*" required>

        <button type="submit" style="width: 100%; padding: 15px; font-size: 1.1em; background-color: var(--success); margin-top: 20px;">Guardar vehículo</button>
    </form>

    <a href="vehiculos.php" style="display: block; text-align: center; margin-top: 15px; color: var(--primary);">Cancelar y volver</a>
</div>

</body>
</html>
