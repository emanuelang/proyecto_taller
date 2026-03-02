<?php
require_once __DIR__ . '/../core/storage.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];

/* Verificar si ya es conductor */
$stmt = $pdo->prepare("SELECT id FROM conductores WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
if ($stmt->fetch()) {
    header('Location: conductor/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $experiencia = trim($_POST['experiencia']);
    $disponibilidad = trim($_POST['disponibilidad']);
    $licencia_vencimiento = $_POST['licencia_vencimiento'];

    /* Subidas */
    function subirArchivo($campo, $dir) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $nombre = time() . '_' . basename($_FILES[$campo]['name']);
        $ruta = $dir . '/' . $nombre;
        move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta);
        return str_replace(__DIR__ . '/../', '', $ruta);
    }

    $licencia_foto = subirArchivo('licencia_foto', __DIR__ . '/uploads/licencias');
    $doc_vehiculo = subirArchivo('documentacion_vehiculo', __DIR__ . '/uploads/vehiculos');
    $foto_vehiculo = subirArchivo('foto_vehiculo', __DIR__ . '/uploads/vehiculos');

    /* Vehículo */
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $patente = trim($_POST['patente']);
    $asientos = (int)$_POST['asientos'];

    try {
        $pdo->beginTransaction();

        /* Crear conductor */
        $stmt = $pdo->prepare("
            INSERT INTO conductores 
            (usuario_id, experiencia, disponibilidad, licencia_foto, licencia_vencimiento, estado)
            VALUES (?, ?, ?, ?, ?, 'pendiente')
        ");
        $stmt->execute([
            $usuario_id,
            $experiencia,
            $disponibilidad,
            $licencia_foto,
            $licencia_vencimiento
        ]);

        $conductor_id = $pdo->lastInsertId();

        /* Crear vehículo inicial */
        $stmt = $pdo->prepare("
            INSERT INTO vehiculos
            (conductor_id, marca, modelo, color, patente, asientos, documentacion)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $conductor_id,
            $marca,
            $modelo,
            $color,
            $patente,
            $asientos,
            $doc_vehiculo ?? $foto_vehiculo
        ]);

        $_SESSION['is_conductor'] = true;
        $_SESSION['conductor_id'] = $conductor_id;

        $pdo->commit();

        header('Location: conductor/dashboard.php');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<p style='color:red'>Error al enviar la solicitud.</p>";
    }
}
?>

<h2>Convertirme en conductor</h2>

<form method="post" enctype="multipart/form-data">

    <label>Experiencia</label><br>
    <textarea name="experiencia" rows="4" required></textarea><br><br>

    <label>Disponibilidad</label><br>
    <textarea name="disponibilidad" rows="3" required></textarea><br><br>

    <label>Foto de licencia</label><br>
    <input type="file" name="licencia_foto" accept="image/*"><br><br>

    <label>Vencimiento licencia</label><br>
    <input type="date" name="licencia_vencimiento" required><br><br>

    <hr>

    <h3>Vehículo inicial</h3>

    <input name="marca" placeholder="Marca" required><br>
    <input name="modelo" placeholder="Modelo" required><br>
    <input name="color" placeholder="Color" required><br>
    <input name="patente" placeholder="Patente" required><br>
    <input type="number" name="asientos" min="1" required placeholder="Asientos"><br><br>

    <label>Documentación vehículo (PDF o imagen)</label><br>
    <input type="file" name="documentacion_vehiculo"><br><br>

    <label>Foto del vehículo (opcional)</label><br>
    <input type="file" name="foto_vehiculo" accept="image/*"><br><br>

    <button>Enviar solicitud</button>
</form>
