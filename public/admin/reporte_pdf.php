<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/trips.php';

sync_finished_trips($pdo);

function pdf_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function pdf_money(PDO $pdo, string $sql, array $params = []): float
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (float)$stmt->fetchColumn();
}

function pdf_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pdf_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function money_fmt(float $value): string
{
    return '$' . number_format($value, 0, ',', '.');
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS BusquedasViajes (
            ID_busqueda INT AUTO_INCREMENT PRIMARY KEY,
            ID_usuario INT NULL,
            CiudadOrigen VARCHAR(100) NULL,
            CiudadDestino VARCHAR(100) NULL,
            Orden VARCHAR(50) NULL,
            Resultados INT NOT NULL DEFAULT 0,
            IP VARCHAR(45) NULL,
            UserAgent VARCHAR(255) NULL,
            Fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    error_log('No se pudo asegurar la tabla BusquedasViajes: ' . $e->getMessage());
}

$generated_at = date('d/m/Y H:i');
$fecha_actual_reporte = date('Y-m-d');
$reporte_desde = $_GET['desde'] ?? date('Y-m-01');
$reporte_hasta = $_GET['hasta'] ?? $fecha_actual_reporte;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporte_desde)) {
    $reporte_desde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporte_hasta)) {
    $reporte_hasta = $fecha_actual_reporte;
}
if (strtotime($reporte_desde) > strtotime($fecha_actual_reporte)) {
    $reporte_desde = $fecha_actual_reporte;
}
if (strtotime($reporte_hasta) > strtotime($fecha_actual_reporte)) {
    $reporte_hasta = $fecha_actual_reporte;
}
if (strtotime($reporte_desde) > strtotime($reporte_hasta)) {
    [$reporte_desde, $reporte_hasta] = [$reporte_hasta, $reporte_desde];
}
$reporte_desde_dt = $reporte_desde . ' 00:00:00';
$reporte_hasta_dt = $reporte_hasta . ' 23:59:59';
$reporte_desde_legible = date('d/m/Y', strtotime($reporte_desde));
$reporte_hasta_legible = date('d/m/Y', strtotime($reporte_hasta));
$deleted_user_sql = "(u.Correo LIKE 'deleted\\_%@deleted.moveon.local' OR u.DNI LIKE 'deleted\\_%')";

