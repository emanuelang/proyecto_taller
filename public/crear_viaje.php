<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$ciudades_predefinidas = [
    'ParanÃ¡', 'Concordia', 'GualeguaychÃº', 'ConcepciÃ³n del Uruguay', 
    'Gualeguay', 'ColÃ³n', 'FederaciÃ³n', 'La Paz', 'Villaguay', 
    'Victoria', 'ChajarÃ­', 'Crespo', 'Diamante', 'Federal', 
    'NogoyÃ¡', 'Rosario del Tala', 'San Salvador', 'San JosÃ© de Feliciano', 
    'Santa Elena', 'Oro Verde', 'Buenos Aires', 'CÃ³rdoba', 'Rosario', 'La Plata'
];

$stmt_ciudades = $pdo->query("SELECT DISTINCT CiudadOrigen AS nombre FROM Publicaciones UNION SELECT DISTINCT CiudadDestino AS nombre FROM Publicaciones");
$ciudades_db = $stmt_ciudades->fetchAll(PDO::FETCH_COLUMN);

$todas_las_ciudades = array_unique(array_merge($ciudades_predefinidas, $ciudades_db));
sort($todas_las_ciudades);

$ciudades = [];
foreach ($todas_las_ciudades as $c) {
    if (trim($c) !== '') {
        $ciudades[] = ['nombre' => trim($c)];
    }
}

