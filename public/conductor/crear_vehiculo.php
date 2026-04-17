<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

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
?>

<h2>Registrar vehículo</h2>

<form method="post" enctype="multipart/form-data">
    <label>Marca:</label> <input name="marca" placeholder="Ej: Toyota" required><br>
    <label>Modelo:</label> <input name="modelo" placeholder="Ej: Corolla" required><br>
    <label>Color:</label> <input name="color" placeholder="Ej: Blanco" required><br>
    <label>Patente:</label> <input name="patente" placeholder="Ej: AB123CD" required><br>
    <label>Asientos:</label> <input type="number" name="asientos" min="1" max="10" required><br><br>

    <label>Papeles del Auto (cédula verde/azul)</label><br>
    <input type="file" name="papeles_auto" accept="image/*" required><br><br>

    <label>Foto Frente del auto (donde se vea la patente si es posible)</label><br>
    <input type="file" name="foto_frente" accept="image/*" required><br><br>

    <label>Foto Costado del auto</label><br>
    <input type="file" name="foto_costado" accept="image/*" required><br><br>

    <label>Foto Atrás del auto</label><br>
    <input type="file" name="foto_atras" accept="image/*" required><br><br>

    <button type="submit" style="width: 100%; padding: 15px; font-size: 1.1em; background-color: var(--success);">Guardar vehículo</button>
</form>

<a href="vehiculos.php" style="display: block; text-align: center; margin-top: 15px;">Cancelar</a>
