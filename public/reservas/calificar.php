<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (!isset($_GET['reserva_id'])) {
    die("Reserva no especificada.");
}
$reserva_id = (int) $_GET['reserva_id'];
$usuario_id = $_SESSION['user_id'];

// Verificar que la reserva pertenece al usuario y que no esta calificada ya.
$sql = "
    SELECT r.ID_reserva, r.ID_publicacion, p.HoraSalida, p.CiudadOrigen, p.CiudadDestino,
           cp.ID_conductor, u.Nombre as conductor_nombre, pr.ID_pasajero
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE r.ID_reserva = :reserva_id AND pas.ID_usuario = :usuario_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':reserva_id' => $reserva_id, ':usuario_id' => $usuario_id]);
$reserva = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reserva) {
    $_SESSION['mensaje_error'] = "La reserva no es valida para tu usuario. La notificacion fue actualizada para evitar enlaces incorrectos.";
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

if (strtotime($reserva['HoraSalida']) > time()) {
    $_SESSION['mensaje_error'] = "Solo podes calificar un viaje despues de su fecha de inicio.";
    header("Location: " . BASE_URL . "reservas/historial_viajes.php");
    exit;
}

$sql_check = "SELECT COUNT(*) FROM Calificaciones WHERE ID_reserva = ?";
$stmt_check = $pdo->prepare($sql_check);
$stmt_check->execute([$reserva_id]);
if ($stmt_check->fetchColumn() > 0) {
    $_SESSION['mensaje_exito'] = "Ya calificaste este viaje.";
    header("Location: historial_viajes.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $puntaje = (int) ($_POST['puntaje'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');

    if ($puntaje >= 1 && $puntaje <= 5) {
        $sql_insert = "
            INSERT INTO Calificaciones (ID_reserva, ID_conductor, ID_pasajero, Puntuacion, Comentario, Fecha)
            VALUES (?, ?, ?, ?, ?, NOW())
        ";
        $stmt_i = $pdo->prepare($sql_insert);
        $stmt_i->execute([
            $reserva_id,
            $reserva['ID_conductor'],
            $reserva['ID_pasajero'],
            $puntaje,
            $comentario
        ]);

        $stmt_confirm = $pdo->prepare("
            INSERT INTO ConfirmacionesViaje (ID_reserva, ID_usuario, ID_publicacion, ConfirmoLlegada)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                ConfirmoLlegada = VALUES(ConfirmoLlegada),
                FechaConfirmacion = CURRENT_TIMESTAMP
        ");
        $stmt_confirm->execute([$reserva_id, (int)$usuario_id, (int)$reserva['ID_publicacion']]);

        $fecha = date('d/m/Y H:i', strtotime($reserva['HoraSalida']));
        $mensaje = "Tu llegada al viaje de {$reserva['CiudadOrigen']} a {$reserva['CiudadDestino']} del {$fecha} ya fue confirmada y la calificacion ya fue enviada.";
        $confirmar_url = BASE_URL . 'reservas/confirmar_llegada.php?reserva_id=' . $reserva_id;
        $calificar_url = BASE_URL . 'reservas/calificar.php?reserva_id=' . $reserva_id;
        $report_url = BASE_URL . 'reportar.php?conductor_id=' . (int)$reserva['ID_conductor'] . '&publicacion_id=' . (int)$reserva['ID_publicacion'];
        $stmt_notif = $pdo->prepare("
            UPDATE Notificaciones
            SET Mensaje = ?, AccionURL = NULL, AccionLabel = NULL, AccionSecundariaURL = ?, AccionSecundariaLabel = ?, Leida = FALSE, Fecha = CURRENT_TIMESTAMP
            WHERE ID_usuario = ?
              AND AccionURL IN (?, ?)
        ");
        $stmt_notif->execute([$mensaje, $report_url, 'Reportar conductor', (int)$usuario_id, $confirmar_url, $calificar_url]);

        $_SESSION['mensaje_exito'] = "Gracias por tu calificacion!";
        header("Location: historial_viajes.php");
        exit;
    }

    $error = "Puntaje invalido. Debe ser entre 1 y 5.";
}
?>

<?php require_once __DIR__ . '/../header.php'; ?>

<div class="page-shell create-trip-page">
    <div class="create-trip-head">
        <div>
            <h1 class="page-title">Calificar a <?= htmlspecialchars($reserva['conductor_nombre']) ?></h1>
            <p class="page-subtitle">Ayuda a la comunidad contando como estuvo el viaje.</p>
        </div>
        <a href="<?= BASE_URL ?>reservas/historial_viajes.php" class="btn btn-outline">Cancelar y volver</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="create-trip-form">
        <?= csrf_field() ?>
        <section class="card create-trip-card">
            <div class="form-section-head">
                <span class="section-kicker">Tu opinion</span>
                <h2>Calificacion del conductor</h2>
            </div>

            <div class="driver-chip" style="margin-bottom:22px;">
                <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($reserva['conductor_nombre'], 0, 1))) ?></span>
                <div>
                    <strong><?= htmlspecialchars($reserva['conductor_nombre']) ?></strong>
                    <div class="text-muted" style="font-size:14px;">Selecciona de 1 a 5 estrellas.</div>
                </div>
            </div>

            <div class="field-group">
                <label>Del 1 al 5, como calificarias tu experiencia?</label>
                <div class="star-rating" style="display:flex; margin:6px 0 18px;" aria-label="Seleccionar calificacion">
                    <input type="radio" id="star5" name="puntaje" value="5" required>
                    <label for="star5" title="Excelente">★</label>
                    <input type="radio" id="star4" name="puntaje" value="4">
                    <label for="star4" title="Muy bueno">★</label>
                    <input type="radio" id="star3" name="puntaje" value="3">
                    <label for="star3" title="Bueno">★</label>
                    <input type="radio" id="star2" name="puntaje" value="2">
                    <label for="star2" title="Malo">★</label>
                    <input type="radio" id="star1" name="puntaje" value="1">
                    <label for="star1" title="Pesimo">★</label>
                </div>
            </div>

            <div class="field-group">
                <label>Deja una critica/comentario (opcional)</label>
                <textarea name="comentario" rows="5" placeholder="Como estuvo el viaje, el conductor, el vehiculo...?"></textarea>
            </div>

            <div class="create-trip-actions">
                <button type="submit" class="success-bg">Enviar calificacion</button>
            </div>
        </section>
    </form>
</div>

</main>
</body>
</html>
