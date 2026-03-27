<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$conductor_id = $_GET['conductor_id'] ?? null;
if (!$conductor_id) {
    die("Conductor no especificado.");
}

// Verify conductor exists and fetch name
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $descripcion = trim($_POST['descripcion']);
    
    if (!empty($descripcion)) {
        $stmt = $pdo->prepare("INSERT INTO Reportes (Hora, Fecha, Descripcion, ID_conductor) VALUES (CURTIME(), CURDATE(), ?, ?)");
        $stmt->execute([$descripcion, $conductor_id]);
        
        $msg_exito = "Gracias, tu queja ha sido enviada de forma anónima y será revisada por nuestros administradores.";
    } else {
        $msg_error = "Por favor, ingresa los detalles de tu queja.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reportar Queja - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<div class="nav-menu">
    <h2>Reportar un Problema</h2>
    <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Volver al inicio</a>
</div>

<div style="max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background: #fafafa;">
    <h3 style="color: #dc3545; margin-top: 0;">Reportar al Conductor: <?= htmlspecialchars($conductor['Nombre'] . ' ' . $conductor['Apellido']) ?></h3>
    
    <p>Si tuviste un problema con este conductor (llegada tarde, viaje inseguro, comportamientos indebidos), infórmanos a continuación. <strong>El reporte es completamente anónimo.</strong></p>

    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= $msg_exito ?></p>
        <p><a href="<?= BASE_URL ?>index.php" class="btn">Volver al Inicio</a></p>
    <?php else: ?>
        <?php if (isset($msg_error)): ?>
            <p style="color: red; font-weight: bold; background: #ffebee; padding: 10px; border: 1px solid #ffcdd2;"><?= $msg_error ?></p>
        <?php endif; ?>

        <form method="post">
            <label style="display:block; margin-bottom: 5px; font-weight: bold;">Detalles de la Queja:</label>
            <textarea name="descripcion" rows="6" style="width: 100%; padding: 10px; box-sizing: border-box;" placeholder="Explica lo sucedido de la forma más descriptiva posible..." required></textarea>
            
            <button type="submit" style="background-color: #dc3545; color: white; padding: 10px 20px; border: none; font-size: 1em; cursor: pointer; border-radius: 4px; margin-top: 15px;">Enviar Queja de Forma Anónima</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
