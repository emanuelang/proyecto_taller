<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

/* ============================
   TRAER CIUDADES
============================ */
$stmt_ciudades = $pdo->query("SELECT DISTINCT CiudadOrigen AS nombre FROM Publicaciones UNION SELECT DISTINCT CiudadDestino AS nombre FROM Publicaciones ORDER BY nombre");
$ciudades = $stmt_ciudades->fetchAll(PDO::FETCH_ASSOC);

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
           c.ID_conductor AS conductor_id
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
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
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<h1>Carpooling</h1>

<?php if (!isset($_SESSION['user_id'])): ?>

    <a href="<?= BASE_URL ?>login.php">Iniciar sesión</a> |
    <a href="<?= BASE_URL ?>registro_usuario.php">Registrarse</a>

<?php else: ?>

    <p>
        Hola <?= htmlspecialchars($_SESSION['nombre']) ?> |
        <a href="<?= BASE_URL ?>index.php">Ver viajes</a> |
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php">Mis reservas</a> |

        <?php if (!$_SESSION['is_conductor']): ?>
            <a href="<?= BASE_URL ?>registro_conductor.php">Convertirme en conductor</a> |
        <?php else: ?>
            <a href="<?= BASE_URL ?>conductor/dashboard.php">Panel conductor</a> |
        <?php endif; ?>

        <a href="<?= BASE_URL ?>logout.php">Salir</a>
    </p>

<?php endif; ?>

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

<h2>Buscar viajes</h2>

<form method="GET" style="margin-bottom:20px;">

    <select name="origen">
        <option value="">Salida</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($origen == $c['nombre']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="destino">
        <option value="">Llegada</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= htmlspecialchars($c['nombre']) ?>" <?= ($destino == $c['nombre']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="orden">
        <option value="">Ordenar</option>
        <option value="precio_asc" <?= ($orden=='precio_asc')?'selected':'' ?>>Precio más barato</option>
        <option value="precio_desc" <?= ($orden=='precio_desc')?'selected':'' ?>>Precio más caro</option>
        <option value="fecha_desc" <?= ($orden=='fecha_desc')?'selected':'' ?>>Más nuevo</option>
        <option value="fecha_asc" <?= ($orden=='fecha_asc')?'selected':'' ?>>Más viejo</option>
    </select>

    <button>Buscar</button>
</form>

<hr>

<h2>Viajes disponibles</h2>

<?php if (empty($viajes)): ?>
    <p>No hay viajes disponibles.</p>
<?php endif; ?>

<?php foreach ($viajes as $v): ?>
    <div class="viaje">
        <strong>
            <?= htmlspecialchars($v['origen_nombre']) ?> →
            <?= htmlspecialchars($v['destino_nombre']) ?>
        </strong><br>

        Fecha: <?= $v['fecha'] ?><br>
        Precio: $<?= $v['precio'] ?><br>
        Conductor: <?= htmlspecialchars($v['conductor_nombre']) ?><br><br>

        <?php
        if (
            isset($_SESSION['user_id']) &&
            (
                !$_SESSION['is_conductor'] ||
                $_SESSION['conductor_id'] != $v['conductor_id']
            )
        ):
        ?>
            <a href="<?= BASE_URL ?>reservar_viaje.php?id=<?= $v['id'] ?>">Reservar</a>
        <?php endif; ?>
    </div>
    <hr>
<?php endforeach; ?>

</body>
</html>
