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
<?php include __DIR__ . '/_nav.php'; ?>

<?php if (empty($viajes)): ?>
    <div class="card" style="text-align: center; color: #64748b; padding: 40px;">
        <p style="font-size: 1.2em;">No creaste viajes todavía.</p>
        <a href="<?= BASE_URL ?>crear_viaje.php" class="btn" style="margin-top: 15px; display: inline-block;">Crear Viaje</a>
    </div>
<?php else: ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
    <?php foreach ($viajes as $v): ?>
        <div class="card" style="margin-bottom: 0;">
            <h3 style="margin-top: 0; color: var(--primary);">
                <?= htmlspecialchars($v['CiudadOrigen']) ?> → <?= htmlspecialchars($v['CiudadDestino']) ?>
            </h3>

            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($v['HoraSalida'])) ?></p>
            <p><strong>Precio:</strong> $<?= number_format($v['Precio'], 2) ?></p>

            <div style="margin-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                <a href="<?= BASE_URL ?>conductor/ver_reservas.php?id=<?= $v['ID_publicacion'] ?>" class="btn" style="text-align: center; background-color: var(--success); color: white;">
                    👥 Ver Pasajeros / Validar
                </a>
                <a href="<?= BASE_URL ?>crear_viaje.php?origen=<?= urlencode($v['CiudadOrigen']) ?>&destino=<?= urlencode($v['CiudadDestino']) ?>&precio=<?= $v['Precio'] ?>" class="btn" style="text-align: center; background-color: var(--surface); color: var(--primary); border: 1px solid var(--primary);">
                    📋 Reutilizar como Plantilla
                </a>
                <a href="<?= BASE_URL ?>conductor/eliminar_viaje.php?id=<?= $v['ID_publicacion'] ?>" class="btn" style="text-align: center; background-color: #ef4444;" onclick="return confirm('¿Eliminar viaje?')">
                    Eliminar viaje
                </a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