$usuarios_total = pdf_scalar($pdo, "
    SELECT COUNT(*)
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
");
$usuarios_activos = pdf_scalar($pdo, "
    SELECT COUNT(*)
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
      AND NOT $deleted_user_sql
      AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta <= NOW())
      AND LOWER(u.estado) = 'activo'
");
$usuarios_baneados = pdf_scalar($pdo, "
    SELECT COUNT(*)
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
      AND NOT $deleted_user_sql
      AND u.BaneadoHasta > NOW()
");
$usuarios_eliminados = pdf_scalar($pdo, "
    SELECT COUNT(*)
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
      AND $deleted_user_sql
");

$pasajeros_total = pdf_scalar($pdo, "SELECT COUNT(*) FROM Pasajeros");
$pasajeros_con_reservas = pdf_scalar($pdo, "
    SELECT COUNT(DISTINCT pas.ID_usuario)
    FROM Pasajeros pas
    JOIN PasajerosReservas pr ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Reservas r ON r.ID_reserva = pr.ID_reserva
    WHERE r.Estado = 'Completada'
");

$conductores_total = pdf_scalar($pdo, "SELECT COUNT(*) FROM Conductores");
$conductores_aprobados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado = 'Aceptada'");
$conductores_pendientes = pdf_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado IN ('Esperando', 'Pendiente')");
$conductores_baneados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado = 'Aceptada' AND BaneadoHasta > NOW()");
$conductores_eliminados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado IN ('Eliminado', 'Rechazado')");

$vehiculos_total = pdf_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos");
$vehiculos_pendientes = pdf_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Pendiente'");
$vehiculos_aprobados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Aceptado'");
$vehiculos_suspendidos = pdf_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Suspendido'");
$vehiculos_eliminados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado IN ('Rechazado', 'Eliminado')");

$viajes_total = pdf_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$viajes_activos = pdf_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Activa' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$viajes_finalizados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Finalizada' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$viajes_cancelados = pdf_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Cancelada' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$viajes_mes = $viajes_total;

$reservas_total = pdf_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$reservas_confirmadas = pdf_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE Estado = 'Completada' AND FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$reservas_canceladas = pdf_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE Estado IN ('Cancelada', 'Rechazada') AND FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$reservas_mes = $reservas_total;

$reportes_conductores = pdf_scalar($pdo, "SELECT COUNT(*) FROM Reportes WHERE Fecha BETWEEN ? AND ?", [$reporte_desde, $reporte_hasta]);
$reportes_pasajeros = pdf_scalar($pdo, "SELECT COUNT(*) FROM ReportesPasajeros WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$reportes_pasajeros_pendientes = pdf_scalar($pdo, "SELECT COUNT(*) FROM ReportesPasajeros WHERE Estado = 'Pendiente' AND Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$soporte_total = pdf_scalar($pdo, "SELECT COUNT(*) FROM Soporte WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
$soporte_pendiente = pdf_scalar($pdo, "SELECT COUNT(*) FROM Soporte WHERE Estado = 'Pendiente' AND Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);

$volumen_reservas = pdf_money($pdo, "
    SELECT COALESCE(SUM(p.Precio), 0)
    FROM Reservas r
    JOIN Publicaciones p ON p.ID_publicacion = r.ID_publicacion
    WHERE r.Estado = 'Completada'
      AND r.FechaReserva BETWEEN ? AND ?
", [$reporte_desde_dt, $reporte_hasta_dt]);
$ticket_promedio = $reservas_confirmadas > 0 ? $volumen_reservas / $reservas_confirmadas : 0;
$comision_referencial = $volumen_reservas * 0.10;
$asientos_ofrecidos = pdf_scalar($pdo, "
    SELECT COALESCE(SUM(v.CantidadAsientos), 0)
    FROM Publicaciones p
    JOIN Vehiculos v ON v.ID_vehiculo = p.ID_vehiculo
    WHERE p.HoraSalida BETWEEN ? AND ?
", [$reporte_desde_dt, $reporte_hasta_dt]);
$ocupacion_pct = $asientos_ofrecidos > 0 ? round(($reservas_confirmadas / $asientos_ofrecidos) * 100, 1) : 0;

$busquedas_registradas = 0;
$busquedas_con_resultados = 0;
$busquedas_sin_resultados = 0;
$top_busquedas = [];
$busquedas_nota = 'Todavia no hay busquedas registradas. Se empezaran a medir cuando los usuarios usen filtros en Buscar viajes.';
if (pdf_table_exists($pdo, 'BusquedasViajes')) {
    $busquedas_registradas = pdf_scalar($pdo, "SELECT COUNT(*) FROM BusquedasViajes WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
    $busquedas_con_resultados = pdf_scalar($pdo, "SELECT COUNT(*) FROM BusquedasViajes WHERE Resultados > 0 AND Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
    $busquedas_sin_resultados = pdf_scalar($pdo, "SELECT COUNT(*) FROM BusquedasViajes WHERE Resultados = 0 AND Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
    $top_busquedas = pdf_rows($pdo, "
        SELECT
            COALESCE(NULLIF(CiudadOrigen, ''), 'Cualquier origen') AS origen,
            COALESCE(NULLIF(CiudadDestino, ''), 'Cualquier destino') AS destino,
            COUNT(*) AS total,
            ROUND(AVG(Resultados), 1) AS promedio_resultados
        FROM BusquedasViajes
        WHERE Fecha BETWEEN ? AND ?
        GROUP BY COALESCE(NULLIF(CiudadOrigen, ''), 'Cualquier origen'),
                 COALESCE(NULLIF(CiudadDestino, ''), 'Cualquier destino')
        ORDER BY total DESC
        LIMIT 5
    ", [$reporte_desde_dt, $reporte_hasta_dt]);
    $busquedas_nota = 'Las busquedas se registran cuando un usuario usa filtros de origen, destino u ordenamiento.';
}

$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01 00:00:00', strtotime("-$i months"));
    $end = date('Y-m-01 00:00:00', strtotime("-" . ($i - 1) . " months"));
    if ($i === 0) {
        $end = date('Y-m-01 00:00:00', strtotime('first day of next month'));
    }
    $monthly[] = [
        'label' => date('M', strtotime($start)),
        'reservas' => pdf_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE FechaReserva >= ? AND FechaReserva < ?", [$start, $end]),
        'viajes' => pdf_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE HoraSalida >= ? AND HoraSalida < ?", [$start, $end]),
        'volumen' => pdf_money($pdo, "
            SELECT COALESCE(SUM(p.Precio), 0)
            FROM Reservas r
            JOIN Publicaciones p ON p.ID_publicacion = r.ID_publicacion
            WHERE r.Estado = 'Completada' AND r.FechaReserva >= ? AND r.FechaReserva < ?
        ", [$start, $end]),
    ];
}

$top_rutas = pdf_rows($pdo, "
    SELECT p.CiudadOrigen, p.CiudadDestino,
           COUNT(r.ID_reserva) AS reservas,
           COUNT(DISTINCT p.ID_publicacion) AS viajes,
           COALESCE(SUM(CASE WHEN r.Estado = 'Completada' THEN p.Precio ELSE 0 END), 0) AS volumen
    FROM Publicaciones p
    LEFT JOIN Reservas r ON r.ID_publicacion = p.ID_publicacion AND r.FechaReserva BETWEEN ? AND ?
    WHERE p.HoraSalida BETWEEN ? AND ?
    GROUP BY p.CiudadOrigen, p.CiudadDestino
    ORDER BY reservas DESC, viajes DESC
    LIMIT 6
", [$reporte_desde_dt, $reporte_hasta_dt, $reporte_desde_dt, $reporte_hasta_dt]);

$top_conductores = pdf_rows($pdo, "
    SELECT CONCAT(u.Nombre, ' ', u.Apellido) AS conductor,
           COUNT(DISTINCT p.ID_publicacion) AS viajes,
           COUNT(DISTINCT r.ID_reserva) AS reservas,
           COALESCE(AVG(cal.Puntuacion), 0) AS rating
    FROM Conductores c
    JOIN Usuarios u ON u.ID_usuario = c.ID_usuario
    LEFT JOIN ConductorPublicacion cp ON cp.ID_conductor = c.ID_conductor
    LEFT JOIN Publicaciones p ON p.ID_publicacion = cp.ID_publicacion AND p.HoraSalida BETWEEN ? AND ?
    LEFT JOIN Reservas r ON r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada' AND r.FechaReserva BETWEEN ? AND ?
    LEFT JOIN Calificaciones cal ON cal.ID_conductor = c.ID_conductor
    GROUP BY c.ID_conductor, u.Nombre, u.Apellido
    ORDER BY reservas DESC, viajes DESC
    LIMIT 5
", [$reporte_desde_dt, $reporte_hasta_dt, $reporte_desde_dt, $reporte_hasta_dt]);

$user_chart = [
    ['label' => 'Activos', 'value' => $usuarios_activos, 'color' => '#118c8b'],
    ['label' => 'Baneados', 'value' => $usuarios_baneados, 'color' => '#d83a34'],
    ['label' => 'Eliminados', 'value' => $usuarios_eliminados, 'color' => '#263238'],
    ['label' => 'Pasajeros', 'value' => $pasajeros_total, 'color' => '#f7b733'],
];
$vehicle_chart = [
    ['label' => 'Aprobados', 'value' => $vehiculos_aprobados, 'color' => '#118c8b'],
    ['label' => 'Pendientes', 'value' => $vehiculos_pendientes, 'color' => '#f7b733'],
    ['label' => 'Suspendidos', 'value' => $vehiculos_suspendidos, 'color' => '#d83a34'],
    ['label' => 'Eliminados', 'value' => $vehiculos_eliminados, 'color' => '#263238'],
];
$trip_chart = [
    ['label' => 'Activos', 'value' => $viajes_activos, 'color' => '#118c8b'],
    ['label' => 'Finalizados', 'value' => $viajes_finalizados, 'color' => '#2364aa'],
    ['label' => 'Cancelados', 'value' => $viajes_cancelados, 'color' => '#d83a34'],
];
$max_monthly = max(1, max(array_map(static fn($row) => max((int)$row['reservas'], (int)$row['viajes']), $monthly)));
$monthly_total_activity = array_sum(array_map(static fn($row) => (int)$row['reservas'] + (int)$row['viajes'], $monthly));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte general MOVEON</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9f0fb; color: #07142b; font-family: Arial, Helvetica, sans-serif; }
        .toolbar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: space-between; align-items: center; padding: 14px 24px; background: #07142b; color: white; }
        .toolbar a, .toolbar button { border: 0; border-radius: 12px; padding: 10px 16px; font-weight: 800; cursor: pointer; text-decoration: none; }
        .toolbar a { background: #dbeafe; color: #1d4ed8; }
        .toolbar button { background: #0a9b72; color: white; }
        .report { width: 1040px; min-height: 760px; margin: 24px auto; background: #f8fafc; border: 1px solid #d7e1ef; box-shadow: 0 22px 60px rgba(15, 23, 42, .16); padding: 18px; overflow: visible; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #bcd2e8; padding-bottom: 14px; margin-bottom: 14px; }
        .brand { display: flex; gap: 14px; align-items: center; }
        .logo { min-width: 118px; height: 42px; padding: 0 14px; border-radius: 10px; background: #102f4a; display: grid; place-items: center; color: white; font-size: 15px; letter-spacing: 0; font-weight: 900; white-space: nowrap; }
        h1 { margin: 0; font-size: 24px; }
        h2 { margin: 0 0 12px; font-size: 15px; }
        .muted { color: #60718d; font-size: 12px; }
        .grid { display: grid; gap: 12px; }
        .grid-main { grid-template-columns: 145px 1fr 1fr 1fr; align-items: stretch; }
        .grid-2 { grid-template-columns: 1.2fr .8fr; }
        .grid-3 { grid-template-columns: repeat(3, 1fr); }
        .tile, .panel { background: white; border: 1px solid #dfe7f2; border-radius: 4px; padding: 13px; min-width: 0; break-inside: avoid; page-break-inside: avoid; overflow: hidden; }
        .tile { background: #0b6b78; color: white; min-height: 86px; display: flex; flex-direction: column; justify-content: center; }
        .tile strong { font-size: 24px; margin-top: 6px; }
        .tile small { opacity: .85; font-weight: 700; }
        .metric { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 14px; align-items: center; padding: 8px 0; border-bottom: 1px solid #edf2f7; font-size: 12px; }
        .metric span { min-width: 0; overflow-wrap: anywhere; }
        .metric strong { min-width: 42px; text-align: right; white-space: nowrap; }
        .metric:last-child { border-bottom: 0; }
        .bar-row { display: grid; grid-template-columns: 88px minmax(0, 1fr) 42px; gap: 8px; align-items: center; margin: 9px 0; font-size: 11px; }
        .bar-row strong { text-align: right; white-space: nowrap; }
        .bar-row > span:first-child { min-width: 0; overflow-wrap: anywhere; }
        .bar { height: 14px; background: #e7edf5; border-radius: 2px; overflow: hidden; }
        .bar span { display: block; height: 100%; }
        .stack { display: flex; height: 32px; overflow: hidden; border-radius: 3px; background: #e7edf5; margin-top: 10px; }
        .stack span { display: block; height: 100%; color: white; font-size: 10px; padding: 9px 5px; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; }
        th { text-align: left; color: #60718d; background: #eef3f9; padding: 8px; }
        td { padding: 8px; border-bottom: 1px solid #e5edf7; }
        .note { background: #fff7db; border: 1px solid #ffe9a3; padding: 10px; font-size: 11px; color: #664d03; border-radius: 4px; }
        .monthly-chart { min-height: 170px; display: grid; gap: 8px; align-content: start; padding-top: 2px; }
        .monthly-row { display: grid; grid-template-columns: 42px 1fr 44px 1fr 44px; gap: 7px; align-items: center; font-size: 10px; color: #60718d; }
        .monthly-label { font-weight: 800; color: #25364d; text-transform: uppercase; }
        .monthly-track { height: 12px; border-radius: 2px; background: #e7edf5; overflow: hidden; }
        .monthly-track span { display: block; height: 100%; min-width: 2px; }
        .monthly-value { text-align: right; font-weight: 800; color: #25364d; }
        .legend { display: flex; gap: 16px; font-size: 11px; color: #60718d; margin-top: 8px; }
        .legend i { display: inline-block; width: 10px; height: 10px; margin-right: 5px; border-radius: 2px; }
        .footer { margin-top: 12px; color: #60718d; font-size: 10px; display: flex; justify-content: space-between; }
        section { break-inside: avoid; page-break-inside: avoid; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .report { margin: 0; box-shadow: none; border: 0; width: 100%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="dashboard.php">Volver al dashboard</a>
        <button type="button" onclick="generarPDF()">Descargar PDF</button>
    </div>

    <main id="reporte-pdf" class="report">
        <header class="head">
            <div class="brand">
                <div class="logo">MOVEON</div>
                <div>
                    <h1>Reporte tecnico general del sistema</h1>
                <div class="muted">Estadistica completa de usuarios, viajes, conductores, reservas, reportes y rendimiento operativo.</div>
                </div>
            </div>
            <div class="muted" style="text-align:right;">
                Generado: <?= htmlspecialchars($generated_at) ?><br>
                Periodo: <?= htmlspecialchars($reporte_desde_legible) ?> - <?= htmlspecialchars($reporte_hasta_legible) ?>
            </div>
        </header>

        <section class="grid grid-main">
            <div class="grid">
                <div class="tile"><small>Usuarios activos</small><strong><?= number_format($usuarios_activos) ?></strong></div>
                <div class="tile"><small>Reservas del periodo</small><strong><?= number_format($reservas_total) ?></strong></div>
                <div class="tile"><small>Viajes del periodo</small><strong><?= number_format($viajes_total) ?></strong></div>
                <div class="tile"><small>Volumen potencial</small><strong><?= money_fmt($volumen_reservas) ?></strong></div>
            </div>

            <div class="panel">
                <h2>Usuarios y pasajeros</h2>
                <?php $max_user = max(1, max(array_column($user_chart, 'value'))); ?>
                <?php foreach ($user_chart as $item): ?>
                    <div class="bar-row">
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <div class="bar"><span style="width:<?= min(100, ((int)$item['value'] / $max_user) * 100) ?>%; background:<?= $item['color'] ?>;"></span></div>
                        <strong><?= number_format((int)$item['value']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="metric"><span>Pasajeros con reservas</span><strong><?= number_format($pasajeros_con_reservas) ?></strong></div>
            </div>

            <div class="panel">
                <h2>Conductores y vehiculos</h2>
                <div class="metric"><span>Conductores totales</span><strong><?= number_format($conductores_total) ?></strong></div>
                <div class="metric"><span>Aprobados</span><strong><?= number_format($conductores_aprobados) ?></strong></div>
                <div class="metric"><span>Pendientes</span><strong><?= number_format($conductores_pendientes) ?></strong></div>
                <div class="metric"><span>Conductores baneados</span><strong><?= number_format($conductores_baneados) ?></strong></div>
                <div class="metric"><span>Vehiculos registrados</span><strong><?= number_format($vehiculos_total) ?></strong></div>
                <?php $veh_total_stack = max(1, $vehiculos_total); ?>
                <div class="stack">
                    <?php foreach ($vehicle_chart as $item): ?>
                        <?php if ((int)$item['value'] > 0): ?>
                            <span style="width:<?= ((int)$item['value'] / $veh_total_stack) * 100 ?>%; background:<?= $item['color'] ?>;"><?= htmlspecialchars($item['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <h2>Rendimiento del negocio</h2>
                <div class="metric"><span>Ticket promedio</span><strong><?= money_fmt($ticket_promedio) ?></strong></div>
                <div class="metric"><span>Comision referencial 10%</span><strong><?= money_fmt($comision_referencial) ?></strong></div>
                <div class="metric"><span>Ocupacion estimada</span><strong><?= number_format($ocupacion_pct, 1, ',', '.') ?>%</strong></div>
                <div class="metric"><span>Reservas del periodo</span><strong><?= number_format($reservas_mes) ?></strong></div>
                <div class="metric"><span>Viajes del periodo</span><strong><?= number_format($viajes_mes) ?></strong></div>
                <div class="note">Como los pagos estan desactivados, la rentabilidad se muestra como volumen potencial y comision referencial, no como ingreso cobrado.</div>
            </div>
        </section>

        <section class="grid grid-2" style="margin-top:12px;">
            <div class="panel">
                <h2>Evolucion mensual: reservas vs viajes</h2>
                <div class="monthly-chart" role="img" aria-label="Evolucion mensual">
                    <?php foreach ($monthly as $row): ?>
                        <?php
                            $reservas_val = (int)$row['reservas'];
                            $viajes_val = (int)$row['viajes'];
                            $w_res = $reservas_val > 0 ? max(3, min(100, ($reservas_val / $max_monthly) * 100)) : 0;
                            $w_via = $viajes_val > 0 ? max(3, min(100, ($viajes_val / $max_monthly) * 100)) : 0;
                        ?>
                        <div class="monthly-row">
                            <span class="monthly-label"><?= htmlspecialchars($row['label']) ?></span>
                            <div class="monthly-track"><span style="width:<?= $w_res ?>%; background:#118c8b;"></span></div>
                            <span class="monthly-value"><?= number_format($reservas_val) ?></span>
                            <div class="monthly-track"><span style="width:<?= $w_via ?>%; background:#d83a34;"></span></div>
                            <span class="monthly-value"><?= number_format($viajes_val) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($monthly_total_activity === 0): ?>
                        <div class="note">No hay reservas ni viajes registrados en los ultimos meses medidos.</div>
                    <?php endif; ?>
                </div>
                <div class="legend"><span><i style="background:#118c8b;"></i>Reservas</span><span><i style="background:#d83a34;"></i>Viajes</span></div>
            </div>

            <div class="panel">
                <h2>Estados de viajes</h2>
                <?php $max_trip = max(1, max(array_column($trip_chart, 'value'))); ?>
                <?php foreach ($trip_chart as $item): ?>
                    <div class="bar-row">
                        <span><?= htmlspecialchars($item['label']) ?></span>
                        <div class="bar"><span style="width:<?= min(100, ((int)$item['value'] / $max_trip) * 100) ?>%; background:<?= $item['color'] ?>;"></span></div>
                        <strong><?= number_format((int)$item['value']) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="metric"><span>Reservas canceladas/rechazadas</span><strong><?= number_format($reservas_canceladas) ?></strong></div>
                <div class="metric"><span>Asientos ofrecidos</span><strong><?= number_format($asientos_ofrecidos) ?></strong></div>
            </div>
        </section>

        <section class="grid grid-3" style="margin-top:12px;">
            <div class="panel">
                <h2>Reportes y soporte</h2>
                <div class="metric"><span>Reportes a conductores</span><strong><?= number_format($reportes_conductores) ?></strong></div>
                <div class="metric"><span>Reportes a pasajeros</span><strong><?= number_format($reportes_pasajeros) ?></strong></div>
                <div class="metric"><span>Reportes pasajeros pendientes</span><strong><?= number_format($reportes_pasajeros_pendientes) ?></strong></div>
                <div class="metric"><span>Tickets soporte</span><strong><?= number_format($soporte_total) ?></strong></div>
                <div class="metric"><span>Soporte pendiente</span><strong><?= number_format($soporte_pendiente) ?></strong></div>
            </div>

            <div class="panel">
                <h2>Busquedas de viajes</h2>
                <div class="metric"><span>Busquedas registradas</span><strong><?= number_format($busquedas_registradas) ?></strong></div>
                <div class="metric"><span>Con resultados</span><strong><?= number_format($busquedas_con_resultados) ?></strong></div>
                <div class="metric"><span>Sin resultados</span><strong><?= number_format($busquedas_sin_resultados) ?></strong></div>
                <div class="note"><?= htmlspecialchars($busquedas_nota) ?></div>
            </div>

            <div class="panel">
                <h2>Semaforo operativo</h2>
                <div class="metric"><span>Conductores pendientes</span><strong><?= number_format($conductores_pendientes) ?></strong></div>
                <div class="metric"><span>Vehiculos pendientes</span><strong><?= number_format($vehiculos_pendientes) ?></strong></div>
                <div class="metric"><span>Usuarios bloqueados</span><strong><?= number_format($usuarios_baneados + $usuarios_eliminados) ?></strong></div>
                <div class="metric"><span>Vehiculos fuera de circulacion</span><strong><?= number_format($vehiculos_suspendidos + $vehiculos_eliminados) ?></strong></div>
            </div>
        </section>

        <section class="grid grid-2" style="margin-top:12px;">
            <div class="panel">
                <h2>Rutas con mayor movimiento</h2>
                <table>
                    <thead><tr><th>Ruta</th><th>Reservas</th><th>Viajes</th><th>Volumen</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_rutas as $ruta): ?>
                        <tr>
                            <td><?= htmlspecialchars($ruta['CiudadOrigen'] . ' -> ' . $ruta['CiudadDestino']) ?></td>
                            <td><?= number_format((int)$ruta['reservas']) ?></td>
                            <td><?= number_format((int)$ruta['viajes']) ?></td>
                            <td><?= money_fmt((float)$ruta['volumen']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="panel">
                <h2>Conductores con mayor actividad</h2>
                <table>
                    <thead><tr><th>Conductor</th><th>Viajes</th><th>Reservas</th><th>Rating</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_conductores as $cond): ?>
                        <tr>
                            <td><?= htmlspecialchars($cond['conductor']) ?></td>
                            <td><?= number_format((int)$cond['viajes']) ?></td>
                            <td><?= number_format((int)$cond['reservas']) ?></td>
                            <td><?= number_format((float)$cond['rating'], 1, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if (!empty($top_busquedas)): ?>
            <section class="panel" style="margin-top:12px;">
                <h2>Rutas mas buscadas por los usuarios</h2>
                <table>
                    <thead><tr><th>Busqueda</th><th>Cantidad</th><th>Promedio de resultados</th><th>Lectura</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_busquedas as $busqueda): ?>
                        <tr>
                            <td><?= htmlspecialchars($busqueda['origen'] . ' -> ' . $busqueda['destino']) ?></td>
                            <td><?= number_format((int)$busqueda['total']) ?></td>
                            <td><?= number_format((float)$busqueda['promedio_resultados'], 1, ',', '.') ?></td>
                            <td><?= ((float)$busqueda['promedio_resultados'] <= 1) ? 'Demanda con poca oferta' : 'Demanda con oferta disponible' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

        <footer class="footer">
            <span>MOVEON - Reporte administrativo integral</span>
            <span>Fuente: base de datos actual del sistema</span>
        </footer>
    </main>

    <script>
        function generarPDF() {
            const element = document.getElementById('reporte-pdf');
            const opt = {
                margin: 6,
                filename: 'reporte_general_moveon_<?= htmlspecialchars($reporte_desde) ?>_<?= htmlspecialchars($reporte_hasta) ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['.panel', '.tile', 'table', 'tr'] },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(element).save();
        }

        window.addEventListener('load', function () {
            if (window.html2pdf) {
                requestAnimationFrame(function () {
                    setTimeout(generarPDF, 1400);
                });
            }
        });
    </script>
</body>
</html>
