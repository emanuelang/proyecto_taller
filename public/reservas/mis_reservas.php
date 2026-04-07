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
    die("Error: No estás registrado como pasajero.");
}
$pasajero_id = $pasajero['ID_pasajero'];

$sql = "SELECT 
            r.ID_reserva AS reserva_id,
            r.FechaReserva AS fecha_reserva,
            r.Estado AS estado,
            r.CodigoAcceso AS codigo_acceso,
            p.HoraSalida AS fecha,
            p.Precio AS precio,
            u.Nombre AS conductor_nombre,
            p.CiudadOrigen AS origen_nombre,
            p.CiudadDestino AS destino_nombre
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
        WHERE pr.ID_pasajero = ? AND r.Estado = 'Completada'
        ORDER BY p.HoraSalida ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pasajero_id]);
$reservas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mis Reservas</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
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
            <p><strong>Fecha Reserva:</strong> <?= date('d/m/Y H:i', strtotime($r['fecha_reserva'])) ?></p>
            
            <?php if ($r['codigo_acceso']): ?>
                <div style="margin: 15px 0; padding: 15px; background-color: #ecfdf5; border-left: 4px solid var(--success); border-radius: 4px;">
                    <p style="margin: 0 0 5px 0; color: #065f46; font-size: 0.9em; font-weight: bold;">Tu código de acceso (Póstergaselo al conductor):</p>
                    <p style="margin: 0; font-size: 1.4em; font-family: monospace; color: var(--success); letter-spacing: 2px; font-weight: bold;">
                        <?= htmlspecialchars($r['codigo_acceso']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <p>
                <strong>Estado:</strong> 
                <span style="color: var(--success); font-weight: bold;">✅ Confirmada y Pagada</span>
            </p>

            <form method="POST" action="cancelar_reserva.php">
                <input type="hidden" name="reserva_id" value="<?= $r['reserva_id'] ?>">
                <button type="submit" style="margin-top: 10px; width: 100%;" onclick="return confirm('¿Seguro quieres cancelar tu asiento? Se eliminará de tus reservas y dejará el lugar libre para otra persona.')">Cancelar reserva</button>
            </form>
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
