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

$stmt = $pdo->prepare("
    SELECT r.ID_reserva AS reserva_id, r.Estado, r.CodigoAcceso,
           u.Nombre AS nombre, u.Apellido AS apellido, u.Correo AS email, u.Telefono
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
        <a href="viajes.php" class="btn btn-outline">Volver a Mis Viajes</a>
        <button onclick="generarPDF()" class="btn success-bg">Descargar Lista (PDF)</button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="card" style="background:#f0fdf4; color:#047857;">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <div id="manifest-content">
        <div class="boarding-header">
            <div>
                <h2 style="margin:0; color:white;">Planilla de Pasajeros</h2>
                <p style="margin:6px 0 0; opacity:.9;">
                    Viaje ID #<?= str_pad($viaje_id, 4, '0', STR_PAD_LEFT) ?> ·
                    <?= htmlspecialchars($viaje['CiudadOrigen']) ?> → <?= htmlspecialchars($viaje['CiudadDestino']) ?>
                </p>
            </div>
            <div style="text-align:right;">
                <p style="margin:0; font-weight:850; font-size:22px;">Total: <?= count($reservas) ?> pasajero(s)</p>
                <p style="margin:6px 0 0; opacity:.85;">Lista generada para validación al abordar</p>
            </div>
        </div>

        <div class="boarding-body">
            <?php if (empty($reservas)): ?>
                <div style="text-align:center; padding:46px;">
                    <h3 style="margin-top:0;">No hay pasajeros confirmados</h3>
                    <p class="text-muted">Cuando alguien reserve y pague este viaje, aparecerá en esta lista.</p>
                </div>
            <?php else: ?>
                <?php foreach ($reservas as $index => $r): ?>
                    <div class="passenger-row">
                        <div class="passenger-info">
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px; flex-wrap:wrap;">
                                <span class="mini-avatar" style="width:30px; height:30px;"><?= $index + 1 ?></span>
                                <h3><?= htmlspecialchars(trim($r['nombre'] . ' ' . $r['apellido'])) ?></h3>
                                <span class="badge badge-success">Pagado</span>
                            </div>
                            <p><?= htmlspecialchars($r['email']) ?></p>
                            <p><?= htmlspecialchars($r['Telefono'] ?: 'No especificado') ?></p>
                        </div>

                        <?php if (!empty($r['CodigoAcceso'])): ?>
                            <div class="passenger-code">
                                <small>Código de validación</small>
                                <span><?= htmlspecialchars($r['CodigoAcceso']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="no-print" style="grid-column:1 / -1; text-align:right;">
                            <a href="eliminar_reserva.php?id=<?= $r['reserva_id'] ?>&viaje=<?= $viaje_id ?>&csrf_token=<?= urlencode(csrf_token()) ?>"
                               class="btn btn-danger"
                               onclick="return confirm('¿Seguro que deseas cancelar esta reserva confirmada?');">
                                Cancelar pasaje
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="margin-top:26px; text-align:center; color:#94a3b8; font-size:14px;">
                <p>Solicitá el código de validación a cada pasajero al momento de subir al vehículo.</p>
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
