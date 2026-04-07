<?php
session_start();
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$viaje_id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.ID_reserva AS reserva_id, r.Estado, r.CodigoAcceso, u.Nombre AS nombre, u.Correo AS email, u.Telefono
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
    WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
    ORDER BY u.Nombre ASC
");
$stmt->execute([$viaje_id]);
$reservas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Lista de Pasajeros</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <!-- Cargar html2pdf para exportar a PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        .boarding-header {
            background-color: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0;
        }
        .boarding-body {
            background-color: #fff;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-top: none;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .passenger-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-left: 5px solid var(--primary);
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 6px;
        }
        .passenger-info h3 { margin: 0 0 5px 0; color: #1E293B; }
        .passenger-info p { margin: 2px 0; color: #475569; font-size: 0.95em; }
        .passenger-code {
            text-align: center;
            background: #EFF6FF;
            padding: 10px 20px;
            border-radius: 6px;
            border: 1px solid #BFDBFE;
        }
        .passenger-code span {
            display: block;
            font-size: 1.5em;
            font-weight: bold;
            color: #1D4ED8;
            letter-spacing: 2px;
        }
        .passenger-code small { color: #64748B; font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: 600;
        }
        .status-paid { background-color: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
        .status-pending { background-color: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }

        @media print {
            body { background: white; padding: 0; max-width: 100%; }
            .no-print { display: none !important; }
            .passenger-row { break-inside: avoid; border: 1px solid #ddd; }
            .boarding-body { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>

<!-- Menú de la aplicación (no se incluye en el PDF) -->
<div class="no-print">
    <?php include __DIR__ . '/_nav.php'; ?>
    
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <a href="viajes.php" style="font-weight: bold;">← Volver a Mis Viajes</a>
        <button onclick="generarPDF()" class="btn" style="background-color: var(--success); display: flex; align-items: center; gap: 8px;">
            📄 Descargar Lista (PDF)
        </button>
    </div>
</div>

<!-- Contenedor principal que se exportará a PDF -->
<div id="manifest-content">
    <div class="boarding-header">
        <div>
            <h2 style="margin: 0; color: white;">Planilla de Pasajeros</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">Viaje ID #<?= str_pad($viaje_id, 4, '0', STR_PAD_LEFT) ?></p>
        </div>
        <div style="text-align: right;">
            <p style="margin: 0; font-weight: bold; font-size: 1.2em;">Total: <?= count($reservas) ?> pasajero(s)</p>
            <p style="margin: 5px 0 0.8em 0; font-size: 0.9em; opacity: 0.8;">Lista generada para validación al abordar</p>
        </div>
    </div>

    <div class="boarding-body">
        <?php if (empty($reservas)): ?>
            <div style="text-align: center; padding: 40px; color: #64748b;">
                <p>No hay pasajeros confirmados para este viaje todavía.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($reservas as $index => $r): ?>
            <div class="passenger-row">
                <div class="passenger-info" style="flex: 1; min-width: 200px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                        <span style="background: #E2E8F0; color: #475569; width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8em; font-weight: bold;"><?= $index + 1 ?></span>
                        <h3><?= htmlspecialchars($r['nombre']) ?></h3>
                        <?php if ($r['Estado'] === 'Completada'): ?>
                            <span class="status-badge status-paid">Pagado</span>
                        <?php else: ?>
                            <span class="status-badge status-pending"><?= htmlspecialchars($r['Estado']) ?></span>
                        <?php endif; ?>
                    </div>
                    <p>✉️ <?= htmlspecialchars($r['email']) ?></p>
                    <p>📞 <?= htmlspecialchars($r['Telefono'] ?? 'No especificado') ?></p>
                </div>

                <?php if ($r['CodigoAcceso']): ?>
                    <div class="passenger-code" style="margin: 10px 0;">
                        <small>Código de Validación</small>
                        <span><?= htmlspecialchars($r['CodigoAcceso']) ?></span>
                    </div>
                <?php endif; ?>

                <div class="no-print" style="width: 100%; margin-top: 10px; text-align: right;">
                    <a href="eliminar_reserva.php?id=<?= $r['reserva_id'] ?>&viaje=<?= $viaje_id ?>" 
                       style="color: #EF4444; font-size: 0.9em; text-decoration: underline;" 
                       onclick="return confirm('ATENCIÓN: ¿Seguro que deseas cancelar esta reserva confirmada?')">
                       Cancelar Pasaje
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 30px; text-align: center; color: #94A3B8; font-size: 0.85em;">
            <p>Por favor, solicite el 'Código de Validación' a cada pasajero al momento de subir al vehículo para comprobar su identidad y el cobro del viaje.</p>
            <p>Documento generado el <?= date('d/m/Y H:i') ?></p>
        </div>
    </div>
</div>

<script>
function generarPDF() {
    const element = document.getElementById('manifest-content');
    const opt = {
      margin:       10,
      filename:     'lista_pasajeros_viaje_<?= $viaje_id ?>.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>