// NUEVO: Obtenemos los vehÃ­culos del conductor logueado que estÃ©n aprobados
$stmt_v = $pdo->prepare("SELECT v.ID_vehiculo AS id, v.Marca AS marca, v.Modelo AS modelo, v.Patente AS patente FROM Vehiculos v JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo WHERE cv.ID_conductor = ? AND v.Estado = 'Aceptado'");
$stmt_v->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt_v->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $origen = $_POST['origen'];
    $destino = $_POST['destino'];
    $calle_salida = trim($_POST['calle_salida']);
    $fecha = $_POST['fecha'];
    $precio = $_POST['precio'];
    $vehiculo_id = $_POST['vehiculo_id']; // Capturamos el vehÃ­culo seleccionado
    $observaciones = $_POST['observaciones'] ?? '';

    $errores = [];
    if (strlen($calle_salida) > 200) {
        $errores[] = "La calle de salida es muy larga.";
    }

    if ((float)$precio <= 0 || (float)$precio > 1000000) {
        $errores[] = "El precio debe ser mayor a 0 y no superar $1.000.000.";
    }

    if ($origen === $destino) {
        $errores[] = "El origen y el destino no pueden ser la misma ciudad.";
    }

    if (strtotime($fecha) < strtotime('+23 hours 50 minutes')) { // Permitimos un margen de 10 min por demoras
        $errores[] = "El viaje debe programarse con al menos 24 horas de anticipaciÃ³n.";
    }

    // Validar que el vehÃ­culo seleccionado pertenezca al conductor y estÃ© aceptado
    $stmt_vehiculo = $pdo->prepare("SELECT v.ID_vehiculo FROM Vehiculos v JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo WHERE cv.ID_conductor = ? AND v.ID_vehiculo = ? AND v.Estado = 'Aceptado'");
    $stmt_vehiculo->execute([$_SESSION['conductor_id'], $vehiculo_id]);
    $vehiculo = $stmt_vehiculo->fetch();
    
    if (!$vehiculo) {
        $errores[] = "Error: El vehÃ­culo seleccionado no es vÃ¡lido o no ha sido aprobado.";
    }

    if (empty($errores)) {
        $distancia_km = null;
        $duracion_min = null;

        try {
            $context = stream_context_create([
                "http" => [
                    "header" => "User-Agent: CarpoolingTallerApp/1.0\r\n"
                ]
            ]);

            $url_origen = "https://nominatim.openstreetmap.org/search?q=" . urlencode($origen . ", Entre RÃ­os, Argentina") . "&format=json&limit=1";
            $res_orig = @file_get_contents($url_origen, false, $context);
            $data_orig = json_decode($res_orig, true);

            // Pausa breve para respetar las polÃ­ticas de nominatim (1 request por segundo)
            usleep(1000000); 

            $url_destino = "https://nominatim.openstreetmap.org/search?q=" . urlencode($destino . ", Entre RÃ­os, Argentina") . "&format=json&limit=1";
            $res_dest = @file_get_contents($url_destino, false, $context);
            $data_dest = json_decode($res_dest, true);

            if (!empty($data_orig) && !empty($data_dest)) {
                $lon1 = $data_orig[0]['lon'];
                $lat1 = $data_orig[0]['lat'];
                $lon2 = $data_dest[0]['lon'];
                $lat2 = $data_dest[0]['lat'];

                $url_osrm = "http://router.project-osrm.org/route/v1/driving/{$lon1},{$lat1};{$lon2},{$lat2}?overview=false";
                $res_osrm = @file_get_contents($url_osrm);
                $routeData = json_decode($res_osrm, true);

                if (isset($routeData['routes'][0])) {
                    $distancia_km = ceil($routeData['routes'][0]['distance'] / 1000); // Redondeado para arriba en KM
                    $duracion_min = ceil($routeData['routes'][0]['duration'] / 60); // DuraciÃ³n en minutos
                }
            }
        } catch (Exception $e) {
            // Falla silenciosa si las APIs no responden
        }

        // Si la API falla, aplicamos 24hs por seguridad para que el sistema valide la superposiciÃ³n de todas formas
        if ($duracion_min === null) {
            $duracion_min = 1440;
            $distancia_km = 0;
        }

        // --- VALIDACIÃ“N DE SUPERPOSICIÃ“N DE HORARIOS ---
        if ($duracion_min !== null) {
            $new_start = $fecha;
            $new_end = date('Y-m-d H:i:s', strtotime($fecha . " + $duracion_min minutes"));

            $stmt_overlap = $pdo->prepare("
                SELECT p.HoraSalida, p.DuracionMinutos, p.CiudadOrigen, p.CiudadDestino 
                FROM Publicaciones p
                JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
                WHERE cp.ID_conductor = ? 
                AND p.Estado = 'Activa'
                AND p.HoraSalida < ? 
                AND DATE_ADD(p.HoraSalida, INTERVAL p.DuracionMinutos MINUTE) > ?
            ");
            $stmt_overlap->execute([$_SESSION['conductor_id'], $new_end, $new_start]);
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
                INSERT INTO Publicaciones 
                (CiudadOrigen, CiudadDestino, CalleSalida, HoraSalida, Precio, Estado, DistanciaKM, DuracionMinutos, ID_vehiculo)
                VALUES (?, ?, ?, ?, ?, 'Activa', ?, ?, ?)
            ");

            $stmt->execute([
                $origen,
                $destino,
                $calle_salida,
                $fecha,
                $precio,
                $distancia_km,
                $duracion_min,
                $vehiculo_id
            ]);

            $publicacion_id = $pdo->lastInsertId();

            $stmt2 = $pdo->prepare("INSERT INTO ConductorPublicacion (ID_conductor, ID_publicacion) VALUES (?, ?)");
            $stmt2->execute([$_SESSION['conductor_id'], $publicacion_id]);

            header("Location: " . BASE_URL . "conductor/viajes.php");
            exit;
        }
    }
}
?>

<?php
$origen_def = $_POST['origen'] ?? $_GET['origen'] ?? '';
$destino_def = $_POST['destino'] ?? $_GET['destino'] ?? '';
$precio_def = $_POST['precio'] ?? $_GET['precio'] ?? '';
$obs_def = $_POST['observaciones'] ?? $_GET['observaciones'] ?? '';
$calle_def = $_POST['calle_salida'] ?? '';
$fecha_def = $_POST['fecha'] ?? '';
$vehiculo_def = $_POST['vehiculo_id'] ?? '';
?>

