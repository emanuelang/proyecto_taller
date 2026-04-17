<?php
require_once __DIR__ . '/../core/storage.php';
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['user_id'];

/* Verificar si ya es conductor */
$stmt = $pdo->prepare("SELECT Estado FROM Conductores WHERE ID_usuario = ?");
$stmt->execute([$usuario_id]);
$cond = $stmt->fetch(PDO::FETCH_ASSOC);

if ($cond) {
    if ($cond['Estado'] === 'Aceptada') {
        header('Location: conductor/dashboard.php');
        exit;
    } else {
        // Todavía está esperando (o en algún otro estado)
        header('Location: index.php?msg=esperando_aprobacion');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $licencia = trim($_POST['licencia_conducir']);
    $seguro = trim($_POST['seguro_vehiculo']);
    $banco = trim($_POST['cuenta_bancaria']);
    $telefono = trim($_POST['telefono_contacto']);
    $alias_mp = trim($_POST['alias_mp']);

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

    $foto_vehiculo = procesarFotoBase64('foto_vehiculo'); // Mantengo por si suben acá, pero usaremos las específicas
    $foto_carnet = procesarFotoBase64('foto_carnet');
    $foto_cara = procesarFotoBase64('foto_cara');
    $papeles_auto = procesarFotoBase64('papeles_auto');
    $foto_frente = procesarFotoBase64('foto_frente');
    $foto_costado = procesarFotoBase64('foto_costado');
    $foto_atras = procesarFotoBase64('foto_atras');

    /* Vehículo */
    $marca = trim($_POST['marca']);
    $modelo = trim($_POST['modelo']);
    $color = trim($_POST['color']);
    $asientos = (int)$_POST['asientos'];

    try {
        $pdo->beginTransaction();

        /* Crear conductor */
        $stmt = $pdo->prepare("
            INSERT INTO Conductores 
            (LicenciaConducir, SeguroVehiculo, CuentaBancaria, Estado, ID_usuario, FotoCarnet, FotoCara, TelefonoContacto, AliasMP)
            VALUES (?, ?, ?, 'Esperando', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $licencia,
            $seguro,
            $banco,
            $usuario_id,
            $foto_carnet,
            $foto_cara,
            $telefono,
            $alias_mp
        ]);

        $conductor_id = $pdo->lastInsertId();

        $patente = trim($_POST['patente']); // NUEVO CAMPO

        /* Crear vehículo inicial */
        $stmt = $pdo->prepare("
            INSERT INTO Vehiculos
            (CantidadAsientos, Color, Modelo, Marca, Patente, Foto, PapelesAuto, FotoFrente, FotoCostado, FotoAtras)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $asientos,
            $color,
            $modelo,
            $marca,
            $patente,
            $foto_vehiculo,
            $papeles_auto,
            $foto_frente,
            $foto_costado,
            $foto_atras
        ]);
        
        $vehiculo_id = $pdo->lastInsertId();
        
        /* Conectar ambos */
        $stmt = $pdo->prepare("
            INSERT INTO ConductorVehiculo (ID_conductor, ID_vehiculo)
            VALUES (?, ?)
        ");
        $stmt->execute([$conductor_id, $vehiculo_id]);

        $pdo->commit();

        header('Location: index.php?msg=solicitud_enviada');
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<p style='color:red'>Error al enviar la solicitud.</p>";
    }
}
?>

<h2>Convertirme en conductor</h2>

<form method="post" enctype="multipart/form-data">
    <h3>Tus Datos de Conductor</h3>
    <label>Licencia de Conducir (Número)</label><br>
    <input type="text" name="licencia_conducir" required><br><br>

    <label>Número de Contacto</label><br>
    <input type="text" name="telefono_contacto" placeholder="Tu número de celular" required><br><br>

    <label>Cuenta Bancaria (CBU para cobros)</label><br>
    <input type="text" name="cuenta_bancaria" required><br><br>

    <label>Alias de MercadoPago</label><br>
    <input type="text" name="alias_mp" placeholder="tu.alias.mp" required><br><br>

    <label>Foto de tu Carnet de Conducir (frente y dorso en una imagen o collage)</label><br>
    <input type="file" name="foto_carnet" accept="image/*" required><br><br>

    <label>Foto Cara (Selfie clara y visible)</label><br>
    <input type="file" name="foto_cara" accept="image/*" required><br><br>

    <hr>

    <h3>Vehículo principal</h3>

    <input name="marca" placeholder="Marca" required><br>
    <input name="modelo" placeholder="Modelo" required><br>
    <input name="color" placeholder="Color" required><br>
    <input name="patente" placeholder="Patente (Ej: AB123CD)" required><br>
    <input type="number" name="asientos" min="1" required placeholder="Cantidad de Asientos disponibles"><br><br>

    <label>Póliza del Seguro Vehicular</label><br>
    <input type="text" name="seguro_vehiculo" required><br><br>

    <label>Papeles del Auto (cédula verde/azul)</label><br>
    <input type="file" name="papeles_auto" accept="image/*" required><br><br>

    <label>Foto Frente del auto (donde se vea la patente si es posible)</label><br>
    <input type="file" name="foto_frente" accept="image/*" required><br><br>

    <label>Foto Costado del auto</label><br>
    <input type="file" name="foto_costado" accept="image/*" required><br><br>

    <label>Foto Atrás del auto</label><br>
    <input type="file" name="foto_atras" accept="image/*" required><br><br>

    <button style="width: 100%; font-size: 1.1em; padding: 15px;">Enviar solicitud de revisión</button>
</form>
