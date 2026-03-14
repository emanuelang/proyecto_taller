<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (!isset($_GET['reserva_id'])) {
    die("Reserva no especificada.");
}
$reserva_id = (int) $_GET['reserva_id'];
$usuario_id = $_SESSION['user_id'];

// Verificar que la reserva pertenece al usuario y que no está calificada ya
$sql = "
    SELECT r.*, v.conductor_id, v.fecha, u.nombre as conductor_nombre 
    FROM reservas r
    JOIN viajes v ON r.viaje_id = v.id
    JOIN conductores c ON v.conductor_id = c.id
    JOIN usuarios u ON c.usuario_id = u.id
    WHERE r.id = :reserva_id AND r.usuario_id = :usuario_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':reserva_id' => $reserva_id, ':usuario_id' => $usuario_id]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    die("Reserva inválida o no te pertenece.");
}

// Verificar que ya pasó la fecha del viaje
if (strtotime($reserva['fecha']) > time()) {
    die("Sólo podés calificar un viaje después de su fecha de inicio.");
}

// Verificar si ya qualificó
$sql_check = "SELECT COUNT(*) FROM calificaciones WHERE reserva_id = ?";
$stmt_check = $pdo->prepare($sql_check);
$stmt_check->execute([$reserva_id]);
if ($stmt_check->fetchColumn() > 0) {
    $_SESSION['mensaje_exito'] = "Ya has calificado este viaje.";
    header("Location: mis_reservas.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puntaje = (int) $_POST['puntaje'];
    $comentario = $_POST['comentario'] ?? '';

    if ($puntaje >= 1 && $puntaje <= 5) {
        $sql_insert = "
            INSERT INTO calificaciones (reserva_id, conductor_id, pasajero_id, puntaje, comentario, fecha)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        $stmt_i = $pdo->prepare($sql_insert);
        $stmt_i->execute([
            $reserva_id, 
            $reserva['conductor_id'], 
            $usuario_id, 
            $puntaje, 
            $comentario
        ]);

        $_SESSION['mensaje_exito'] = "¡Gracias por tu calificación!";
        header("Location: mis_reservas.php");
        exit;
    } else {
        $error = "Puntaje inválido. Debe ser entre 1 y 5.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Calificar Conductor - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <h1>Calificar a <?= htmlspecialchars($reserva['conductor_nombre']) ?></h1>
    <a href="<?= BASE_URL ?>reservas/mis_reservas.php">← Cancelar y volver</a>
    <hr>
    
    <?php if (isset($error)): ?>
        <p style="color:red;"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <label>Del 1 al 5, ¿cómo calificarías tu experiencia?</label><br>
        <select name="puntaje" required style="padding: 5px; font-size: 1.1em; margin: 10px 0;">
            <option value="5">⭐⭐⭐⭐⭐ Excelente</option>
            <option value="4">⭐⭐⭐⭐ Muy bueno</option>
            <option value="3">⭐⭐⭐ Bueno / Regular</option>
            <option value="2">⭐⭐ Malo</option>
            <option value="1">⭐ Pésimo</option>
        </select><br><br>

        <label>Dejá una crítica/comentario (Opcional):</label><br>
        <textarea name="comentario" rows="4" cols="50" placeholder="¿Cómo estuvo el viaje, el conductor, el vehículo...?"></textarea><br><br>

        <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">Enviar Calificación</button>
    </form>
</body>
</html>
