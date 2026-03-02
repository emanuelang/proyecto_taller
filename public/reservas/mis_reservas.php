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
            c2.nombre AS destino_nombre
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

            <?php if ($r['estado'] === 'activa'): ?>
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
