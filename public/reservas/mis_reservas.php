<?php
session_start();
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$usuario_id = $_SESSION['user_id'];

// Para las reservas del usuario, necesitamos encontrar el ID de Pasajero
$stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
$stmt_pasajero->execute([$usuario_id]);
$pasajero = $stmt_pasajero->fetch();

if (!$pasajero) {
    die("Error: No estÃ¡s registrado como pasajero.");
}
$pasajero_id = $pasajero['ID_pasajero'];

$sql = "SELECT 
            r.ID_reserva AS reserva_id,
            p.ID_publicacion AS publicacion_id,
            r.FechaReserva AS fecha_reserva,
            r.Estado AS estado,
            r.CodigoAcceso AS codigo_acceso,
            p.HoraSalida AS fecha,
            p.Precio AS precio,
            u.Nombre AS conductor_nombre,
            u.Correo AS conductor_correo,
            c.TelefonoContacto AS conductor_telefono,
            p.CiudadOrigen AS origen_nombre,
            p.CiudadDestino AS destino_nombre
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
        WHERE pr.ID_pasajero = ? AND r.Estado = 'Completada' AND p.HoraSalida >= NOW()
        ORDER BY p.HoraSalida ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pasajero_id]);
$reservas = $stmt->fetchAll();
?>

<?php require_once __DIR__ . '/../header.php'; ?>

<div class="page-shell">
    <h1 class="page-title">Mis reservas</h1>
    <p class="page-subtitle">Gestioná tus viajes reservados</p>

    <div class="tabs">
        <span class="tab active">Activas</span>
        <a href="<?= BASE_URL ?>reservas/historial_viajes.php" class="tab">Historial</a>
    </div>

    <?php if (isset($_SESSION['mensaje_exito'])): ?>
        <div class="card" style="background:#f0fdf4; color:#047857;">
            <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
        </div>
        <?php unset($_SESSION['mensaje_exito']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['mensaje_cancelacion'])): ?>
        <div class="card text-muted">
            <?= htmlspecialchars($_SESSION['mensaje_cancelacion']) ?>
        </div>
        <?php unset($_SESSION['mensaje_cancelacion']); ?>
    <?php endif; ?>

    <?php if (count($reservas) > 0): ?>
        <div class="reservation-list">
        <?php foreach ($reservas as $r): ?>
            <article class="card reservation-card">
                <div class="reservation-head">
                    <div>
                        <h2 class="reservation-title"><?= htmlspecialchars($r['origen_nombre']) ?> <span style="color:var(--primary);">→</span> <?= htmlspecialchars($r['destino_nombre']) ?></h2>
                        <div class="trip-meta">
                            <span>▣ <?= date('d M Y', strtotime($r['fecha'])) ?></span>
                            <span>◷ <?= date('H:i', strtotime($r['fecha'])) ?> hs</span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge-success">Confirmado</span>
                        <div class="trip-price" style="margin-top:12px;">$<?= number_format($r['precio'], 0, ',', '.') ?></div>
                    </div>
                </div>

                <div class="driver-chip" style="margin:20px 0;">
                    <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($r['conductor_nombre'], 0, 1))) ?></span>
                    <div>
                        <strong><?= htmlspecialchars($r['conductor_nombre']) ?></strong>
                        <div class="text-muted" style="font-size:14px;">
                            <?php if ($r['conductor_telefono']): ?><?= htmlspecialchars($r['conductor_telefono']) ?><?php endif; ?>
                            <?php if ($r['conductor_correo']): ?> · <?= htmlspecialchars($r['conductor_correo']) ?><?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if ($r['codigo_acceso']): ?>
                    <div class="info-tile" style="margin-bottom:20px; background:#ecfdf5;">
                        <span>Código de acceso</span>
                        <strong style="font-family:Consolas, monospace; color:var(--success); letter-spacing:2px;"><?= htmlspecialchars($r['codigo_acceso']) ?></strong>
                    </div>
                <?php endif; ?>

                <div class="reservation-actions">
                    <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $r['publicacion_id'] ?>" class="btn btn-outline">Ver Detalle</a>
                    <form method="POST" action="cancelar_reserva.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="reserva_id" value="<?= $r['reserva_id'] ?>">
                        <button type="submit" class="btn-danger" onclick="return confirm('¿Seguro querés cancelar tu asiento? Se eliminará de tus reservas y dejará el lugar libre para otra persona.')">Cancelar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align:center; padding:42px;">
            <p class="text-muted" style="font-size:1.15em;">No tenés reservas activas actualmente.</p>
            <a href="<?= BASE_URL ?>index.php" class="btn" style="margin-top:12px;">Buscar viajes</a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
