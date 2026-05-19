<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../core/storage.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

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

<?php
require_once __DIR__ . '/header.php';
?>

<div class="card" style="max-width: 650px; margin: 40px auto; padding: 40px; border-top: 5px solid var(--primary);">
    <h2 style="margin-top: 0; color: var(--primary); text-align: center; font-size: 2em;">Convertirme en conductor</h2>
    <p style="text-align: center; color: #64748b; margin-bottom: 30px;">Completa el formulario para validar tu perfil y empezar a publicar viajes.</p>

    <?php if (!empty($errores)): ?>
        <div style="background-color: #fef2f2; color: #b91c1c; padding: 15px; border-radius: 8px; border: 1px solid #fee2e2; margin-bottom: 25px;">
            <strong style="display: block; margin-bottom: 5px;">Por favor corrige los siguientes errores:</strong>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="max-width: 100%; border: none; padding: 0; box-shadow: none;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <div style="background: var(--primary); width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">1</div>
            <h3 style="margin: 0; color: var(--primary);">Tus Datos de Conductor</h3>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label>Licencia de Conducir (Número)</label>
                <input type="text"  name="licencia_conducir" required placeholder="Ej: 12345678" minlength="5" maxlength="25">
            </div>
            <div>
                <label>Número de Contacto</label>
                <input type="text"  name="telefono_contacto" placeholder="Ej: 1122334455" required pattern="[0-9]{8,15}" title="Debe contener entre 8 y 15 números" minlength="10" maxlength="15">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div>
                <label>Cuenta Bancaria (CBU/CVU)</label>
                <input type="text"  name="cuenta_bancaria" required placeholder="Tu CBU para cobros" minlength="22" maxlength="22">
            </div>
            <div>
                <label>Alias de MercadoPago</label>
                <input type="text"  name="alias_mp" placeholder="tu.alias.mp" required  minlength="6" maxlength="20">
            </div>
        </div>

        <div style="margin-top: 15px;">
            <label>Foto de tu Carnet de Conducir (Frente y dorso)</label>
            <input type="file" name="foto_carnet" accept="image/*" required style="padding: 8px;">
        </div>

        <div style="margin-top: 10px;">
            <label>Foto de tu Cara (Selfie clara)</label>
            <input type="file" name="foto_cara" accept="image/*" required style="padding: 8px;">
        </div>

        <hr style="margin: 40px 0;">

        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
            <div style="background: var(--primary); width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">2</div>
            <h3 style="margin: 0; color: var(--primary);">Vehículo principal</h3>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label>Marca:</label>
                <input type="text"  name="marca" placeholder="Ej: Toyota" required  minlength="2" maxlength="30">
            </div>
            <div>
                <label>Modelo:</label>
                <input type="text"  name="modelo" placeholder="Ej: Corolla" required  minlength="1" maxlength="40">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div>
                <label>Color:</label>
                <input type="text"  name="color" placeholder="Ej: Blanco" required  minlength="3" maxlength="20">
            </div>
            <div>
                <label>Patente:</label>
                <input type="text"  name="patente" placeholder="Ej: AB123CD" required pattern="[A-Za-z0-9]{6,7}" title="Debe contener 6 o 7 caracteres alfanuméricos" minlength="6" maxlength="10">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div>
                <label>Asientos disponibles:</label>
                <input type="number"  name="asientos" min="1" max="10" required minlength="1" maxlength="2">
            </div>
            <div>
                <label>Póliza del Seguro</label>
                <input type="text"  name="seguro_vehiculo" placeholder="Número de póliza" required  minlength="5" maxlength="40">
            </div>
        </div>

        <div style="margin-top: 15px;">
            <label>Papeles del Auto (cédula verde/azul)</label>
            <input type="file" name="papeles_auto" accept="image/*" required style="padding: 8px;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
            <div>
                <label>Foto Frente del auto</label>
                <input type="file" name="foto_frente" accept="image/*" required style="padding: 8px;">
            </div>
            <div>
                <label>Foto Costado del auto</label>
                <input type="file" name="foto_costado" accept="image/*" required style="padding: 8px;">
            </div>
        </div>

        <div style="margin-top: 10px;">
            <label>Foto Atrás del auto</label>
            <input type="file" name="foto_atras" accept="image/*" required style="padding: 8px;">
        </div>

        <button type="submit" class="success-bg" style="width: 100%; font-size: 1.2em; padding: 18px; margin-top: 40px; border-radius: 12px; box-shadow: 0 4px 12px rgba(132, 204, 22, 0.2);">
            🚀 Enviar solicitud de revisión
        </button>
    </form>

    <div style="text-align: center; margin-top: 25px;">
        <a href="index.php" style="color: #64748b; font-size: 0.95em;">← Cancelar y volver al inicio</a>
    </div>
</div>

</body>
</html>

