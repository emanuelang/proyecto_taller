<?php
session_start();
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

$viaje_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$post_viaje = isset($_GET['post_viaje']) && $_GET['post_viaje'] === '1';
$post_viaje_suffix = $post_viaje ? '&post_viaje=1' : '';

$stmt_viaje = $pdo->prepare("
    SELECT p.CiudadOrigen, p.CiudadDestino, p.HoraSalida, p.Precio
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
");
$stmt_viaje->execute([$viaje_id, $_SESSION['conductor_id']]);
$viaje = $stmt_viaje->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die('Viaje no encontrado o sin permisos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'reportar_pasajero') {
    require_csrf();

    $reserva_id = (int)($_POST['reserva_id'] ?? 0);
    $motivo = $_POST['motivo'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $motivos_validos = [
        'no_se_presento' => 'No se presento',
        'no_pago' => 'No pago',
        'datos_falsos' => 'Datos falsos',
        'conducta_inapropiada' => 'Conducta inapropiada',
        'otro' => 'Otro',
    ];

    if (!isset($motivos_validos[$motivo])) {
        header("Location: ver_reservas.php?id=$viaje_id$post_viaje_suffix&err=" . urlencode('Motivo de reporte invalido.'));
        exit;
    }

    if ($descripcion !== '' && strlen($descripcion) > 800) {
        header("Location: ver_reservas.php?id=$viaje_id$post_viaje_suffix&err=" . urlencode('La descripcion no puede superar 800 caracteres.'));
        exit;
    }

    $stmt_reportable = $pdo->prepare("
        SELECT r.ID_reserva,
               COALESCE(r.ID_usuario_responsable, pas.ID_usuario) AS responsable_id,
               pas.ID_usuario AS usuario_reportado_id
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        JOIN ConductorPublicacion cp ON r.ID_publicacion = cp.ID_publicacion
        WHERE r.ID_reserva = ?
          AND r.ID_publicacion = ?
          AND cp.ID_conductor = ?
          AND r.Estado = 'Completada'
        LIMIT 1
    ");
    $stmt_reportable->execute([$reserva_id, $viaje_id, $_SESSION['conductor_id']]);
    $reportable = $stmt_reportable->fetch(PDO::FETCH_ASSOC);

    if (!$reportable) {
        header("Location: ver_reservas.php?id=$viaje_id$post_viaje_suffix&err=" . urlencode('No se encontro la reserva a reportar.'));
        exit;
    }

    $stmt_dup_report = $pdo->prepare("
        SELECT COUNT(*)
        FROM ReportesPasajeros
        WHERE ID_reserva = ?
          AND ID_conductor = ?
          AND Motivo = ?
          AND Estado = 'Pendiente'
    ");
    $stmt_dup_report->execute([$reserva_id, $_SESSION['conductor_id'], $motivo]);
    if ((int)$stmt_dup_report->fetchColumn() > 0) {
        header("Location: ver_reservas.php?id=$viaje_id$post_viaje_suffix&err=" . urlencode('Ya existe un reporte pendiente con ese motivo.'));
        exit;
    }

    $stmt_insert_report = $pdo->prepare("
        INSERT INTO ReportesPasajeros
            (ID_reserva, ID_usuario_reportado, ID_usuario_responsable, ID_conductor, Motivo, Descripcion)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");
    $stmt_insert_report->execute([
        $reserva_id,
        $reportable['usuario_reportado_id'],
        $reportable['responsable_id'],
        $_SESSION['conductor_id'],
        $motivos_validos[$motivo],
        $descripcion !== '' ? $descripcion : null
    ]);

    header("Location: ver_reservas.php?id=$viaje_id$post_viaje_suffix&msg=" . urlencode('Reporte enviado para revision de administracion.'));
    exit;
}

$stmt = $pdo->prepare("
    SELECT r.ID_reserva AS reserva_id, r.Estado, r.CodigoAcceso, r.TipoPasaje,
           u.ID_usuario AS usuario_reportado_id,
           COALESCE(r.ID_usuario_responsable, u.ID_usuario) AS responsable_id,
           COALESCE(NULLIF(r.PasajeroNombre, ''), u.Nombre) AS nombre,
           COALESCE(NULLIF(r.PasajeroApellido, ''), u.Apellido) AS apellido,
           COALESCE(NULLIF(r.PasajeroCorreo, ''), u.Correo) AS email,
           COALESCE(NULLIF(r.PasajeroTelefono, ''), u.Telefono) AS telefono,
           COALESCE(NULLIF(r.PasajeroDNI, ''), u.DNI) AS dni,
           u.Nombre AS responsable_nombre,
           u.Apellido AS responsable_apellido,
           u.Correo AS responsable_email,
           u.Telefono AS responsable_telefono,
           u.DNI AS responsable_dni
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
    WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
    ORDER BY u.Nombre ASC
");
$stmt->execute([$viaje_id]);
$reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/_nav.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    .boarding-header {
        background: var(--primary);
        color: white;
        padding: 24px 26px;
        border-radius: 18px 18px 0 0;
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: center;
    }

    .boarding-body {
        background: #fff;
        padding: 26px;
        border: 1px solid var(--border-color);
        border-top: none;
        border-radius: 0 0 18px 18px;
        box-shadow: var(--shadow);
    }

    .passenger-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 18px;
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-left: 5px solid var(--primary);
        margin-bottom: 16px;
        padding: 18px;
        border-radius: 14px;
    }

    .passenger-info h3 {
        margin: 0 0 6px;
        color: var(--text-main);
    }

    .passenger-info p {
        margin: 3px 0;
        color: var(--text-muted);
    }

    .passenger-code {
        min-width: 270px;
        text-align: center;
        background: #eff6ff;
        padding: 14px 20px;
        border-radius: 12px;
        border: 1px solid #bfdbfe;
    }

    .passenger-code small {
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .passenger-code span {
        display: block;
        margin-top: 6px;
        color: var(--primary);
        font-size: 30px;
        font-weight: 850;
        letter-spacing: 3px;
    }

    @media (max-width: 760px) {
        .boarding-header,
        .passenger-row {
            grid-template-columns: 1fr;
            display: grid;
        }

        .passenger-code {
            min-width: 0;
        }
    }

    @media print {
        .no-print,
        .app-sidebar,
        .sidebar-main-toggle {
            display: none !important;
        }

        .app-main {
            margin: 0;
            padding: 0;
        }

        .passenger-row {
            break-inside: avoid;
        }

        .boarding-body {
            box-shadow: none;
        }
    }
</style>

<div class="page-shell">
    <div class="no-print" style="display:flex; justify-content:space-between; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:22px;">
        <a href="viajes.php<?= $post_viaje ? '?vista=historial' : '' ?>" class="btn btn-outline">Volver a Mis Viajes</a>
        <?php if (!$post_viaje): ?>
            <button onclick="generarPDF()" class="btn success-bg">Descargar Lista (PDF)</button>
        <?php endif; ?>
    </div>

    <?php if ($post_viaje): ?>
        <div class="card" style="margin-bottom:18px;">
            <h2 style="margin-top:0;">Revision posterior al viaje</h2>
            <p class="text-muted" style="margin-bottom:0;">Elegi el pasajero correspondiente y envia un reporte si hubo un problema.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg'])): ?>
        <div class="card" style="background:#f0fdf4; color:#047857;">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['err'])): ?>
        <div class="card" style="background:#fef2f2; color:#b91c1c;">
            <?= htmlspecialchars($_GET['err']) ?>
        </div>
    <?php endif; ?>

    <div id="manifest-content">
        <div class="boarding-header">
            <div>
                <h2 style="margin:0; color:white;">Planilla de Pasajeros</h2>
                <p style="margin:6px 0 0; opacity:.9;">
                    Viaje ID #<?= str_pad($viaje_id, 4, '0', STR_PAD_LEFT) ?> Â·
                    <?= htmlspecialchars($viaje['CiudadOrigen']) ?> â†’ <?= htmlspecialchars($viaje['CiudadDestino']) ?>
                </p>
            </div>
            <div style="text-align:right;">
                <p style="margin:0; font-weight:850; font-size:22px;">Total: <?= count($reservas) ?> pasajero(s)</p>
                <p style="margin:6px 0 0; opacity:.85;">Lista generada para validaciÃ³n al abordar</p>
            </div>
        </div>

        <div class="boarding-body">
            <?php if (empty($reservas)): ?>
                <div style="text-align:center; padding:46px;">
                    <h3 style="margin-top:0;">No hay pasajeros confirmados</h3>
                    <p class="text-muted">Cuando alguien confirme una reserva en este viaje, aparecera en esta lista.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reservas as $index => $r): ?>
                    <div class="passenger-row">
                        <div class="passenger-info">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px; flex-wrap:wrap;">
                                <span class="mini-avatar" style="width:30px; height:30px;"><?= $index + 1 ?></span>
                                <h3><?= htmlspecialchars(trim($r['nombre'] . ' ' . $r['apellido'])) ?></h3>
                                <span class="badge badge-success">Reserva confirmada</span>
                                <?php if (($r['TipoPasaje'] ?? 'propio') === 'tercero'): ?>
                                    <span class="badge badge-orange">Tercero</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">Titular</span>
                                <?php endif; ?>
                            </div>
                            <p><strong>DNI:</strong> <?= htmlspecialchars($r['dni'] ?: 'No especificado') ?></p>
                            <p><strong>Telefono:</strong> <?= htmlspecialchars($r['telefono'] ?: 'No especificado') ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($r['email'] ?: 'No especificado') ?></p>

                            <?php if (($r['TipoPasaje'] ?? 'propio') === 'tercero'): ?>
                                <div class="info-tile" style="margin-top:12px;">
                                    <span>Responsable de la reserva</span>
                                    <strong><?= htmlspecialchars(trim($r['responsable_nombre'] . ' ' . $r['responsable_apellido'])) ?></strong>
                                    <p style="margin:8px 0 0;"><strong>DNI:</strong> <?= htmlspecialchars($r['responsable_dni'] ?: 'No especificado') ?></p>
                                    <p><strong>Telefono:</strong> <?= htmlspecialchars($r['responsable_telefono'] ?: 'No especificado') ?></p>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($r['responsable_email'] ?: 'No especificado') ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($r['CodigoAcceso'])): ?>
                            <div class="passenger-code">
                                <small>CÃ³digo de validaciÃ³n</small>
                                <span><?= htmlspecialchars($r['CodigoAcceso']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="no-print" style="grid-column:1 / -1; text-align:right;">
                            <details style="margin-bottom:12px; text-align:left;">
                                <summary class="btn btn-outline" style="display:inline-flex; cursor:pointer;">Reportar pasajero</summary>
                                <form method="POST" style="margin-top:12px; padding:14px; border:1px solid var(--border-color); border-radius:12px; background:white;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="accion" value="reportar_pasajero">
                                    <input type="hidden" name="reserva_id" value="<?= (int)$r['reserva_id'] ?>">
                                    <div class="info-grid" style="grid-template-columns: minmax(180px, 240px) 1fr; gap:12px;">
                                        <label>
                                            <span class="text-muted" style="display:block; font-weight:800; margin-bottom:6px;">Motivo</span>
                                            <select name="motivo" required>
                                                <option value="no_se_presento">No se presento</option>
                                                <option value="no_pago">No pago</option>
                                                <option value="datos_falsos">Datos falsos</option>
                                                <option value="conducta_inapropiada">Conducta inapropiada</option>
                                                <option value="otro">Otro</option>
                                            </select>
                                        </label>
                                        <label>
                                            <span class="text-muted" style="display:block; font-weight:800; margin-bottom:6px;">Detalle</span>
                                            <input type="text" name="descripcion" maxlength="800" placeholder="Agrega contexto para administracion">
                                        </label>
                                    </div>
                                    <button type="submit" class="btn btn-danger" style="margin-top:12px;" onclick="return confirm('Enviar este reporte a administracion?');">Enviar reporte</button>
                                </form>
                            </details>
                            <?php if (!$post_viaje): ?>
                            <a href="eliminar_reserva.php?id=<?= $r['reserva_id'] ?>&viaje=<?= $viaje_id ?>&csrf_token=<?= urlencode(csrf_token()) ?>"
                               class="btn btn-danger"
                               onclick="return confirm('Â¿Seguro que deseas cancelar esta reserva confirmada?');">
                                Cancelar pasaje
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="margin-top:26px; text-align:center; color:#94a3b8; font-size:14px;">
                <p>SolicitÃ¡ el cÃ³digo de validaciÃ³n a cada pasajero al momento de subir al vehÃ­culo.</p>
                <p>Documento generado el <?= date('d/m/Y H:i') ?></p>
            </div>
        </div>
    </div>
</div>

<script>
function generarPDF() {
    const element = document.getElementById('manifest-content');
    const opt = {
        margin: 10,
        filename: 'lista_pasajeros_viaje_<?= $viaje_id ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>
