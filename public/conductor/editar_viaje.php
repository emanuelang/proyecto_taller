<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

// Traer viaje
$stmt = $pdo->prepare("
    SELECT p.* 
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);
$viaje = $stmt->fetch();

if (!$viaje) {
    die('Viaje no encontrado');
}

// Traer vehículos del conductor
$stmt = $pdo->prepare("
    SELECT v.* 
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    WHERE cv.ID_conductor = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ciudades list for the select dropdowns
$stmt_ciudades = $pdo->query("SELECT DISTINCT CiudadOrigen AS nombre FROM Publicaciones UNION SELECT DISTINCT CiudadDestino AS nombre FROM Publicaciones ORDER BY nombre");
$ciudades = $stmt_ciudades->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $fecha = $_POST['fecha'];
    $vehiculo_id = $_POST['vehiculo_id'];

    $errores = [];
    if ($origen === $destino) {
        $errores[] = "El origen y el destino no pueden ser la misma ciudad.";
    }

    $distancia_km = $viaje['DistanciaKM'];
    $duracion_min = $viaje['DuracionMinutos'];

    // Si cambió el origen o el destino, recalculamos distancia y tiempo
    if ($origen !== $viaje['CiudadOrigen'] || $destino !== $viaje['CiudadDestino']) {
        try {
            $context = stream_context_create(["http" => ["header" => "User-Agent: CarpoolingTallerApp/1.0\r\n"]]);
            $url_origen = "https://nominatim.openstreetmap.org/search?q=" . urlencode($origen . ", Entre Ríos, Argentina") . "&format=json&limit=1";
            $res_orig = @file_get_contents($url_origen, false, $context);
            $data_orig = json_decode($res_orig, true);

            usleep(1000000); 

            $url_destino = "https://nominatim.openstreetmap.org/search?q=" . urlencode($destino . ", Entre Ríos, Argentina") . "&format=json&limit=1";
            $res_dest = @file_get_contents($url_destino, false, $context);
            $data_dest = json_decode($res_dest, true);

            if (!empty($data_orig) && !empty($data_dest)) {
                $lon1 = $data_orig[0]['lon']; $lat1 = $data_orig[0]['lat'];
                $lon2 = $data_dest[0]['lon']; $lat2 = $data_dest[0]['lat'];
                $url_osrm = "http://router.project-osrm.org/route/v1/driving/{$lon1},{$lat1};{$lon2},{$lat2}?overview=false";
                $res_osrm = @file_get_contents($url_osrm);
                $routeData = json_decode($res_osrm, true);

                if (isset($routeData['routes'][0])) {
                    $distancia_km = ceil($routeData['routes'][0]['distance'] / 1000);
                    $duracion_min = ceil($routeData['routes'][0]['duration'] / 60);
                }
            }
        } catch (Exception $e) {}
    }

    // --- VALIDACIÓN DE SUPERPOSICIÓN DE HORARIOS ---
    if ($duracion_min !== null) {
        $new_start = $fecha;
        $new_end = date('Y-m-d H:i:s', strtotime($fecha . " + $duracion_min minutes"));

        $stmt_overlap = $pdo->prepare("
            SELECT p.HoraSalida, p.DuracionMinutos, p.CiudadOrigen, p.CiudadDestino 
            FROM Publicaciones p
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            WHERE cp.ID_conductor = ? 
            AND p.Estado = 'Activa'
            AND p.ID_publicacion != ?
            AND p.HoraSalida < ? 
            AND DATE_ADD(p.HoraSalida, INTERVAL p.DuracionMinutos MINUTE) > ?
        ");
        $stmt_overlap->execute([$_SESSION['conductor_id'], $id, $new_end, $new_start]);
        $overlap = $stmt_overlap->fetch();

        if ($overlap) {
            $h_salida = date('H:i', strtotime($overlap['HoraSalida']));
            $h_llegada = date('H:i', strtotime($overlap['HoraSalida'] . " + {$overlap['DuracionMinutos']} minutes"));
            $errores[] = "Ya tienes un viaje programado que se superpone con este horario: " . 
                         "{$overlap['CiudadOrigen']} a {$overlap['CiudadDestino']} (Sale $h_salida, llega aprox. $h_llegada).";
        }
    }

    if (empty($errores)) {
        $stmt = $pdo->prepare("
            UPDATE Publicaciones p
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            SET p.CiudadOrigen = ?, p.CiudadDestino = ?, p.HoraSalida = ?, p.ID_vehiculo = ?, 
                p.DistanciaKM = ?, p.DuracionMinutos = ?
            WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
        ");

        $stmt->execute([
            $origen,
            $destino,
            $fecha,
            $vehiculo_id,
            $distancia_km,
            $duracion_min,
            $id,
            $_SESSION['conductor_id']
        ]);

        header('Location: viajes.php');
        exit;
    }
}
?>

<h2>Editar viaje</h2>

<?php if (!empty($errores)): ?>
    <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
        <ul style="margin: 0; padding-left: 20px;">
            <?php foreach ($errores as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post">
    Origen:
    <select name="origen" required>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= $c['nombre'] == $viaje['CiudadOrigen'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br>

    Destino:
    <select name="destino" required>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= $c['nombre'] == $viaje['CiudadDestino'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select><br>

    Fecha:
    <input type="datetime-local"
           name="fecha"
           value="<?= date('Y-m-d\TH:i', strtotime($viaje['HoraSalida'])) ?>"
           required><br>

    Vehículo:
    <select name="vehiculo_id" required>
        <?php foreach ($vehiculos as $v): ?>
            <option value="<?= $v['ID_vehiculo'] ?>"
                <?= $v['ID_vehiculo'] == $viaje['ID_vehiculo'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['Marca']) ?>
                <?= htmlspecialchars($v['Modelo']) ?>
            </option>
        <?php endforeach; ?>
    </select><br><br>

    <button>Guardar cambios</button>
</form>

<a href="viajes.php">Cancelar</a>
