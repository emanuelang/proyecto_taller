<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

/* ============================
   TRAER CIUDADES...
============================ */
$ciudades_predefinidas = [
    'Paraná', 'Concordia', 'Gualeguaychú', 'Concepción del Uruguay', 
    'Gualeguay', 'Colón', 'Federación', 'La Paz', 'Villaguay', 
    'Victoria', 'Chajarí', 'Crespo', 'Diamante', 'Federal', 
    'Nogoyá', 'Rosario del Tala', 'San Salvador', 'San José de Feliciano', 
    'Santa Elena', 'Oro Verde', 'Buenos Aires', 'Córdoba', 'Rosario', 'La Plata'
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

/* ============================
   CAPTURAR FILTROS ...
============================ */
$origen = $_GET['origen'] ?? '';
$destino = $_GET['destino'] ?? '';
$orden = $_GET['orden'] ?? '';

/* ============================
   QUERY DINÁMICA DE VIAJES
============================ */
$sql = "
    SELECT p.*, 
           u.Nombre AS conductor_nombre,
           p.CiudadOrigen AS origen_nombre,
           p.CiudadDestino AS destino_nombre,
           p.HoraSalida AS fecha,
           p.Precio AS precio,
           p.ID_publicacion AS id,
           c.ID_conductor AS conductor_id,
           (v.CantidadAsientos - (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'activa')) AS asientos_disp
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    WHERE p.HoraSalida >= NOW() AND p.Estado = 'Activa'
";

$params = [];

if ($origen !== '') {
    $sql .= " AND p.CiudadOrigen = ?";
    $params[] = $origen;
}

if ($destino !== '') {
    $sql .= " AND p.CiudadDestino = ?";
    $params[] = $destino;
}

switch ($orden) {
    case 'precio_asc':
        $sql .= " ORDER BY p.Precio ASC";
        break;
    case 'precio_desc':
        $sql .= " ORDER BY p.Precio DESC";
        break;
    case 'fecha_desc':
        $sql .= " ORDER BY p.HoraSalida DESC";
        break;
    case 'fecha_asc':
        $sql .= " ORDER BY p.HoraSalida ASC";
        break;
    case 'asientos_desc':
        $sql .= " ORDER BY asientos_disp DESC";
        break;
    default:
        $sql .= " ORDER BY p.HoraSalida ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= time() ?>">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
    <!-- Botón toggle flotante SIEMPRE visible en la esquina superior izquierda -->
    <button id="sidebarMainToggle" class="sidebar-main-toggle">&#9776;</button>
    
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Sidebar Menu -->
    <div id="sidebarMenu" class="sidebar">
        <a href="#" class="sidebar-link">Perfil</a>
        <div class="sidebar-separator"></div>
        
        <a href="<?= BASE_URL ?>index.php" class="sidebar-link">Ver viajes</a>
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="sidebar-link">Mis reservas</a>

        <?php if (!$_SESSION['is_conductor']): ?>
            <a href="<?= BASE_URL ?>registro_conductor.php" class="sidebar-link">Convertirme en conductor</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>conductor/dashboard.php" class="sidebar-link">Panel conductor</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>manual.php" class="sidebar-link">Manual de Ayuda</a>

        <?php 
        $stmt_admin = $pdo->prepare("SELECT ID_administrador FROM administradores WHERE ID_usuario = ?");
        $stmt_admin->execute([$_SESSION['user_id']]);
        $es_admin = $stmt_admin->fetch() !== false;
        if ($es_admin): ?>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="sidebar-link" style="color: #10b981;">Panel de Admin</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>logout.php" class="sidebar-link sidebar-logout">Salir</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebarMenu');
            const overlay = document.getElementById('sidebarOverlay');
            const btnToggle = document.getElementById('sidebarMainToggle');

            function toggleSidebar() {
                sidebar.classList.toggle('active');
                if (sidebar.classList.contains('active')) {
                    overlay.style.display = 'block';
                    setTimeout(() => overlay.style.opacity = '1', 10);
                } else {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            }

            btnToggle.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
        });
    </script>
<?php endif; ?>

<h1>Carpooling</h1>

<div class="nav-menu">
<?php if (!isset($_SESSION['user_id'])): ?>
    <a href="<?= BASE_URL ?>login.php" class="btn">Iniciar sesión</a>
    <a href="<?= BASE_URL ?>registro_usuario.php">Registrarse</a>
<?php else: ?>
    <?php
    $stmt_admin = $pdo->prepare("SELECT ID_administrador FROM administradores WHERE ID_usuario = ?");
    $stmt_admin->execute([$_SESSION['user_id']]);
    $es_admin = $stmt_admin->fetch() !== false;
    ?>
    
    <span style="font-size: 1.1em; margin-bottom: 20px;">
        Hola <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong>
        <?php if ($es_admin): ?>
            <span style="color: #10b981; font-weight: bold; margin-left: 5px;">(Estás como admin)</span>
        <?php endif; ?>
    </span>
<?php endif; ?>
</div>

<hr>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'solicitud_enviada'): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; margin-bottom: 15px; border-radius: 4px;">
            <strong>¡Solicitud enviada exitosamente!</strong> Tu cuenta ha sido registrada y se encuentra <strong>Esperando</strong> la aprobación de un administrador.
        </div>
    <?php elseif ($_GET['msg'] === 'esperando_aprobacion'): ?>
        <div style="background-color: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeeba; margin-bottom: 15px; border-radius: 4px;">
            <strong>Aviso:</strong> Ya enviaste una solicitud para ser conductor y todavía está <strong>Esperando</strong> revisión por parte de los administradores.
        </div>
    <?php endif; ?>
