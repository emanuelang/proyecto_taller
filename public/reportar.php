<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

$conductor_id = isset($_GET['conductor_id']) ? (int)$_GET['conductor_id'] : 0;
if ($conductor_id <= 0) {
    die("Conductor no especificado.");
}

$publicacion_id = isset($_GET['publicacion_id']) ? (int)$_GET['publicacion_id'] : null;
$viaje_reportado = null;

$stmt_c = $pdo->prepare("
    SELECT u.Nombre, u.Apellido
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.ID_conductor = ?
");
$stmt_c->execute([$conductor_id]);
$conductor = $stmt_c->fetch(PDO::FETCH_ASSOC);

if (!$conductor) {
    die("El conductor especificado no existe.");
}

if ($publicacion_id) {
    $stmt_viaje = $pdo->prepare("
        SELECT p.ID_publicacion, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida
        FROM Publicaciones p
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Reservas r ON p.ID_publicacion = r.ID_publicacion
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        WHERE p.ID_publicacion = ?
          AND cp.ID_conductor = ?
          AND pas.ID_usuario = ?
          AND r.Estado IN ('Completada', 'Cancelada')
        LIMIT 1
    ");
    $stmt_viaje->execute([$publicacion_id, $conductor_id, $_SESSION['user_id']]);
    $viaje_reportado = $stmt_viaje->fetch(PDO::FETCH_ASSOC);
    if (!$viaje_reportado) {
        $publicacion_id = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($descripcion !== '') {
        $stmt = $pdo->prepare("
            INSERT INTO Reportes
                (Hora, Fecha, Descripcion, ID_conductor, ID_publicacion, ID_usuario_reportante)
            VALUES
                (CURTIME(), CURDATE(), ?, ?, ?, ?)
        ");
        $stmt->execute([$descripcion, $conductor_id, $publicacion_id, $_SESSION['user_id']]);

        $msg_exito = "Gracias, tu reporte fue enviado y sera revisado por administracion.";
    } else {
        $msg_error = "Por favor, ingresa los detalles del reporte.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reportar conductor - MOVEON</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<div class="nav-menu">
    <h2>Reportar un problema</h2>
    <a href="<?= BASE_URL ?>reservas/historial_viajes.php" style="margin-left: auto;">Volver al historial</a>
</div>

<div style="max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background: #fafafa;">
    <h3 style="color: #dc3545; margin-top: 0;">Reportar al conductor: <?= htmlspecialchars($conductor['Nombre'] . ' ' . $conductor['Apellido']) ?></h3>
    <?php if ($viaje_reportado): ?>
        <div class="info-tile" style="margin-bottom:16px;">
            <span>Viaje reportado</span>
            <strong><?= htmlspecialchars($viaje_reportado['CiudadOrigen']) ?> -> <?= htmlspecialchars($viaje_reportado['CiudadDestino']) ?></strong>
            <p class="text-muted" style="margin:8px 0 0;"><?= date('d/m/Y H:i', strtotime($viaje_reportado['HoraSalida'])) ?></p>
        </div>
    <?php endif; ?>

    <p>Si tuviste un problema con este conductor, informanos a continuacion. Tu identidad no se mostrara al conductor, pero administracion si podra verla para revisar el caso.</p>

    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
        <p><a href="<?= BASE_URL ?>reservas/historial_viajes.php" class="btn">Volver al historial</a></p>
    <?php else: ?>
        <?php if (isset($msg_error)): ?>
            <p style="color: red; font-weight: bold; background: #ffebee; padding: 10px; border: 1px solid #ffcdd2;"><?= htmlspecialchars($msg_error) ?></p>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <label style="display:block; margin-bottom: 5px; font-weight: bold;">Detalles del reporte:</label>
            <textarea name="descripcion" rows="6" style="width: 100%; padding: 10px; box-sizing: border-box;" placeholder="Explica lo sucedido de la forma mas descriptiva posible..." required minlength="15" maxlength="1500"></textarea>

            <button type="submit" style="background-color: #dc3545; color: white; padding: 10px 20px; border: none; font-size: 1em; cursor: pointer; border-radius: 4px; margin-top: 15px;">Enviar reporte</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
