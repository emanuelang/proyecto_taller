<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

/* ============================
   TRAER CIUDADES...
============================ */
$ciudades = $pdo->query("SELECT * FROM ciudades ORDER BY nombre")->fetchAll();

/* ============================
   CAPTURAR FILTROS
============================ */
$origen = $_GET['origen'] ?? '';
$destino = $_GET['destino'] ?? '';
$orden = $_GET['orden'] ?? '';

/* ============================
   QUERY DINÁMICA DE VIAJES
============================ */
$sql = "
    SELECT v.*, 
           u.nombre AS conductor_nombre,
           c1.nombre AS origen_nombre,
           c2.nombre AS destino_nombre
    FROM viajes v
    JOIN conductores c ON v.conductor_id = c.id
    JOIN usuarios u ON c.usuario_id = u.id
    JOIN ciudades c1 ON v.origen_id = c1.id
    JOIN ciudades c2 ON v.destino_id = c2.id
    WHERE v.fecha >= NOW()
";

$params = [];

if ($origen !== '') {
    $sql .= " AND v.origen_id = ?";
    $params[] = $origen;
}

if ($destino !== '') {
    $sql .= " AND v.destino_id = ?";
    $params[] = $destino;
}

switch ($orden) {
    case 'precio_asc':
        $sql .= " ORDER BY v.precio ASC";
        break;
    case 'precio_desc':
        $sql .= " ORDER BY v.precio DESC";
        break;
    case 'fecha_desc':
        $sql .= " ORDER BY v.fecha DESC";
        break;
    case 'fecha_asc':
        $sql .= " ORDER BY v.fecha ASC";
        break;
    default:
        $sql .= " ORDER BY v.fecha ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viajes = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/header.php'; ?>

<h2>Buscar viajes</h2>

<form method="GET" style="margin-bottom:20px;">

    <select name="origen">
        <option value="">Salida</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($origen == $c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="destino">
        <option value="">Llegada</option>
        <?php foreach ($ciudades as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($destino == $c['id']) ? 'selected' : '' ?>>
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

        <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $v['id'] ?>" class="btn" style="display: block; text-align: center; margin-top: 15px;">Ver Detalle</a>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>
