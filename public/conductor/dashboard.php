<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor']) || !$_SESSION['is_conductor']) {
    die('Acceso denegado');
}

/* Datos del conductor */
$stmt = $pdo->prepare("
    SELECT u.nombre, u.email, c.estado
    FROM conductores c
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$conductor = $stmt->fetch();

/* Vehículos */
$stmt = $pdo->prepare("
    SELECT *
    FROM vehiculos
    WHERE conductor_id = ?
");
$stmt->execute([$_SESSION['conductor_id']]);
$vehiculos = $stmt->fetchAll();
?>

<h2>Panel del conductor</h2>

<p><strong>Nombre:</strong> <?= htmlspecialchars($conductor['nombre']) ?></p>
<p><strong>Email:</strong> <?= htmlspecialchars($conductor['email']) ?></p>
<p><strong>Estado:</strong> <?= htmlspecialchars($conductor['estado']) ?></p>

<hr>

<h3>Vehículos registrados</h3>

<?php if (empty($vehiculos)): ?>
    <p>No tenés vehículos registrados.</p>
<?php else: ?>
    <ul>
        <?php foreach ($vehiculos as $v): ?>
            <li>
                <?= htmlspecialchars($v['marca']) ?>
                <?= htmlspecialchars($v['modelo']) ?> -
                <?= htmlspecialchars($v['color']) ?> -
                <?= htmlspecialchars($v['patente']) ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<br>
<a href="vehiculos.php">Administrar vehículos</a>

<hr>

<a href="viajes.php">Mis viajes</a><br><br>
<a href="../crear_viaje.php">Crear nuevo viaje</a><br><br>
<a href="../index.php">Volver al inicio</a>
