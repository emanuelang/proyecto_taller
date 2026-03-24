<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['user_id']) || !$_SESSION['is_conductor']) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$sql = "SELECT p.*
        FROM Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        WHERE cp.ID_conductor = ?
        ORDER BY p.HoraSalida ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['conductor_id']]);
$viajes = $stmt->fetchAll();
?>

<h2>Mis viajes</h2>
<a href="<?= BASE_URL ?>conductor/dashboard.php">← Volver</a>
<hr>

<?php if (empty($viajes)): ?>
    <p>No creaste viajes todavía.</p>
<?php endif; ?>

<?php foreach ($viajes as $v): ?>
    <div>
        <strong>
            <?= htmlspecialchars($v['CiudadOrigen']) ?>
            →
            <?= htmlspecialchars($v['CiudadDestino']) ?>
        </strong><br>

        Fecha: <?= $v['HoraSalida'] ?><br>
        Precio: $<?= $v['Precio'] ?><br><br>

        <a href="<?= BASE_URL ?>conductor/eliminar_viaje.php?id=<?= $v['ID_publicacion'] ?>">
            Eliminar viaje
        </a>
        &nbsp;|&nbsp;
        <a href="<?= BASE_URL ?>crear_viaje.php?origen=<?= $v['origen_id'] ?>&destino=<?= $v['destino_id'] ?>&precio=<?= $v['precio'] ?>&observaciones=<?= urlencode($v['observaciones'] ?? '') ?>">
            📋 Reutilizar como Plantilla
        </a>
    </div>
    <hr>
<?php endforeach; ?>
