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
   CAPTURAR FILTROS Y PAGINACIÓN...
============================ */
$origen = $_GET['origen'] ?? '';
$destino = $_GET['destino'] ?? '';
$orden = $_GET['orden'] ?? '';
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;

$limite = 30;
$offset = ($pagina_actual - 1) * $limite;

/* ============================
   CONDICIONES BASE
============================ */
$where_sql = "WHERE p.HoraSalida >= NOW() AND p.Estado = 'Activa'";
$params = [];

if ($origen !== '') {
    $where_sql .= " AND p.CiudadOrigen = ?";
    $params[] = $origen;
}

if ($destino !== '') {
    $where_sql .= " AND p.CiudadDestino = ?";
    $params[] = $destino;
}

/* ============================
   CONTAR TOTAL DE VIAJES
============================ */
$sql_count = "
    SELECT COUNT(*) 
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    $where_sql
";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_viajes = $stmt_count->fetchColumn();
$total_paginas = ceil($total_viajes / $limite);

if ($pagina_actual > $total_paginas && $total_paginas > 0) {
    $pagina_actual = $total_paginas;
    $offset = ($pagina_actual - 1) * $limite;
}

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
           (v.CantidadAsientos - (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada')) AS asientos_disp
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    $where_sql
";

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
    case 'asientos_asc':
        $sql .= " ORDER BY asientos_disp ASC";
        break;
    default:
        $sql .= " ORDER BY p.HoraSalida ASC";
}

$sql .= " LIMIT $limite OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ============================
   CÁLCULO RANGO DE PÁGINAS VISIBLES
============================ */
$max_paginas_visibles = 6;
if ($pagina_actual <= $max_paginas_visibles) {
    $start_page = 1;
    $end_page = min($max_paginas_visibles, $total_paginas);
} else {
    $start_page = $pagina_actual - $max_paginas_visibles + 1;
    $end_page = $pagina_actual;
}

// Preparar base URL para los botones de paginación manteniendo los filtros
$base_url_params = $_GET;
unset($base_url_params['pagina']);
$query_string = http_build_query($base_url_params);
$query_separator = empty($query_string) ? '?' : '?' . $query_string . '&';

require_once __DIR__ . '/header.php';
?>


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
    <form method="GET" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; background: #ffffff; padding: 10px 15px; border-radius: 50px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); width: 100%; max-width: 850px; border: 1px solid #e2e8f0; justify-content: center;">

        <datalist id="ciudades_list">
            <?php foreach ($ciudades as $c): ?>
                <option value="<?= htmlspecialchars($c['nombre']) ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <span style="color: #94A3B8; margin-left: 10px;">📍</span>
        <input type="text" name="origen" list="ciudades_list" placeholder="Salida" style="flex: 1; min-width: 130px; border: none; background: transparent; padding: 10px 5px; font-size: 1rem; outline: none; color: #475569; margin:0;" value="<?= htmlspecialchars($origen) ?>" autocomplete="off">

        <div style="width: 1px; height: 35px; background-color: #cbd5e1; display: inline-block;"></div>

        <span style="color: #94A3B8; margin-left: 5px;">🏁</span>
        <input type="text" name="destino" list="ciudades_list" placeholder="Llegada" style="flex: 1; min-width: 130px; border: none; background: transparent; padding: 10px 5px; font-size: 1rem; outline: none; color: #475569; margin:0;" value="<?= htmlspecialchars($destino) ?>" autocomplete="off">

        <div style="width: 1px; height: 35px; background-color: #cbd5e1; display: inline-block;"></div>

        <select name="orden" style="flex: 1; min-width: 160px; border: none; background: transparent; padding: 10px; font-size: 1rem; outline: none; cursor: pointer; color: #475569; margin:0;">
            <option value="">Ordenar</option>
            <option value="precio_asc" <?= ($orden=='precio_asc')?'selected':'' ?>>Precio más barato</option>
            <option value="precio_desc" <?= ($orden=='precio_desc')?'selected':'' ?>>Precio más caro</option>
            <option value="fecha_desc" <?= ($orden=='fecha_desc')?'selected':'' ?>>Más nuevo</option>
            <option value="fecha_asc" <?= ($orden=='fecha_asc')?'selected':'' ?>>Más viejo</option>
            <option value="asientos_desc" <?= ($orden=='asientos_desc')?'selected':'' ?>>Más asientos disponibles</option>
            <option value="asientos_asc" <?= ($orden=='asientos_asc')?'selected':'' ?>>Menos asientos disponibles</option>
        </select>

        <button type="submit" class="btn success-bg" style="border-radius: 30px; padding: 12px 30px; margin: 0; white-space: nowrap; font-size: 1rem;">
            🔍 Buscar
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
    <div class="viaje card" style="display: flex; flex-direction: column; justify-content: space-between;">
        <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                <span class="badge badge-primary">Sale el <?= date('d/m/Y', strtotime($v['fecha'])) ?></span>
                <span style="font-size: 1.25em; font-weight: bold; color: var(--success);">$<?= number_format($v['precio'], 2) ?></span>
            </div>

            <h3 style="margin: 5px 0 15px 0; font-size: 1.3em; color: var(--text-main); display:flex; align-items: center; gap: 8px;">
                <?= htmlspecialchars($v['origen_nombre']) ?>
                <span style="color: #CBD5E1; font-weight: 300;">→</span> 
                <?= htmlspecialchars($v['destino_nombre']) ?>
            </h3>

            <div style="display: flex; flex-direction: column; gap: 8px; color: #475569; font-size: 0.95em; margin-bottom: 20px;">
                <div>🕒 <?= date('H:i', strtotime($v['fecha'])) ?> hs</div>
                <div>👤 <?= htmlspecialchars($v['conductor_nombre']) ?></div>
                <div>💺 <?= max(0, $v['asientos_disp']) ?> asientos disponibles</div>
            </div>
        </div>

        <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $v['id'] ?>" class="btn btn-outline" style="display: block; text-align: center; width: 100%;">Ver Detalle</a>
    </div>
<?php endforeach; ?>
</div>

<?php if ($total_paginas > 1): ?>
    <div style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 40px; margin-bottom: 40px; flex-wrap: wrap;">
        <!-- Primera página y Anterior -->
        <?php if ($pagina_actual > 1): ?>
            <a href="<?= htmlspecialchars($query_separator . 'pagina=1') ?>" class="btn" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; text-decoration: none;" title="Primera página">&lt;&lt;</a>
            <a href="<?= htmlspecialchars($query_separator . 'pagina=' . ($pagina_actual - 1)) ?>" class="btn" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; text-decoration: none;" title="Página anterior">&lt;</a>
        <?php else: ?>
            <span style="background: #e2e8f0; color: #94a3b8; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; cursor: not-allowed; user-select: none;">&lt;&lt;</span>
            <span style="background: #e2e8f0; color: #94a3b8; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; cursor: not-allowed; user-select: none;">&lt;</span>
        <?php endif; ?>

        <!-- Números de página -->
        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
            <?php if ($i == $pagina_actual): ?>
                <span style="background: var(--primary); color: white; padding: 8px 14px; border-radius: 6px; font-weight: bold; border: 1px solid var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><?= $i ?></span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($query_separator . 'pagina=' . $i) ?>" class="btn" style="background: #ffffff; color: #475569; padding: 8px 14px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; text-decoration: none; transition: all 0.2s;"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <!-- Siguiente y Última -->
        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="<?= htmlspecialchars($query_separator . 'pagina=' . ($pagina_actual + 1)) ?>" class="btn" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; text-decoration: none;" title="Página siguiente">&gt;</a>
            <a href="<?= htmlspecialchars($query_separator . 'pagina=' . $total_paginas) ?>" class="btn" style="background: #f1f5f9; color: #475569; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; text-decoration: none;" title="Última página">&gt;&gt;</a>
        <?php else: ?>
            <span style="background: #e2e8f0; color: #94a3b8; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; cursor: not-allowed; user-select: none;">&gt;</span>
            <span style="background: #e2e8f0; color: #94a3b8; padding: 8px 12px; border-radius: 6px; font-weight: bold; border: 1px solid #cbd5e1; cursor: not-allowed; user-select: none;">&gt;&gt;</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
