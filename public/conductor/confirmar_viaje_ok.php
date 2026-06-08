<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/session_guard.php';

if (!isset($_SESSION['user_id'], $_SESSION['conductor_id']) || empty($_SESSION['is_conductor'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

$viaje_id = (int)($_GET['id'] ?? 0);
if ($viaje_id <= 0) {
    header("Location: " . BASE_URL . "conductor/viajes.php?vista=historial");
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.ID_publicacion
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ?
      AND cp.ID_conductor = ?
      AND (p.Estado = 'Finalizada' OR p.HoraSalida < NOW())
    LIMIT 1
");
$stmt->execute([$viaje_id, (int)$_SESSION['conductor_id']]);

if (!$stmt->fetch()) {
    header("Location: " . BASE_URL . "conductor/viajes.php?vista=historial");
    exit;
}

$stmt_ok = $pdo->prepare("
    INSERT INTO ConfirmacionesConductorViaje (ID_publicacion, ID_conductor, Estado)
    VALUES (?, ?, 'Todo bien')
    ON DUPLICATE KEY UPDATE
        Estado = VALUES(Estado),
        FechaConfirmacion = CURRENT_TIMESTAMP
");
$stmt_ok->execute([$viaje_id, (int)$_SESSION['conductor_id']]);

header("Location: " . BASE_URL . "conductor/viajes.php?vista=historial&msg=" . urlencode('Viaje confirmado sin problemas.'));
exit;
