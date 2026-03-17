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
        WHERE pr.ID_pasajero = ?
        ORDER BY p.HoraSalida ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pasajero_id]);
$reservas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Mis Reservas</title>
</head>
<body>

<h2>Mis reservas</h2>
<a href="<?= BASE_URL ?>index.php">← Volver</a>
<hr>

<?php if (count($reservas) > 0): ?>
    <?php foreach ($reservas as $r): ?>
        <div style="margin-bottom:20px;border:1px solid #ccc;padding:10px;">
            <strong><?= htmlspecialchars($r['origen_nombre']) ?> → <?= htmlspecialchars($r['destino_nombre']) ?></strong><br>
            Fecha del viaje: <?= $r['fecha'] ?><br>
            Precio: $<?= number_format($r['precio'], 2) ?><br>
            Conductor: <?= htmlspecialchars($r['conductor_nombre']) ?><br>
            Reservado el: <?= $r['fecha_reserva'] ?><br>
            Estado: <?= $r['estado'] ?><br><br>

            <?php if ($r['estado'] === 'Pendiente' || $r['estado'] === 'Aceptada'): ?>
                <form method="POST" action="cancelar_reserva.php">
                    <input type="hidden" name="reserva_id" value="<?= $r['reserva_id'] ?>">
                    <button type="submit">Cancelar reserva</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>No tenés reservas registradas.</p>
<?php endif; ?>

</body>
</html>
