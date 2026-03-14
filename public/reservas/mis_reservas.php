<?php
session_start();
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$usuario_id = $_SESSION['user_id'];

$sql = "SELECT 
            r.id AS reserva_id,
            r.fecha_reserva,
            r.estado,
            v.fecha,
            v.precio,
            u.nombre AS conductor_nombre,
            c1.nombre AS origen_nombre,
            c2.nombre AS destino_nombre,
            (SELECT COUNT(*) FROM calificaciones calif WHERE calif.reserva_id = r.id) AS calificada
        FROM reservas r
        JOIN viajes v ON r.viaje_id = v.id
        JOIN conductores c ON v.conductor_id = c.id
        JOIN usuarios u ON c.usuario_id = u.id
        JOIN ciudades c1 ON v.origen_id = c1.id
        JOIN ciudades c2 ON v.destino_id = c2.id
        WHERE r.usuario_id = ?
        ORDER BY v.fecha ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$reservas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mis Reservas</title>
</head>
<body>

<div class="nav-menu">
    <h2>Mis reservas</h2>
    <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Volver al Dashboard</a>
</div>

<?php if (isset($_SESSION['mensaje_exito'])): ?>
    <div style="padding: 15px; margin-bottom: 20px; border-radius: 6px; background-color: #f0fdf4; border: 1px solid var(--success); color: var(--success-hover);">
        <?= htmlspecialchars($_SESSION['mensaje_exito']) ?>
    </div>
    <?php unset($_SESSION['mensaje_exito']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['mensaje_cancelacion'])): ?>
    <div style="padding: 15px; margin-bottom: 20px; border-radius: 6px; background-color: #f8fafc; border: 1px solid #94a3b8; color: #475569;">
        <?= htmlspecialchars($_SESSION['mensaje_cancelacion']) ?>
    </div>
    <?php unset($_SESSION['mensaje_cancelacion']); ?>
<?php endif; ?>

<?php if (count($reservas) > 0): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
    <?php foreach ($reservas as $r): ?>
        <div class="card" style="margin-bottom: 0;">
            <h3 style="margin-top: 0; color: var(--primary);">
                <?= htmlspecialchars($r['origen_nombre']) ?> → <?= htmlspecialchars($r['destino_nombre']) ?>
            </h3>
            
            <p><strong>Fecha del viaje:</strong> <?= date('d/m/Y H:i', strtotime($r['fecha'])) ?></p>
            <p><strong>Precio:</strong> $<?= number_format($r['precio'], 2) ?></p>
            <p><strong>Conductor:</strong> <?= htmlspecialchars($r['conductor_nombre']) ?></p>
            <p><strong>Fecha Reserva:</strong> <?= date('d/m/Y H:i', strtotime($r['fecha_reserva'])) ?></p>
            
            <p>
                <strong>Estado:</strong> 
                <?php if ($r['estado'] === 'activa'): ?>
                    <span style="color: var(--success); font-weight: bold;">Activa</span>
                <?php else: ?>
                    <span style="color: #ef4444; font-weight: bold;">Cancelada</span>
                <?php endif; ?>
            </p>

            <div style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
            <?php if ($r['estado'] === 'activa'): ?>
                <?php if (strtotime($r['fecha']) > time()): ?>
                    <form method="POST" action="cancelar_reserva.php" style="padding:0; box-shadow:none; border:none; margin:0;" onsubmit="return confirm('¿Seguro quieres cancelar esta reserva?');">
                        <input type="hidden" name="reserva_id" value="<?= $r['reserva_id'] ?>">
                        <button type="submit" style="background-color: #f1f5f9; color: #ef4444; border: 1px solid #ef4444; width: 100%;">Cancelar Reserva</button>
                    </form>
                <?php else: ?>
                    <?php if ($r['calificada'] == 0): ?>
                        <a href="calificar.php?reserva_id=<?= $r['reserva_id'] ?>" class="btn" style="display:block; text-align:center;">⭐ Calificar al conductor</a>
                    <?php else: ?>
                        <p style="color:var(--success); margin:0; text-align:center; font-weight:500;"><em>✔ Ya calificaste este viaje.</em></p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:#64748b; margin:0; text-align:center; font-size: 0.9em;">Este viaje ya no está activo</p>
            <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card" style="text-align: center; color: #64748b; padding: 40px;">
        <p style="font-size: 1.2em;">No tenés reservas registradas actualmente.</p>
        <a href="<?= BASE_URL ?>index.php" class="btn" style="margin-top: 15px; display: inline-block;">Buscar Viajes</a>
    </div>
<?php endif; ?>

</body>
</html>
