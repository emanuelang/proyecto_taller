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
<?php require_once __DIR__ . '/../header.php'; ?>

    <style>
        .star-select {
            width: 100%;
            padding: 12px;
            font-size: 1.1em;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
    </style>

    <div class="nav-menu">
        <h2>Calificar a <?= htmlspecialchars($reserva['conductor_nombre']) ?></h2>
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" style="margin-left: auto;">← Cancelar y volver</a>
    </div>
    
    <?php if (isset($error)): ?>
        <p style="color:red; text-align: center; font-weight: bold;"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <h3 style="margin-top:0; color:var(--primary);">Tu Opinión Cuenta</h3>
        <p style="color: #64748b; margin-bottom: 20px;">
            Ayudá a la comunidad calificando cómo estuvo el viaje.
        </p>

        <label>Del 1 al 5, ¿cómo calificarías tu experiencia?</label>
        <select name="puntaje" required class="star-select">
            <option value="5">⭐⭐⭐⭐⭐ Excelente</option>
            <option value="4">⭐⭐⭐⭐ Muy bueno</option>
            <option value="3">⭐⭐⭐ Bueno / Regular</option>
            <option value="2">⭐⭐ Malo</option>
            <option value="1">⭐ Pésimo</option>
        </select>

        <label>Dejá una crítica/comentario (Opcional):</label>
        <textarea name="comentario" rows="5" placeholder="¿Cómo estuvo el viaje, el conductor, el vehículo...?"></textarea>

        <button type="submit" class="success-bg" style="width: 100%; margin-top: 15px; font-size: 1.1em;">Enviar Calificación</button>
    </form>
</body>
</html>
