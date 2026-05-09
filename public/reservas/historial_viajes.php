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
            (SELECT Puntuacion FROM Calificaciones WHERE ID_reserva = r.ID_reserva AND ID_pasajero = ?) AS mi_puntuacion
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

// Función para obtener compañeros
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

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Historial de Viajes</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .star-rating {
            display: inline-flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            font-size: 2em;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            color: #cbd5e1;
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating :checked ~ label {
            color: #fbbf24;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #fbbf24;
        }
        .star-rated {
            color: #fbbf24;
            font-size: 2em;
        }
        .star-unrated {
            color: #cbd5e1;
            font-size: 2em;
        }
    </style>
</head>
<body style="background-color: #f8fafc;">

<div class="nav-menu">
    <h2>Historial de Viajes</h2>
    <a href="<?= BASE_URL ?>reservas/mis_reservas.php" style="margin-left: auto;">← Volver a Mis Reservas</a>
</div>

<?php if (count($reservas) > 0): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; max-width: 1200px; margin: 20px auto; padding: 0 20px;">
    <?php foreach ($reservas as $r): ?>
        <?php $companeros = getCompaneros($pdo, $r['publicacion_id'], $usuario_id); ?>
        <div class="card" style="margin-bottom: 0;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                <h3 style="margin: 0; color: var(--primary);">
                    <?= htmlspecialchars($r['origen_nombre']) ?> → <?= htmlspecialchars($r['destino_nombre']) ?>
                </h3>
                <span class="badge badge-success" style="font-size: 1.1em;">$<?= number_format($r['precio'], 2) ?></span>
            </div>
            
            <p style="margin: 5px 0;"><strong>📅 Fecha del viaje:</strong> <?= date('d/m/Y H:i', strtotime($r['fecha'])) ?> hs</p>
            <p style="margin: 5px 0;"><strong>👤 Conductor:</strong> <a href="<?= BASE_URL ?>perfil.php?id=<?= $r['conductor_id'] ?>" style="color: var(--primary); text-decoration: none;"><?= htmlspecialchars($r['conductor_nombre']) ?></a></p>
            
            <div style="margin: 15px 0; background-color: #f1f5f9; padding: 10px; border-radius: 6px;">
                <strong style="color: #475569; font-size: 0.9em;">👥 Compañeros de Viaje:</strong>
                <div style="color: #334155; margin-top: 5px;">
                    <?php if (count($companeros) > 0): ?>
                        <?= htmlspecialchars(implode(', ', $companeros)) ?>
                    <?php else: ?>
                        <span style="font-style: italic; color: #94a3b8;">Viajaste solo con el conductor.</span>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px; text-align: center;">
                <strong style="display: block; color: #475569; margin-bottom: 5px;">¿Cómo calificarías al conductor?</strong>
                
                <?php if ($r['mi_puntuacion']): ?>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="<?= $i <= $r['mi_puntuacion'] ? 'star-rated' : 'star-unrated' ?>">★</span>
                        <?php endfor; ?>
                        <div style="color: var(--success); font-weight: bold; font-size: 0.9em; margin-top: 5px;">✅ Ya calificaste este viaje</div>
                    </div>
                <?php else: ?>
                    <div class="star-rating" id="rating-<?= $r['reserva_id'] ?>">
                        <input type="radio" id="star5-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="5" onchange="rate(<?= $r['reserva_id'] ?>, 5)" />
                        <label for="star5-<?= $r['reserva_id'] ?>" title="Excelente">★</label>
                        <input type="radio" id="star4-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="4" onchange="rate(<?= $r['reserva_id'] ?>, 4)" />
                        <label for="star4-<?= $r['reserva_id'] ?>" title="Muy bueno">★</label>
                        <input type="radio" id="star3-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="3" onchange="rate(<?= $r['reserva_id'] ?>, 3)" />
                        <label for="star3-<?= $r['reserva_id'] ?>" title="Bueno">★</label>
                        <input type="radio" id="star2-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="2" onchange="rate(<?= $r['reserva_id'] ?>, 2)" />
                        <label for="star2-<?= $r['reserva_id'] ?>" title="Malo">★</label>
                        <input type="radio" id="star1-<?= $r['reserva_id'] ?>" name="rating-<?= $r['reserva_id'] ?>" value="1" onchange="rate(<?= $r['reserva_id'] ?>, 1)" />
                        <label for="star1-<?= $r['reserva_id'] ?>" title="Terrible">★</label>
                    </div>
                    <div id="msg-<?= $r['reserva_id'] ?>" style="font-size: 0.9em; margin-top: 5px;"></div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px; text-align: center;">
                <a href="<?= BASE_URL ?>reportar.php?conductor_id=<?= $r['conductor_id'] ?>" style="color: #ef4444; text-decoration: none; font-size: 0.9em; padding: 8px; border: 1px dashed #fca5a5; border-radius: 4px; display: inline-block; transition: background-color 0.2s;">
                    ⚠️ Reportar un problema con este viaje
                </a>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <div style="text-align: center; color: #64748b; padding: 60px 20px;">
        <div style="font-size: 4em; margin-bottom: 20px;">🛣️</div>
        <h3 style="font-size: 1.5em; color: #334155;">Aún no tienes viajes pasados</h3>
        <p>Cuando un viaje en el que hayas reservado pase su fecha de salida, aparecerá aquí.</p>
    </div>
<?php endif; ?>

<script>
function rate(reservaId, puntuacion) {
    const msgDiv = document.getElementById('msg-' + reservaId);
    msgDiv.style.color = '#64748b';
    msgDiv.innerText = 'Enviando...';
    
    // Deshabilitar inputs
    const inputs = document.querySelectorAll(`input[name="rating-${reservaId}"]`);
    inputs.forEach(i => i.disabled = true);

    fetch('calificar_conductor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ reserva_id: reservaId, puntuacion: puntuacion })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msgDiv.style.color = 'var(--success)';
            msgDiv.innerText = '✅ ¡Gracias por tu calificación!';
            
            // Congelar visualmente las estrellas cambiando las clases a permanente
            const container = document.getElementById('rating-' + reservaId);
            let html = '';
            for (let i = 1; i <= 5; i++) {
                let cls = i <= puntuacion ? 'star-rated' : 'star-unrated';
                html += `<span class="${cls}">★</span>`;
            }
            container.innerHTML = html;
            // Quitamos el flex-direction reverse para el HTML estático
            container.style.flexDirection = 'row';
            container.style.justifyContent = 'center';
            container.style.display = 'block';
        } else {
            msgDiv.style.color = '#ef4444';
            msgDiv.innerText = '❌ Error: ' + data.message;
            inputs.forEach(i => i.disabled = false);
        }
    })
    .catch(err => {
        msgDiv.style.color = '#ef4444';
        msgDiv.innerText = '❌ Error de conexión';
        inputs.forEach(i => i.disabled = false);
    });
}
</script>

</body>
</html>
