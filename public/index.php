<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/trips.php';
require_once __DIR__ . '/../core/session_guard.php';

sync_finished_trips($pdo);
if (isset($_SESSION['user_id'])) {
    require_active_session($pdo);
}

$usuario_logueado = isset($_SESSION['user_id']);

/* ============================
   TRAER CIUDADES...
============================ */
$ciudades_predefinidas = [
    'Aldea Brasilera', 'Aldea María Luisa', 'Aldea San Antonio', 'Aldea San Miguel',
    'Aldea Valle María', 'Alcaraz', 'Aranguren', 'Basavilbaso', 'Bovril',
    'Ceibas', 'Cerrito', 'Chajarí', 'Colón', 'Colonia Avellaneda',
    'Colonia Ayuí', 'Colonia Elía', 'Colonia Ensayo', 'Concepción del Uruguay',
    'Concordia', 'Crespo', 'Diamante', 'Estancia Grande', 'Federal',
    'Federación', 'General Campos', 'General Galarza', 'General Ramírez',
    'Gilbert', 'Gobernador Mansilla', 'Gualeguay', 'Gualeguaychú', 'Hasenkamp',
    'Hernandarias', 'Hernández', 'Herrera', 'Ibicuy', 'La Criolla', 'La Paz',
    'Larroque', 'Libertador San Martín', 'Los Charrúas', 'Lucas González',
    'Maciá', 'María Grande', 'Nogoyá', 'Oro Verde', 'Paraná',
    'Piedras Blancas', 'Pronunciamiento', 'Pueblo Belgrano', 'Puerto Yeruá',
    'Rosario del Tala', 'San Benito', 'San Gustavo', 'San Jaime de la Frontera',
    'San José', 'San José de Feliciano', 'San Justo', 'San Salvador',
    'Santa Ana', 'Santa Elena', 'Sauce de Luna', 'Seguí', 'Urdinarrain',
    'Viale', 'Victoria', 'Villa Clara', 'Villa del Rosario', 'Villa Elisa',
    'Villa Hernandarias', 'Villa Mantero', 'Villa Paranacito',
    'Villa Urquiza', 'Villaguay'
];

$todas_las_ciudades = array_unique($ciudades_predefinidas);
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
$where_sql = "WHERE p.HoraSalida >= NOW()
    AND p.Estado = 'Activa'
    AND c.Estado = 'Aceptada'
    AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW())
    AND u.estado = 'activo'
    AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta <= NOW())
    AND v.Estado = 'Aceptado'";
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
           (SELECT AVG(cal.Puntuacion) FROM Calificaciones cal WHERE cal.ID_conductor = c.ID_conductor) AS promedio_calif,
           (SELECT COUNT(*) FROM Calificaciones cal WHERE cal.ID_conductor = c.ID_conductor) AS total_calificaciones,
           u.FotoPerfil AS conductor_foto,
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

