<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/trips.php';

sync_finished_trips($pdo);

$stmt_users = $pdo->query("
    SELECT COUNT(*)
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
    AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta < NOW())
    AND u.estado = 'activo'
");
$usuarios_activos = (int)$stmt_users->fetchColumn();

$total_viajes = (int)$pdo->query("SELECT COUNT(*) FROM Publicaciones")->fetchColumn();
$conductores_pendientes = (int)$pdo->query("SELECT COUNT(*) FROM Conductores WHERE Estado = 'Esperando'")->fetchColumn();

$reservas_confirmadas = (int)$pdo->query("SELECT COUNT(*) FROM Reservas WHERE Estado = 'Completada'")->fetchColumn();

$inicio_mes = date('Y-m-01 00:00:00');
$inicio_mes_siguiente = date('Y-m-01 00:00:00', strtotime('first day of next month'));
$inicio_mes_anterior = date('Y-m-01 00:00:00', strtotime('first day of previous month'));

$stmt_mes = $pdo->prepare("
    SELECT COUNT(*)
    FROM Reservas
    WHERE Estado = 'Completada'
      AND FechaReserva >= ? AND FechaReserva < ?
");
$stmt_mes->execute([$inicio_mes, $inicio_mes_siguiente]);
$reservas_mes = (int)$stmt_mes->fetchColumn();

$stmt_mes->execute([$inicio_mes_anterior, $inicio_mes]);
$reservas_mes_anterior = (int)$stmt_mes->fetchColumn();

$trend_up = $reservas_mes >= $reservas_mes_anterior;
$max_chart = max($reservas_mes, $reservas_mes_anterior, 1);
$bar_actual = max(8, (int)round(($reservas_mes / $max_chart) * 130));
$bar_anterior = max(8, (int)round(($reservas_mes_anterior / $max_chart) * 130));
$diferencia = $reservas_mes - $reservas_mes_anterior;
$porcentaje = $reservas_mes_anterior > 0 ? ($diferencia / $reservas_mes_anterior) * 100 : ($reservas_mes > 0 ? 100 : 0);
$chart_color = $trend_up ? '#009b6b' : '#ef4444';

$stmt_recientes = $pdo->query("
    SELECT ID_publicacion, CiudadOrigen, CiudadDestino, HoraSalida, Precio, Estado
    FROM Publicaciones
    ORDER BY HoraSalida DESC
    LIMIT 5
");
$viajes_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);

$stmt_pendientes = $pdo->query("
    SELECT c.ID_conductor, c.LicenciaConducir, u.Nombre, u.Apellido, u.Correo
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.Estado = 'Esperando'
    ORDER BY c.FechaRegistro DESC
    LIMIT 4
");
$pendientes = $stmt_pendientes->fetchAll(PDO::FETCH_ASSOC);

$db_size_mb = 0;
try {
    $stmt_db = $pdo->query("
        SELECT COALESCE(SUM(data_length + index_length),0) / 1024 / 1024
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
    ");
    $db_size_mb = (float)$stmt_db->fetchColumn();
} catch (Exception $e) {
    $db_size_mb = 0;
}

$reporte_tipos = [
    'actividad' => 'Actividad general',
    'tecnico' => 'Reporte tecnico general',
    'usuarios' => 'Usuarios',
    'viajes' => 'Viajes',
    'rutas' => 'Rutas mas utilizadas',
    'reclamos' => 'Reportes y reclamos',
];
$reporte_tipo = $_GET['reporte_tipo'] ?? 'actividad';
if (!isset($reporte_tipos[$reporte_tipo])) {
    $reporte_tipo = 'actividad';
}

$reporte_desde = $_GET['reporte_desde'] ?? date('Y-m-01');
$reporte_hasta = $_GET['reporte_hasta'] ?? date('Y-m-d');
$fecha_actual_reporte = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporte_desde)) {
    $reporte_desde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reporte_hasta)) {
    $reporte_hasta = date('Y-m-d');
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

function dashboard_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function dashboard_rows(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$reporte_metricas = [];
$reporte_columnas = [];
$reporte_filas = [];
$reporte_grafico = [];
$reporte_nota = '';
$reporte_insights = [];

switch ($reporte_tipo) {
    case 'tecnico':
        $deleted_user_sql_reporte = "(u.Correo LIKE 'deleted\\_%@deleted.moveon.local' OR u.DNI LIKE 'deleted\\_%')";
        $usuarios_totales = dashboard_scalar($pdo, "
            SELECT COUNT(*)
            FROM Usuarios u
            LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
            WHERE a.ID_administrador IS NULL
        ");
        $usuarios_suspendidos = dashboard_scalar($pdo, "
            SELECT COUNT(*)
            FROM Usuarios u
            LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
            WHERE a.ID_administrador IS NULL
              AND NOT $deleted_user_sql_reporte
              AND u.BaneadoHasta > NOW()
        ");
        $usuarios_eliminados = dashboard_scalar($pdo, "
            SELECT COUNT(*)
            FROM Usuarios u
            LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
            WHERE a.ID_administrador IS NULL
              AND $deleted_user_sql_reporte
        ");
        $vehiculos_totales = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos");
        $vehiculos_pendientes = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Pendiente'");
        $vehiculos_aprobados = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Aceptado'");
        $vehiculos_suspendidos = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Suspendido'");
        $vehiculos_eliminados = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Vehiculos WHERE Estado IN ('Rechazado', 'Eliminado')");
        $conductores_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE FechaRegistro BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $conductores_pendientes_total = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado = 'Esperando'");
        $conductores_suspendidos_total = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado = 'Aceptada' AND BaneadoHasta > NOW()");
        $viajes_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $viajes_finalizados = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Finalizada' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $viajes_cancelados = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Cancelada' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reservas_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reservas_confirmadas_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE Estado = 'Completada' AND FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $confirmaciones_pasajeros = dashboard_scalar($pdo, "SELECT COUNT(*) FROM ConfirmacionesViaje WHERE FechaConfirmacion BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $confirmaciones_conductores = dashboard_scalar($pdo, "SELECT COUNT(*) FROM ConfirmacionesConductorViaje WHERE FechaConfirmacion BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reportes_conductor = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reportes WHERE Fecha BETWEEN ? AND ?", [$reporte_desde, $reporte_hasta]);
        $reportes_pasajero = dashboard_scalar($pdo, "SELECT COUNT(*) FROM ReportesPasajeros WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $soporte_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Soporte WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $notificaciones_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Notificaciones WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $eventos_operativos = $conductores_periodo + $viajes_periodo + $reservas_periodo + $confirmaciones_pasajeros + $confirmaciones_conductores + $reportes_conductor + $reportes_pasajero + $soporte_periodo;
        $total_reportes_periodo = $reportes_conductor + $reportes_pasajero;
        $tasa_conflicto = $reservas_periodo > 0 ? round(($total_reportes_periodo / $reservas_periodo) * 100, 1) : 0;

        $reporte_metricas = [
            ['label' => 'Eventos del periodo', 'value' => $eventos_operativos],
            ['label' => 'Usuarios bloqueados', 'value' => $usuarios_suspendidos + $usuarios_eliminados],
            ['label' => 'Vehiculos registrados', 'value' => $vehiculos_totales],
            ['label' => 'Tasa de conflicto', 'value' => $tasa_conflicto . '%'],
        ];
        $reporte_columnas = ['Area', 'Indicador', 'Valor', 'Lectura tecnica'];
        $reporte_filas = [
            ['Usuarios', 'Usuarios totales no administradores', $usuarios_totales, 'Base actual de cuentas operativas.'],
            ['Usuarios', 'Usuarios suspendidos actualmente', $usuarios_suspendidos, 'Se detectan por BaneadoHasta vigente.'],
            ['Usuarios', 'Usuarios eliminados o anonimizados', $usuarios_eliminados, 'Se detectan por correo/DNI anonimizados.'],
            ['Vehiculos', 'Vehiculos registrados', $vehiculos_totales, 'Total actual; la tabla no guarda fecha de alta.'],
            ['Vehiculos', 'Pendientes de revision', $vehiculos_pendientes, 'Requieren accion administrativa.'],
            ['Vehiculos', 'Aprobados', $vehiculos_aprobados, 'Disponibles para publicaciones si el conductor esta habilitado.'],
            ['Vehiculos', 'Suspendidos', $vehiculos_suspendidos, 'No deberian operar hasta resolver el estado.'],
            ['Vehiculos', 'Rechazados/eliminados', $vehiculos_eliminados, 'Retirados por decision administrativa.'],
            ['Conductores', 'Solicitudes creadas en el periodo', $conductores_periodo, 'Altas de conductores segun FechaRegistro.'],
            ['Conductores', 'Pendientes actuales', $conductores_pendientes_total, 'Cola administrativa actual.'],
            ['Conductores', 'Suspendidos actuales', $conductores_suspendidos_total, 'Conductores aceptados con suspension vigente.'],
            ['Viajes', 'Viajes con salida en el periodo', $viajes_periodo, 'Actividad publicada para el rango.'],
            ['Viajes', 'Viajes finalizados', $viajes_finalizados, 'Viajes cerrados por fecha/estado.'],
            ['Viajes', 'Viajes cancelados', $viajes_cancelados, 'Posibles bajas por admin, conductor o reglas.'],
            ['Reservas', 'Reservas creadas', $reservas_periodo, 'Demanda generada por pasajeros.'],
            ['Reservas', 'Reservas confirmadas', $reservas_confirmadas_periodo, 'Reservas activas/completadas en el flujo.'],
            ['Confirmaciones', 'Confirmaciones de pasajeros', $confirmaciones_pasajeros, 'Llegadas confirmadas por pasajeros.'],
            ['Confirmaciones', 'Confirmaciones de conductores', $confirmaciones_conductores, 'Cierre operativo declarado por conductores.'],
            ['Conflictos', 'Reportes a conductores', $reportes_conductor, 'Reclamos cargados por pasajeros.'],
            ['Conflictos', 'Reportes a pasajeros', $reportes_pasajero, 'Reclamos cargados por conductores.'],
            ['Soporte', 'Tickets generados', $soporte_periodo, 'Consultas o incidencias enviadas a soporte.'],
            ['Sistema', 'Notificaciones emitidas', $notificaciones_periodo, 'Mensajes automaticos o administrativos enviados.'],
        ];
        $reporte_grafico = [
            ['label' => 'Usuarios bloq.', 'value' => $usuarios_suspendidos + $usuarios_eliminados],
            ['label' => 'Vehiculos', 'value' => $vehiculos_totales],
            ['label' => 'Viajes', 'value' => $viajes_periodo],
            ['label' => 'Reservas', 'value' => $reservas_periodo],
            ['label' => 'Reportes', 'value' => $total_reportes_periodo],
            ['label' => 'Soporte', 'value' => $soporte_periodo],
        ];
        $reporte_insights = [
            'Este reporte funciona como tablero tecnico general: combina estados actuales con eventos fechados del periodo seleccionado.',
            'No existe una tabla de auditoria de acciones administrativas; por eso el log se construye a partir de estados y movimientos reales del sistema.',
            'Usuarios baneados historicamente no pueden contarse con fecha exacta porque Usuarios solo guarda BaneadoHasta vigente, no historial de sanciones.',
            'Vehiculos registrados se informa como total actual porque Vehiculos no tiene FechaRegistro.',
        ];
        $reporte_nota = 'Reporte tecnico construido solo con datos existentes. Para un log administrativo exacto haria falta una tabla de auditoria de acciones del admin.';
        break;

    case 'usuarios':
        $usuarios_suspendidos = dashboard_scalar($pdo, "
            SELECT COUNT(*) FROM Usuarios
            WHERE BaneadoHasta > NOW() OR estado IN ('suspendido', 'baneado')
        ");
        $conductores_activos = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE Estado = 'Aceptada'");
        $pasajeros_activos = dashboard_scalar($pdo, "
            SELECT COUNT(DISTINCT pas.ID_usuario)
            FROM Pasajeros pas
            JOIN PasajerosReservas pr ON pr.ID_pasajero = pas.ID_pasajero
            JOIN Reservas r ON r.ID_reserva = pr.ID_reserva
            WHERE r.Estado = 'Completada'
        ");
        $reporte_metricas = [
            ['label' => 'Usuarios activos', 'value' => $usuarios_activos],
            ['label' => 'Usuarios suspendidos', 'value' => $usuarios_suspendidos],
            ['label' => 'Conductores aceptados', 'value' => $conductores_activos],
            ['label' => 'Pasajeros con reservas', 'value' => $pasajeros_activos],
        ];
        $reporte_columnas = ['Segmento', 'Cantidad'];
        $reporte_filas = [
            ['Usuarios activos', $usuarios_activos],
            ['Usuarios suspendidos', $usuarios_suspendidos],
            ['Conductores aceptados', $conductores_activos],
            ['Pasajeros con reservas completadas', $pasajeros_activos],
        ];
        $reporte_grafico = [
            ['label' => 'Activos', 'value' => $usuarios_activos],
            ['label' => 'Suspendidos', 'value' => $usuarios_suspendidos],
            ['label' => 'Conductores', 'value' => $conductores_activos],
            ['label' => 'Pasajeros', 'value' => $pasajeros_activos],
        ];
        $reporte_nota = 'La tabla Usuarios no guarda fecha de alta; por eso este reporte muestra estado actual y no altas por periodo.';
        break;

    case 'viajes':
        $viajes_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $viajes_finalizados = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Finalizada' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $viajes_cancelados = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE Estado = 'Cancelada' AND HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reservas_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $tasa_finalizacion = $viajes_periodo > 0 ? round(($viajes_finalizados / $viajes_periodo) * 100, 1) : 0;
        $reporte_metricas = [
            ['label' => 'Viajes del periodo', 'value' => $viajes_periodo],
            ['label' => 'Finalizados', 'value' => $viajes_finalizados],
            ['label' => 'Cancelados', 'value' => $viajes_cancelados],
            ['label' => 'Tasa finalizacion', 'value' => $tasa_finalizacion . '%'],
        ];
        $reporte_columnas = ['Estado', 'Cantidad'];
        $reporte_filas = dashboard_rows($pdo, "
            SELECT Estado, COUNT(*) AS total
            FROM Publicaciones
            WHERE HoraSalida BETWEEN ? AND ?
            GROUP BY Estado
            ORDER BY total DESC
        ", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reporte_grafico = [
            ['label' => 'Viajes', 'value' => $viajes_periodo],
            ['label' => 'Finalizados', 'value' => $viajes_finalizados],
            ['label' => 'Cancelados', 'value' => $viajes_cancelados],
            ['label' => 'Reservas', 'value' => $reservas_periodo],
        ];
        break;

    case 'rutas':
        $reporte_filas = dashboard_rows($pdo, "
            SELECT CiudadOrigen, CiudadDestino, COUNT(*) AS viajes, COALESCE(SUM(Precio), 0) AS valor_publicado
            FROM Publicaciones
            WHERE HoraSalida BETWEEN ? AND ?
            GROUP BY CiudadOrigen, CiudadDestino
            ORDER BY viajes DESC, valor_publicado DESC
            LIMIT 8
        ", [$reporte_desde_dt, $reporte_hasta_dt]);
        $total_rutas = count($reporte_filas);
        $viajes_rutas = array_sum(array_map(static fn($r) => (int)$r['viajes'], $reporte_filas));
        $ruta_lider = $reporte_filas[0] ?? null;
        $reporte_metricas = [
            ['label' => 'Rutas distintas', 'value' => $total_rutas],
            ['label' => 'Viajes en top rutas', 'value' => $viajes_rutas],
            ['label' => 'Ruta principal', 'value' => $ruta_lider ? $ruta_lider['CiudadOrigen'] . ' -> ' . $ruta_lider['CiudadDestino'] : 'Sin datos'],
            ['label' => 'Viajes ruta principal', 'value' => $ruta_lider ? (int)$ruta_lider['viajes'] : 0],
        ];
        $reporte_columnas = ['Ruta', 'Viajes', 'Valor publicado'];
        $reporte_grafico = array_map(static fn($r) => [
            'label' => $r['CiudadOrigen'] . ' -> ' . $r['CiudadDestino'],
            'value' => (int)$r['viajes'],
        ], $reporte_filas);
        break;

    case 'reclamos':
        $reportes_conductor = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reportes WHERE Fecha BETWEEN ? AND ?", [$reporte_desde, $reporte_hasta]);
        $reportes_pasajero = dashboard_scalar($pdo, "SELECT COUNT(*) FROM ReportesPasajeros WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $soporte_pendiente = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Soporte WHERE Estado = 'Pendiente' AND Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $soporte_resuelto = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Soporte WHERE Estado = 'Resuelto' AND Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reporte_metricas = [
            ['label' => 'Reportes a conductores', 'value' => $reportes_conductor],
            ['label' => 'Reportes a pasajeros', 'value' => $reportes_pasajero],
            ['label' => 'Soporte pendiente', 'value' => $soporte_pendiente],
            ['label' => 'Soporte resuelto', 'value' => $soporte_resuelto],
        ];
        $reporte_columnas = ['Tipo', 'Cantidad'];
        $reporte_filas = [
            ['Reportes a conductores', $reportes_conductor],
            ['Reportes a pasajeros', $reportes_pasajero],
            ['Tickets soporte pendientes', $soporte_pendiente],
            ['Tickets soporte resueltos', $soporte_resuelto],
        ];
        $reporte_grafico = [
            ['label' => 'Conductores', 'value' => $reportes_conductor],
            ['label' => 'Pasajeros', 'value' => $reportes_pasajero],
            ['label' => 'Soporte pendiente', 'value' => $soporte_pendiente],
            ['label' => 'Soporte resuelto', 'value' => $soporte_resuelto],
        ];
        break;

    case 'actividad':
    default:
        $viajes_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Publicaciones WHERE HoraSalida BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reservas_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reservas WHERE FechaReserva BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $conductores_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Conductores WHERE FechaRegistro BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reportes_periodo = dashboard_scalar($pdo, "SELECT COUNT(*) FROM Reportes WHERE Fecha BETWEEN ? AND ?", [$reporte_desde, $reporte_hasta])
            + dashboard_scalar($pdo, "SELECT COUNT(*) FROM ReportesPasajeros WHERE Fecha BETWEEN ? AND ?", [$reporte_desde_dt, $reporte_hasta_dt]);
        $reporte_metricas = [
            ['label' => 'Viajes programados', 'value' => $viajes_periodo],
            ['label' => 'Reservas creadas', 'value' => $reservas_periodo],
            ['label' => 'Solicitudes conductor', 'value' => $conductores_periodo],
            ['label' => 'Reportes recibidos', 'value' => $reportes_periodo],
        ];
        $reporte_columnas = ['Indicador', 'Cantidad'];
        $reporte_filas = [
            ['Viajes con salida en el periodo', $viajes_periodo],
            ['Reservas creadas en el periodo', $reservas_periodo],
            ['Solicitudes de conductor registradas', $conductores_periodo],
            ['Reportes/reclamos recibidos', $reportes_periodo],
        ];
        $reporte_grafico = [
            ['label' => 'Viajes', 'value' => $viajes_periodo],
            ['label' => 'Reservas', 'value' => $reservas_periodo],
            ['label' => 'Conductores', 'value' => $conductores_periodo],
            ['label' => 'Reportes', 'value' => $reportes_periodo],
        ];
        break;
}

$reporte_valores_grafico = array_map(static fn($item) => (int)$item['value'], $reporte_grafico);
$reporte_max = max(1, empty($reporte_valores_grafico) ? 0 : max($reporte_valores_grafico));

require_once __DIR__ . '/../header.php';
include __DIR__ . '/_nav.php';
?>

<div class="admin-grid">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'import_success'): ?>
        <div class="card" style="background:#f0fdf4; color:#047857;">Base de datos restaurada correctamente a partir del backup.</div>
    <?php elseif (isset($_GET['error'])): ?>
        <div class="card" style="background:#fff1f2; color:#be123c;">
            Error en la importación:
            <?php
                if ($_GET['error'] === 'upload_failed') echo "No se pudo subir el archivo.";
                elseif ($_GET['error'] === 'invalid_extension') echo "El archivo debe tener extensión .SQL.";
                elseif ($_GET['error'] === 'invalid_signature') echo "Firma inválida. Este archivo SQL no fue generado por el sistema.";
                elseif ($_GET['error'] === 'import_exception') echo "La consulta SQL contenía errores o era demasiado grande.";
                elseif ($_GET['error'] === 'recovery_package_unavailable') echo "No hay soporte ZIP/TAR disponible para generar el paquete de recuperacion.";
                elseif ($_GET['error'] === 'recovery_package_exception') echo "No se pudo generar el paquete de recuperacion. Revisa permisos temporales o extensiones PHP.";
                else echo "Error desconocido.";
            ?>
        </div>
    <?php endif; ?>

    <section class="admin-kpis">
        <article class="admin-kpi">
            <span class="admin-kpi-icon">♧</span>
            <p class="admin-kpi-value"><?= number_format($usuarios_activos) ?></p>
            <p class="admin-kpi-label">Usuarios activos</p>
            <div class="admin-kpi-note">Cuentas habilitadas</div>
        </article>

        <article class="admin-kpi">
            <span class="admin-kpi-icon" style="background:#f4efff; color:#7c3aed;">▰</span>
            <p class="admin-kpi-value"><?= number_format($total_viajes) ?></p>
            <p class="admin-kpi-label">Viajes registrados</p>
            <div class="admin-kpi-note">Histórico total</div>
        </article>

        <article class="admin-kpi">
            <span class="admin-kpi-icon" style="background:#fff7ed; color:#f59e0b;">⚠</span>
            <p class="admin-kpi-value"><?= number_format($conductores_pendientes) ?></p>
            <p class="admin-kpi-label">Conductores pendientes</p>
            <div class="admin-kpi-note">Requieren revisión</div>
        </article>

        <article class="admin-kpi">
            <span class="admin-kpi-icon" style="background:#ecfdf5; color:var(--success);">↗</span>
            <p class="admin-kpi-value"><?= number_format($reservas_confirmadas) ?></p>
            <p class="admin-kpi-label">Reservas confirmadas</p>
            <div class="admin-kpi-note">Historico total</div>
        </article>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <div>
                <h2>Reportes gerenciales</h2>
                <p class="text-muted" style="margin:6px 0 0;">Analisis operativo segun los datos disponibles en la base.</p>
            </div>
        </div>

        <style>
            .report-download-card {
                display: grid;
                grid-template-columns: 1fr minmax(320px, 420px);
                gap: 22px;
                align-items: center;
                margin-top: 18px;
                padding: 24px;
                border: 1px solid var(--border-color);
                border-radius: 18px;
                background: #ffffff;
                box-shadow: var(--shadow);
            }

            .report-download-btn {
                width: 100%;
                min-height: 92px;
                border: 0;
                border-radius: 18px;
                background: linear-gradient(135deg, var(--primary) 0%, #0aa373 100%);
                box-shadow: 0 16px 32px rgba(37, 99, 235, .22);
                color: #ffffff;
                cursor: pointer;
                font-weight: 900;
                font-size: 22px;
                letter-spacing: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 16px;
                transition: transform .16s ease, box-shadow .16s ease;
            }

            .report-download-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 20px 38px rgba(37, 99, 235, .28);
            }

            .report-icon {
                position: relative;
                width: 72px;
                height: 72px;
                border-radius: 18px;
                background: rgba(255,255,255,.18);
                border: 1px solid rgba(255,255,255,.28);
                box-shadow: inset 0 1px 0 rgba(255,255,255,.25);
            }

            .report-icon span {
                position: absolute;
                bottom: 16px;
                width: 9px;
                border-radius: 6px 6px 0 0;
                background: #ffffff;
            }

            .report-icon .b1 { left: 18px; height: 20px; }
            .report-icon .b2 { left: 32px; height: 34px; }
            .report-icon .b3 { left: 46px; height: 27px; }
            .report-icon::after {
                content: "";
                position: absolute;
                left: 17px;
                top: 30px;
                width: 42px;
                height: 4px;
                border-radius: 999px;
                background: #ffffff;
                transform: rotate(-32deg);
                transform-origin: left center;
            }

            .report-icon::before {
                content: "";
                position: absolute;
                right: 12px;
                top: 17px;
                width: 0;
                height: 0;
                border-left: 9px solid transparent;
                border-right: 9px solid transparent;
                border-bottom: 16px solid #ffffff;
                transform: rotate(45deg);
            }

            .report-download-text {
                display: grid;
                gap: 3px;
                text-align: left;
            }

            .report-download-text small {
                color: rgba(255,255,255,.82);
                font-size: 13px;
                font-weight: 800;
            }

            .report-date-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                margin-bottom: 12px;
            }

            .report-date-grid label {
                display: grid;
                gap: 6px;
                font-weight: 800;
                color: var(--text-muted);
                font-size: 13px;
            }

            .report-date-grid input {
                width: 100%;
            }

            @media (max-width: 760px) {
                .report-download-card {
                    grid-template-columns: 1fr;
                }

                .report-download-btn {
                    width: 100%;
                }
            }
        </style>

        <div class="report-download-card">
            <div>
                <h3 style="margin:0 0 8px; font-size:24px;">Reporte integral descargable</h3>
                <p class="text-muted" style="margin:0; max-width:760px;">Genera un PDF con estadisticas completas de usuarios, viajes, conductores, vehiculos, reportes, soporte, busquedas y rentabilidad potencial sin salir del dashboard.</p>
            </div>
            <form id="form-reporte-admin" onsubmit="descargarReporteAdmin(); return false;">
                <div class="report-date-grid">
                    <label>
                        Fecha desde
                        <input type="date" id="reporte-pdf-desde" value="<?= htmlspecialchars($reporte_desde) ?>" max="<?= htmlspecialchars($fecha_actual_reporte) ?>">
                    </label>
                    <label>
                        Fecha hasta
                        <input type="date" id="reporte-pdf-hasta" value="<?= htmlspecialchars($reporte_hasta) ?>" max="<?= htmlspecialchars($fecha_actual_reporte) ?>">
                    </label>
                </div>
                <button type="submit" class="report-download-btn">
                    <span class="report-icon" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span></span>
                    <span class="report-download-text">
                        Crear reporte
                        <small>PDF integral</small>
                    </span>
                </button>
            </form>
        </div>
    </section>

    <section class="admin-dashboard-layout">
        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Reservas del mes</h2>
                <span class="<?= $trend_up ? 'trend-up' : 'trend-down' ?>">
                    <?= $trend_up ? '↗' : '↘' ?> <?= number_format(abs($porcentaje), 1, ',', '.') ?>%
                </span>
            </div>
            <div class="admin-chart">
                <div class="admin-chart-summary">
                    <div>
                        <p class="admin-chart-value"><?= number_format($reservas_mes) ?></p>
                        <div class="text-muted">Reservas confirmadas este mes vs mes anterior</div>
                    </div>
                    <div style="text-align:right;">
                        <strong class="<?= $trend_up ? 'trend-up' : 'trend-down' ?>">
                            <?= $trend_up ? '+' : '-' ?><?= number_format(abs($diferencia)) ?>
                        </strong>
                        <div class="text-muted">Diferencia</div>
                    </div>
                </div>
                <svg viewBox="0 0 520 180" width="100%" height="180" role="img" aria-label="Comparacion de reservas">
                    <line x1="42" y1="150" x2="490" y2="150" stroke="#dbe4f0" stroke-width="2"/>
                    <rect x="120" y="<?= 150 - $bar_anterior ?>" width="82" height="<?= $bar_anterior ?>" rx="12" fill="#cbd5e1"/>
                    <rect x="320" y="<?= 150 - $bar_actual ?>" width="82" height="<?= $bar_actual ?>" rx="12" fill="<?= $chart_color ?>"/>
                    <text x="161" y="172" text-anchor="middle" fill="#5d718f" font-size="14">Mes anterior</text>
                    <text x="361" y="172" text-anchor="middle" fill="#5d718f" font-size="14">Este mes</text>
                    <text x="161" y="<?= max(22, 142 - $bar_anterior) ?>" text-anchor="middle" fill="#07142b" font-size="15" font-weight="700"><?= number_format($reservas_mes_anterior) ?></text>
                    <text x="361" y="<?= max(22, 142 - $bar_actual) ?>" text-anchor="middle" fill="#07142b" font-size="15" font-weight="700"><?= number_format($reservas_mes) ?></text>
                </svg>
            </div>
        </article>

        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Sistema</h2>
            </div>
            <div class="admin-system-body">
                <p style="font-size:20px; margin-top:0;"><span class="trend-up">↯</span> Estado: <strong class="trend-up">Operativo</strong></p>
                <div class="info-grid" style="grid-template-columns:1fr; gap:10px;">
                    <div style="display:flex; justify-content:space-between;"><span class="text-muted">Versión</span><strong>v1.4.2</strong></div>
                    <div style="display:flex; justify-content:space-between;"><span class="text-muted">DB size</span><strong><?= number_format($db_size_mb, 1, ',', '.') ?> MB</strong></div>
                </div>

                <a href="backup.php" class="btn" style="width:100%; margin-top:22px;" target="_blank">Exportar SQL</a>
                <a href="backup_recuperacion.php" class="btn btn-outline" style="width:100%; margin-top:12px;">Exportar paquete de recuperacion</a>

                <form action="import_backup.php" method="POST" enctype="multipart/form-data" style="margin-top:14px;">
                    <?= csrf_field() ?>
                    <input type="file" name="backup_file" accept=".sql" required>
                    <button type="submit" class="btn btn-outline" style="width:100%;">Importar Backup</button>
                </form>
            </div>
        </article>
    </section>

    <section class="admin-dashboard-layout">
        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Viajes recientes</h2>
                <span class="results-count"><?= count($viajes_recientes) ?> total</span>
            </div>
            <?php if (empty($viajes_recientes)): ?>
                <div class="admin-list-row"><span class="text-muted">Todavía no hay viajes registrados.</span></div>
            <?php else: ?>
                <?php foreach ($viajes_recientes as $v): ?>
                    <div class="admin-list-row">
                        <strong><?= htmlspecialchars($v['CiudadOrigen']) ?> <span style="color:var(--text-muted);">→</span> <?= htmlspecialchars($v['CiudadDestino']) ?></strong>
                        <span class="text-muted"><?= date('d M Y', strtotime($v['HoraSalida'])) ?></span>
                        <strong class="trend-up">$<?= number_format($v['Precio'], 0, ',', '.') ?></strong>
                        <span class="badge <?= $v['Estado'] === 'Activa' ? 'badge-success' : 'badge-primary' ?>"><?= htmlspecialchars($v['Estado']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </article>

        <article class="admin-panel">
            <div class="admin-panel-head">
                <h2>Atajos</h2>
            </div>
            <div class="admin-system-body">
                <a href="conductores.php" class="btn btn-outline" style="width:100%; margin-bottom:12px;">Revisar conductores</a>
                <a href="vehiculos.php" class="btn btn-outline" style="width:100%; margin-bottom:12px;">Revisar vehículos</a>
                <?php if (PAYMENTS_ENABLED): ?>
                    <a href="pagos.php" class="btn btn-outline" style="width:100%;">Ver pagos</a>
                <?php else: ?>
                    <a href="reportes.php" class="btn btn-outline" style="width:100%;">Ver reportes</a>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <h2>Conductores pendientes de aprobación</h2>
            <span class="badge badge-orange"><?= count($pendientes) ?></span>
        </div>

        <?php if (empty($pendientes)): ?>
            <div class="admin-pending-row">
                <span class="text-muted">No hay solicitudes pendientes.</span>
            </div>
        <?php else: ?>
            <?php foreach ($pendientes as $c): ?>
                <div class="admin-pending-row">
                    <div class="driver-chip">
                        <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($c['Nombre'], 0, 1))) ?></span>
                        <div>
                            <strong><?= htmlspecialchars(trim($c['Nombre'] . ' ' . $c['Apellido'])) ?></strong>
                            <div class="text-muted"><?= htmlspecialchars($c['Correo']) ?> · <?= htmlspecialchars($c['LicenciaConducir']) ?></div>
                        </div>
                    </div>
                    <div class="admin-actions">
                        <form method="POST" action="conductores.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conductor_id" value="<?= $c['ID_conductor'] ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar">Aprobar</button>
                        </form>
                        <form method="POST" action="conductores.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="conductor_id" value="<?= $c['ID_conductor'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Rechazar este conductor?');">Rechazar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<script>
function descargarReporteAdmin() {
    const anterior = document.getElementById('reporte-admin-descarga-frame');
    if (anterior) {
        anterior.remove();
    }

    const desde = document.getElementById('reporte-pdf-desde')?.value || '';
    const hasta = document.getElementById('reporte-pdf-hasta')?.value || '';
    const params = new URLSearchParams({
        download: '1',
        t: String(Date.now())
    });
    if (desde) params.set('desde', desde);
    if (hasta) params.set('hasta', hasta);

    const frame = document.createElement('iframe');
    frame.id = 'reporte-admin-descarga-frame';
    frame.title = 'Descarga de reporte administrativo';
    frame.style.position = 'fixed';
    frame.style.left = '-1400px';
    frame.style.top = '0';
    frame.style.width = '1280px';
    frame.style.height = '920px';
    frame.style.opacity = '0';
    frame.style.pointerEvents = 'none';
    frame.style.border = '0';
    frame.src = 'reporte_pdf.php?' + params.toString();
    document.body.appendChild(frame);
}
</script>

</body>
</html>
