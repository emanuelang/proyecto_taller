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
    $patente = trim($_POST['patente']); // NUEVO CAMPO

    // Validaciones PHP
    $errores = [];
    if (!preg_match('/^[0-9]{8,15}$/', $telefono)) $errores[] = "El teléfono debe tener entre 8 y 15 dígitos numéricos.";
    if (strlen($licencia) > 100) $errores[] = "La licencia de conducir es muy larga.";
    if (strlen($banco) > 100) $errores[] = "La cuenta bancaria es muy larga.";
    if (strlen($alias_mp) > 100) $errores[] = "El alias es muy largo.";
    if (strlen($marca) > 100) $errores[] = "La marca es muy larga.";
    if (strlen($modelo) > 100) $errores[] = "El modelo es muy largo.";
    if (strlen($color) > 50) $errores[] = "El color es muy largo.";
    if (!preg_match('/^[A-Za-z0-9]{6,7}$/', $patente)) $errores[] = "La patente debe tener 6 o 7 caracteres alfanuméricos.";
    
    if (empty($errores)) {
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
} // CIERRA if (empty($errores))
} // CIERRA if ($_SERVER['REQUEST_METHOD'] === 'POST')
?>

<div class="card" style="max-width: 600px; margin: 40px auto; padding: 30px;">
    <h2 style="margin-top: 0; color: var(--primary); text-align: center;">Convertirme en conductor</h2>

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
        <h3 style="color: var(--primary);">Tus Datos de Conductor</h3>
        
        <label>Licencia de Conducir (Número)</label>
        <input type="text" name="licencia_conducir" required maxlength="100">

        <label>Número de Contacto</label>
        <input type="text" name="telefono_contacto" placeholder="Tu número de celular" required pattern="[0-9]{8,15}" title="Debe contener entre 8 y 15 números">

        <label>Cuenta Bancaria (CBU para cobros)</label>
        <input type="text" name="cuenta_bancaria" required maxlength="100">

        <label>Alias de MercadoPago</label>
        <input type="text" name="alias_mp" placeholder="tu.alias.mp" required maxlength="100">

        <label>Foto de tu Carnet de Conducir (frente y dorso en una imagen o collage)</label>
        <input type="file" name="foto_carnet" accept="image/*" required>

        <label>Foto Cara (Selfie clara y visible)</label>
        <input type="file" name="foto_cara" accept="image/*" required>

        <hr style="margin: 30px 0; border: 0; border-top: 1px solid var(--border-color);">

        <h3 style="color: var(--primary);">Vehículo principal</h3>

        <label>Marca:</label>
        <input type="text" name="marca" placeholder="Ej: Toyota" required maxlength="100">
        
        <label>Modelo:</label>
        <input type="text" name="modelo" placeholder="Ej: Corolla" required maxlength="100">
        
        <label>Color:</label>
        <input type="text" name="color" placeholder="Ej: Blanco" required maxlength="50">
        
        <label>Patente:</label>
        <input type="text" name="patente" placeholder="Ej: AB123CD" required minlength="6" maxlength="7" pattern="[A-Za-z0-9]{6,7}" title="Debe contener 6 o 7 caracteres alfanuméricos">
        
        <label>Cantidad de Asientos disponibles:</label>
        <input type="number" name="asientos" min="1" max="10" required>

        <label>Póliza del Seguro Vehicular</label>
        <input type="text" name="seguro_vehiculo" required maxlength="100">

        <label>Papeles del Auto (cédula verde/azul)</label>
        <input type="file" name="papeles_auto" accept="image/*" required>

        <label>Foto Frente del auto (donde se vea la patente si es posible)</label>
        <input type="file" name="foto_frente" accept="image/*" required>

        <label>Foto Costado del auto</label>
        <input type="file" name="foto_costado" accept="image/*" required>

        <label>Foto Atrás del auto</label>
        <input type="file" name="foto_atras" accept="image/*" required>

        <button type="submit" style="width: 100%; font-size: 1.1em; padding: 15px; margin-top: 20px; background-color: var(--success);">Enviar solicitud de revisión</button>
    </form>
</div>
