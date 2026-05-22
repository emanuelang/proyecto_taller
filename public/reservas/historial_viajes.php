<?php
session_start();
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$usuario_id = $_SESSION['user_id'];

// Encontrar el ID de Pasajero
$stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
$stmt_pasajero->execute([$usuario_id]);
$pasajero = $stmt_pasajero->fetch();

if (!$pasajero) {
    die("Error: No estás registrado como pasajero.");
}
$pasajero_id = $pasajero['ID_pasajero'];

$sql = "SELECT 
            r.ID_reserva AS reserva_id,
            p.ID_publicacion AS publicacion_id,
            p.HoraSalida AS fecha,
            p.Precio AS precio,
            u.Nombre AS conductor_nombre,
            c.ID_conductor AS conductor_id,
            p.CiudadOrigen AS origen_nombre,
            p.CiudadDestino AS destino_nombre,
            (SELECT cal.Puntuacion FROM Calificaciones cal WHERE cal.ID_reserva = r.ID_reserva AND cal.ID_pasajero = ?) AS mi_puntuacion
        FROM Reservas r
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
        JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
        JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
        WHERE pr.ID_pasajero = ? AND r.Estado = 'Completada' AND p.HoraSalida < NOW()
        ORDER BY p.HoraSalida DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$pasajero_id, $pasajero_id]);
$reservas = $stmt->fetchAll();

// Función para obtener compañeros.
function getCompaneros($pdo, $publicacion_id, $mi_usuario_id) {
    $sql = "SELECT u.Nombre 
            FROM Usuarios u 
            JOIN Pasajeros pas ON u.ID_usuario = pas.ID_usuario 
            JOIN PasajerosReservas pr ON pas.ID_pasajero = pr.ID_pasajero 
            JOIN Reservas r ON pr.ID_reserva = r.ID_reserva 
            WHERE r.ID_publicacion = ? AND r.Estado='Completada' AND pas.ID_usuario != ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$publicacion_id, $mi_usuario_id]);
    $nombres = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $nombres;
}
?>

<?php require_once __DIR__ . '/../header.php'; ?>