<?php endif; ?>

<h2 style="text-align: center; margin-top: 20px; margin-bottom: 15px;">Buscar viajes</h2>

<div style="display: flex; justify-content: center; margin-bottom: 40px; padding: 0 10px;">
    <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; background: #ffffff; padding: 8px 12px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 850px; border: 1px solid #e2e8f0; justify-content: center;">

        <select name="origen" style="flex: 1; min-width: 140px; border: none; background: transparent; padding: 10px; font-size: 1rem; outline: none; cursor: pointer; color: #475569;">
            <option value="">Salida</option>
            <?php foreach ($ciudades as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($origen == $c['nombre']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div style="width: 1px; height: 35px; background-color: #cbd5e1; display: inline-block;"></div>

        <select name="destino" style="flex: 1; min-width: 140px; border: none; background: transparent; padding: 10px; font-size: 1rem; outline: none; cursor: pointer; color: #475569;">
            <option value="">Llegada</option>
            <?php foreach ($ciudades as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($destino == $c['nombre']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <div style="width: 1px; height: 35px; background-color: #cbd5e1; display: inline-block;"></div>

        <select name="orden" style="flex: 1; min-width: 160px; border: none; background: transparent; padding: 10px; font-size: 1rem; outline: none; cursor: pointer; color: #475569;">
            <option value="">Ordenar</option>
            <option value="precio_asc" <?= ($orden=='precio_asc')?'selected':'' ?>>Precio más barato</option>
            <option value="precio_desc" <?= ($orden=='precio_desc')?'selected':'' ?>>Precio más caro</option>
            <option value="fecha_desc" <?= ($orden=='fecha_desc')?'selected':'' ?>>Más nuevo</option>
            <option value="fecha_asc" <?= ($orden=='fecha_asc')?'selected':'' ?>>Más viejo</option>
            <option value="asientos_desc" <?= ($orden=='asientos_desc')?'selected':'' ?>>Más asientos disponibles</option>
        </select>

        <button type="submit" class="btn" style="border-radius: 30px; padding: 10px 25px; margin: 0; white-space: nowrap; font-size: 1rem;">
            Buscar
        </button>
    </form>
</div>

<hr>

<h2>Viajes disponibles</h2>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
<?php if (empty($viajes)): ?>
    <p>No hay viajes disponibles.</p>
<?php endif; ?>

<?php foreach ($viajes as $v): ?>
    <div class="viaje card" style="margin-bottom: 0;">
        <h3 style="margin-top: 0; color: var(--primary);">
            <?= htmlspecialchars($v['origen_nombre']) ?> →
            <?= htmlspecialchars($v['destino_nombre']) ?>
        </h3>

        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></p>
        <p><strong>Precio:</strong> $<?= number_format($v['precio'], 2) ?></p>
        <p><strong>Conductor:</strong> <?= htmlspecialchars($v['conductor_nombre']) ?></p>
        <p><strong>Asientos:</strong> <?= max(0, $v['asientos_disp']) ?> disponibles</p>

        <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $v['id'] ?>" class="btn" style="display: block; text-align: center; margin-top: 15px;">Ver Detalle</a>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>