<?php include __DIR__ . '/header.php'; ?>

<div class="page-shell create-trip-page">
    <div class="create-trip-head">
        <div>
            <h1 class="page-title">Crear viaje</h1>
            <p class="page-subtitle">Publica una salida, elegi tu vehiculo y deja los datos listos para que otros pasajeros puedan reservar.</p>
        </div>
        <a href="<?= BASE_URL ?>conductor/dashboard.php" class="btn btn-outline">Volver al panel</a>
    </div>

    <?php if (!empty($errores)): ?>
        <div class="alert-error">
            <ul class="create-trip-alert-list">
                <?php foreach ($errores as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" class="create-trip-form">
        <?= csrf_field() ?>
        <section class="card create-trip-card">
            <div class="form-section-head">
                <span class="section-kicker">Ruta</span>
                <h2>Origen y destino</h2>
            </div>

            <div class="create-trip-grid">
                <div class="field-group">
                    <label>Origen</label>
                    <select name="origen" required>
                        <option value="">Seleccionar origen</option>
                        <?php foreach ($ciudades as $c): ?>
                            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($origen_def === $c['nombre']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group">
                    <label>Destino</label>
                    <select name="destino" required>
                        <option value="">Seleccionar destino</option>
                        <?php foreach ($ciudades as $c): ?>
                            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($destino_def === $c['nombre']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-group full-width">
                    <label>Calle de salida</label>
                    <input type="text" name="calle_salida" placeholder="Ej: Av. Corrientes 1234, esquina Callao" value="<?= htmlspecialchars($calle_def) ?>" required minlength="5" maxlength="120">
                </div>
            </div>
        </section>

        <section class="card create-trip-card">
            <div class="form-section-head">
                <span class="section-kicker">Detalles</span>
                <h2>Fecha, vehiculo y precio</h2>
            </div>

            <?php if (empty($vehiculos)): ?>
                <div class="alert-warning">
                    No tenes vehiculos aprobados. <a href="<?= BASE_URL ?>conductor/crear_vehiculo.php">Registra uno</a> o espera a que un administrador lo apruebe.
                </div>
            <?php else: ?>
                <div class="create-trip-grid">
                    <div class="field-group full-width">
                        <label>Vehiculo a utilizar</label>
                        <select name="vehiculo_id" required>
                            <option value="">Selecciona tu vehiculo</option>
                            <?php foreach ($vehiculos as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= ((string)$vehiculo_def === (string)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['marca'] . ' ' . $v['modelo'] . ' (' . $v['patente'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Fecha y hora</label>
                        <input type="datetime-local" name="fecha" value="<?= htmlspecialchars($fecha_def) ?>" required min="<?= date('Y-m-d\TH:i', strtotime('+24 hours')) ?>">
                    </div>

                    <div class="field-group">
                        <label>Precio por persona ($)</label>
                        <input type="number" name="precio" placeholder="Ej: 2500" value="<?= htmlspecialchars($precio_def) ?>" minlength="1" maxlength="7" required min="0" step="0.01">
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="card create-trip-card">
            <div class="form-section-head">
                <span class="section-kicker">Notas</span>
                <h2>Observaciones</h2>
            </div>

            <label>Informacion adicional</label>
            <textarea name="observaciones" placeholder="Ej: No se aceptan mascotas" rows="4" minlength="0" maxlength="500"><?= htmlspecialchars($obs_def) ?></textarea>
        </section>

        <div class="create-trip-actions">
            <a href="<?= BASE_URL ?>conductor/viajes.php" class="btn btn-outline">Cancelar</a>
            <button type="submit" <?= empty($vehiculos) ? 'disabled' : '' ?> class="btn success-bg">Publicar viaje</button>
        </div>
    </form>
</div>

</body>
</html>