<div class="page-shell">
    <h1 class="page-title">Mis reservas</h1>
    <p class="page-subtitle">Consultá tus viajes finalizados y calificá conductores</p>

    <div class="tabs">
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="tab">Activas</a>
        <span class="tab active">Historial</span>
    </div>

    <?php if (count($reservas) > 0): ?>
        <div class="reservation-list">
        <?php foreach ($reservas as $r): ?>
            <?php $companeros = getCompaneros($pdo, $r['publicacion_id'], $usuario_id); ?>
            <article class="card reservation-card">
                <div class="reservation-head">
                    <div>
                        <h2 class="reservation-title"><?= htmlspecialchars($r['origen_nombre']) ?> <span style="color:var(--primary);">→</span> <?= htmlspecialchars($r['destino_nombre']) ?></h2>
                        <div class="trip-meta">
                            <span>▣ <?= date('d M Y', strtotime($r['fecha'])) ?></span>
                            <span>◷ <?= date('H:i', strtotime($r['fecha'])) ?> hs</span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge-primary">Finalizado</span>
                        <div class="trip-price" style="margin-top:12px;">$<?= number_format($r['precio'], 0, ',', '.') ?></div>
                    </div>
                </div>

                <div class="driver-chip" style="margin:20px 0;">
                    <span class="mini-avatar"><?= htmlspecialchars(strtoupper(substr($r['conductor_nombre'], 0, 1))) ?></span>
                    <div>
                        <strong><a href="<?= BASE_URL ?>perfil.php?id=<?= $r['conductor_id'] ?>"><?= htmlspecialchars($r['conductor_nombre']) ?></a></strong>
                        <div class="text-muted" style="font-size:14px;">
                            <?php if (count($companeros) > 0): ?>
                                Compañeros: <?= htmlspecialchars(implode(', ', $companeros)) ?>
                            <?php else: ?>
                                Viajaste solo con el conductor.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="reservation-actions">
                    <div>
                        <strong style="display:block; color:var(--text-muted); margin-bottom:8px;">Calificación del conductor</strong>
                        <?php if ($r['mi_puntuacion']): ?>
                            <div class="star-static">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="<?= $i <= $r['mi_puntuacion'] ? 'star-rated' : 'star-unrated' ?>">★</span>
                                <?php endfor; ?>
                                <small class="text-muted">Ya calificaste este viaje</small>
                            </div>
                        <?php else: ?>
                            <div class="star-rating" id="rating-<?= $r['reserva_id'] ?>">
                                <input type="radio" id="star5-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="5" onchange="rate(<?= $r['reserva_id'] ?>, 5)">
                                <label for="star5-<?= $r['reserva_id'] ?>" title="Excelente">★</label>
                                <input type="radio" id="star4-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="4" onchange="rate(<?= $r['reserva_id'] ?>, 4)">
                                <label for="star4-<?= $r['reserva_id'] ?>" title="Muy bueno">★</label>
                                <input type="radio" id="star3-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="3" onchange="rate(<?= $r['reserva_id'] ?>, 3)">
                                <label for="star3-<?= $r['reserva_id'] ?>" title="Bueno">★</label>
                                <input type="radio" id="star2-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="2" onchange="rate(<?= $r['reserva_id'] ?>, 2)">
                                <label for="star2-<?= $r['reserva_id'] ?>" title="Malo">★</label>
                                <input type="radio" id="star1-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="1" onchange="rate(<?= $r['reserva_id'] ?>, 1)">
                                <label for="star1-<?= $r['reserva_id'] ?>" title="Terrible">★</label>
                            </div>
                            <div id="msg-<?= $r['reserva_id'] ?>" class="text-muted" style="font-size:14px; margin-top:5px;"></div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= BASE_URL ?>reportar.php?conductor_id=<?= $r['conductor_id'] ?>" class="btn btn-danger">Reportar</a>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align:center; padding:54px;">
            <div style="font-size:52px; margin-bottom:16px;">▣</div>
            <h2 style="margin:0 0 12px;">Aún no tenés viajes pasados</h2>
            <p class="text-muted">Cuando un viaje reservado pase su fecha de salida, aparecerá acá.</p>
            <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="btn btn-outline" style="margin-top:12px;">Volver a reservas activas</a>
        </div>
    <?php endif; ?>
</div>

<script>
function rate(reservaId, puntuacion) {
    const msgDiv = document.getElementById('msg-' + reservaId);
    msgDiv.style.color = '#64748b';
    msgDiv.innerText = 'Enviando...';

    const inputs = document.querySelectorAll(`input[name="rating-${reservaId}"]`);
    inputs.forEach(i => i.disabled = true);

    fetch('calificar_conductor.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || ''
        },
        body: JSON.stringify({
            reserva_id: reservaId,
            puntuacion: puntuacion,
            csrf_token: window.CSRF_TOKEN || ''
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msgDiv.style.color = 'var(--success)';
            msgDiv.innerText = 'Gracias por tu calificación.';
            const container = document.getElementById('rating-' + reservaId);
            let html = '';
            for (let i = 1; i <= 5; i++) {
                const cls = i <= puntuacion ? 'star-rated' : 'star-unrated';
                html += `<span class="${cls}">★</span>`;
            }
            container.className = 'star-static';
            container.innerHTML = html;
        } else {
            msgDiv.style.color = '#ef4444';
            msgDiv.innerText = 'Error: ' + data.message;
            inputs.forEach(i => i.disabled = false);
        }
    })
    .catch(() => {
        msgDiv.style.color = '#ef4444';
        msgDiv.innerText = 'Error de conexión';
        inputs.forEach(i => i.disabled = false);
    });
}
</script>

</body>
</html>
