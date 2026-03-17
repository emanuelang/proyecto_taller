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

    $foto_vehiculo = subirArchivo('foto_vehiculo', __DIR__ . '/uploads/vehiculos');

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
            (LicenciaConducir, SeguroVehiculo, CuentaBancaria, Estado, ID_usuario)
            VALUES (?, ?, ?, 'Esperando', ?)
        ");
        $stmt->execute([
            $licencia,
            $seguro,
            $banco,
            $usuario_id
        ]);

        $conductor_id = $pdo->lastInsertId();

        /* Crear vehículo inicial */
        $stmt = $pdo->prepare("
            INSERT INTO Vehiculos
            (CantidadAsientos, Color, Modelo, Marca, Foto)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $asientos,
            $color,
            $modelo,
            $marca,
            $foto_vehiculo
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

    <label>Licencia de Conducir (Número)</label><br>
    <input type="text" name="licencia_conducir" required><br><br>

    <label>Póliza del Seguro Vehicular</label><br>
    <input type="text" name="seguro_vehiculo" required><br><br>

    <label>Cuenta Bancaria (CBU / ALIAS)</label><br>
    <input type="text" name="cuenta_bancaria" required><br><br>

    <hr>

    <h3>Vehículo inicial</h3>

    <input name="marca" placeholder="Marca" required><br>
    <input name="modelo" placeholder="Modelo" required><br>
    <input name="color" placeholder="Color" required><br>
    <input type="number" name="asientos" min="1" required placeholder="Cantidad de Asientos"><br><br>

    <label>Foto del vehículo (opcional)</label><br>
    <input type="file" name="foto_vehiculo" accept="image/*"><br><br>

    <button>Enviar solicitud</button>
</form>
