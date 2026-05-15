<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * Convierte una imagen subida a Base64, comprimiéndola con GD si está disponible.
     * Objetivo: 1 MB por imagen en Base64 para mantener buena calidad.
     */
    function procesarFotoBase64($campo) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $tmpName  = $_FILES[$campo]['tmp_name'];
        $tipoMime = mime_content_type($tmpName);

        if (strpos($tipoMime, 'image/') !== 0) {
            return null;
        }

        // ── Con GD: redimensionar + compresión equilibrada ─────────────────
        if (function_exists('imagecreatetruecolor')) {
            list($ancho_orig, $alto_orig) = getimagesize($tmpName);

            $max_resolucion = 1200; // Calidad superior
            $ratio = $ancho_orig / $alto_orig;
            if ($ancho_orig > $max_resolucion || $alto_orig > $max_resolucion) {
                if ($ratio > 1) {
                    $nuevo_ancho = $max_resolucion;
                    $nuevo_alto  = (int)round($max_resolucion / $ratio);
                } else {
                    $nuevo_alto  = $max_resolucion;
                    $nuevo_ancho = (int)round($max_resolucion * $ratio);
                }
            } else {
                $nuevo_ancho = $ancho_orig;
                $nuevo_alto  = $alto_orig;
            }

            $canvas = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
            $blanco = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $nuevo_ancho, $nuevo_alto, $blanco);

            switch ($tipoMime) {
                case 'image/png':  $orig = @imagecreatefrompng($tmpName);  break;
                case 'image/jpeg': $orig = @imagecreatefromjpeg($tmpName); break;
                case 'image/webp': $orig = @imagecreatefromwebp($tmpName); break;
                default: $orig = false;
            }

            if (!$orig) {
                imagedestroy($canvas);
                return "data:{$tipoMime};base64," . base64_encode(file_get_contents($tmpName));
            }

            imagecopyresampled($canvas, $orig, 0, 0, 0, 0,
                               $nuevo_ancho, $nuevo_alto, $ancho_orig, $alto_orig);
            imagedestroy($orig);

            // Intentamos mantener 1 MB en Base64 (aprox 750KB binario)
            $limite_base64 = 1024 * 1024; 
            $contenido_comprimido = null;
            
            foreach ([85, 70, 50, 30] as $calidad) {
                ob_start();
                imagejpeg($canvas, null, $calidad);
                $datos = ob_get_clean();
                $b64   = base64_encode($datos);
                if (strlen($b64) <= $limite_base64) {
                    $contenido_comprimido = $datos;
                    break;
                }
            }

            imagedestroy($canvas);
            $final_data = $contenido_comprimido ?? $datos;
            return "data:image/jpeg;base64," . base64_encode($final_data);
        }

        // ── Fallback sin GD: Límite de 2MB para evitar bloqueos totales
        if ($_FILES[$campo]['size'] > 2 * 1024 * 1024) {
            return '__TOO_LARGE__';
        }

        return "data:{$tipoMime};base64," . base64_encode(file_get_contents($tmpName));
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
    
    if ($papeles_auto === '__TOO_LARGE__' || $foto_frente === '__TOO_LARGE__' || $foto_costado === '__TOO_LARGE__' || $foto_atras === '__TOO_LARGE__') {
        $errores[] = "Una o más imágenes superan el límite de 2MB.";
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // PASO 1: Insertar datos de texto
            $stmt = $pdo->prepare("
                INSERT INTO Vehiculos (Marca, Modelo, Color, Patente, CantidadAsientos, Estado)
                VALUES (?, ?, ?, ?, ?, 'Pendiente')
            ");
            $stmt->execute([$marca, $modelo, $color, $patente, $asientos]);
            $vehiculo_id = $pdo->lastInsertId();

            // PASO 2: Actualizar cada foto por separado (Evita 'max_allowed_packet' de 1MB)
            // Al ser consultas individuales, cada una tiene su propio "presupuesto" de tamaño.
            if ($papeles_auto) {
                $pdo->prepare("UPDATE Vehiculos SET PapelesAuto = ? WHERE ID_vehiculo = ?")->execute([$papeles_auto, $vehiculo_id]);
            }
            if ($foto_frente) {
                $pdo->prepare("UPDATE Vehiculos SET FotoFrente = ? WHERE ID_vehiculo = ?")->execute([$foto_frente, $vehiculo_id]);
            }
            if ($foto_costado) {
                $pdo->prepare("UPDATE Vehiculos SET FotoCostado = ? WHERE ID_vehiculo = ?")->execute([$foto_costado, $vehiculo_id]);
            }
            if ($foto_atras) {
                $pdo->prepare("UPDATE Vehiculos SET FotoAtras = ? WHERE ID_vehiculo = ?")->execute([$foto_atras, $vehiculo_id]);
            }
            
            $stmt_rel = $pdo->prepare("INSERT INTO ConductorVehiculo (ID_conductor, ID_vehiculo) VALUES (?, ?)");
            $stmt_rel->execute([$_SESSION['conductor_id'], $vehiculo_id]);

            $pdo->commit();
            header('Location: vehiculos.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errores[] = "Error al guardar: " . $e->getMessage();
        }
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