<div class="page-shell">
    <h1 class="page-title">Buscar viajes</h1>
    <p class="page-subtitle">Encontrá el viaje perfecto para tu próximo destino</p>

    <form method="GET" class="search-card">
        <div class="autocomplete-field">
            <input type="text" name="origen" class="city-autocomplete" placeholder="Ciudad de salida" value="<?= htmlspecialchars($origen) ?>" minlength="2" maxlength="100" autocomplete="off" data-cities='<?= htmlspecialchars(json_encode(array_column($ciudades, 'nombre'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>
            <div class="city-suggestions" role="listbox"></div>
        </div>

        <div class="autocomplete-field">
            <input type="text" name="destino" class="city-autocomplete" placeholder="Ciudad de llegada" value="<?= htmlspecialchars($destino) ?>" minlength="2" maxlength="100" autocomplete="off" data-cities='<?= htmlspecialchars(json_encode(array_column($ciudades, 'nombre'), JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>
            <div class="city-suggestions" role="listbox"></div>
        </div>

        <select name="orden">
            <option value="">Ordenar por</option>
            <option value="precio_asc" <?= ($orden=='precio_asc')?'selected':'' ?>>Tarifa: menor a mayor</option>
            <option value="precio_desc" <?= ($orden=='precio_desc')?'selected':'' ?>>Tarifa: mayor a menor</option>
            <option value="fecha_asc" <?= ($orden=='fecha_asc')?'selected':'' ?>>Salida: fechas mas proximas</option>
            <option value="fecha_desc" <?= ($orden=='fecha_desc')?'selected':'' ?>>Salida: fechas mas lejanas</option>
            <option value="asientos_desc" <?= ($orden=='asientos_desc')?'selected':'' ?>>Disponibilidad: mayor a menor</option>
            <option value="asientos_asc" <?= ($orden=='asientos_asc')?'selected':'' ?>>Disponibilidad: menor a mayor</option>
        </select>

        <button type="submit">Buscar</button>
    </form>

    <div class="section-heading">
        <h2>Viajes disponibles</h2>
        <span class="results-count">(<?= (int)$total_viajes ?> resultados)</span>
    </div>

    <div class="travel-grid">
    <?php if (empty($viajes)): ?>
        <div class="card" style="grid-column:1/-1; text-align:center;">
            <p class="text-muted" style="font-size:1.1em;">No hay viajes disponibles con esos filtros.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($viajes as $v): ?>
        <div class="viaje trip-card">
            <div>
                <div class="trip-top">
                    <span class="badge badge-primary">▣ <?= date('d M Y', strtotime($v['fecha'])) ?></span>
                    <span class="trip-price">$<?= number_format($v['precio'], 0, ',', '.') ?></span>
                </div>

                <div class="trip-route">
                    <div>
                        <small>Salida</small>
                        <strong><?= htmlspecialchars($v['origen_nombre']) ?></strong>
                    </div>
                    <div class="route-arrow">→</div>
                    <div style="text-align:right;">
                        <small>Llegada</small>
                        <strong><?= htmlspecialchars($v['destino_nombre']) ?></strong>
                    </div>
                </div>

                <div class="trip-meta">
                    <span>◷ <?= date('H:i', strtotime($v['fecha'])) ?> hs</span>
                    <span>♙ <?= max(0, $v['asientos_disp']) ?> asientos</span>
                </div>
            </div>

            <div class="trip-footer">
                <?php if ($usuario_logueado): ?>
                    <div class="driver-chip">
                        <?php if (!empty($v['conductor_foto'])): ?>
                            <img src="<?= htmlspecialchars($v['conductor_foto']) ?>" class="mini-avatar-img" alt="Foto de <?= htmlspecialchars($v['conductor_nombre']) ?>">
                        <?php else: ?>
                            <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($v['conductor_nombre'], 0, 1))) ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($v['conductor_nombre']) ?></strong>
                            <?php if (!empty($v['promedio_calif'])): ?>
                                <div class="driver-rating">★ <?= number_format(floor($v['promedio_calif'] * 10) / 10, 1, ',', '.') ?> <span>(<?= (int)$v['total_calificaciones'] ?>)</span></div>
                            <?php else: ?>
                                <div class="driver-rating muted">Nuevo</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $v['id'] ?>" class="btn btn-outline">Ver Detalle</a>
                <?php else: ?>
                    <div class="driver-chip">
                        <span class="mini-avatar">?</span>
                        <div>
                            <strong>Conductor protegido</strong>
                            <div class="driver-rating muted">Registrate para ver más</div>
                        </div>
                    </div>
                    <a href="<?= BASE_URL ?>login.php" class="btn btn-outline">Iniciar sesión</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if ($total_paginas > 1): ?>
        <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin:34px 0 0; flex-wrap:wrap;">
            <?php if ($pagina_actual > 1): ?>
                <a href="<?= htmlspecialchars($query_separator . 'pagina=1') ?>" class="btn btn-outline">&lt;&lt;</a>
                <a href="<?= htmlspecialchars($query_separator . 'pagina=' . ($pagina_actual - 1)) ?>" class="btn btn-outline">&lt;</a>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $pagina_actual): ?>
                    <span class="btn" style="pointer-events:none;"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($query_separator . 'pagina=' . $i) ?>" class="btn btn-outline"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="<?= htmlspecialchars($query_separator . 'pagina=' . ($pagina_actual + 1)) ?>" class="btn btn-outline">&gt;</a>
                <a href="<?= htmlspecialchars($query_separator . 'pagina=' . $total_paginas) ?>" class="btn btn-outline">&gt;&gt;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>

