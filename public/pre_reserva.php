<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

require_active_session($pdo);

$viaje_id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($viaje_id <= 0) {
    safe_error('Viaje no especificado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
}

$stmt_viaje = $pdo->prepare("
    SELECT p.ID_publicacion, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida, p.Precio,
           c.ID_usuario AS conductor_usuario_id,
           v.CantidadAsientos AS asientos,
           (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') AS ocupados
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
    JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    WHERE p.ID_publicacion = ?
      AND p.Estado = 'Activa'
      AND p.HoraSalida >= NOW()
      AND c.Estado = 'Aceptada'
      AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW())
      AND u_cond.estado = 'activo'
      AND (u_cond.BaneadoHasta IS NULL OR u_cond.BaneadoHasta <= NOW())
      AND v.Estado = 'Aceptado'
");
$stmt_viaje->execute([$viaje_id]);
$viaje = $stmt_viaje->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    safe_error('El viaje no existe o ya no esta disponible.');
}

if ((int)$viaje['conductor_usuario_id'] === (int)$_SESSION['user_id']) {
    safe_error('No podes reservar tu propio viaje.');
}

if ((int)$viaje['ocupados'] >= (int)$viaje['asientos']) {
    safe_error('No hay asientos disponibles en este viaje.');
}

$stmt_user = $pdo->prepare('SELECT Nombre, Apellido, DNI, Correo, Telefono FROM Usuarios WHERE ID_usuario = ?');
$stmt_user->execute([$_SESSION['user_id']]);
$usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    safe_error('No se pudo cargar tu usuario.');
}

$vista = $_GET['tipo'] ?? 'propio';
$vista = $vista === 'tercero' ? 'tercero' : 'propio';

require_once __DIR__ . '/header.php';
?>

<div class="page-shell">
    <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start; flex-wrap:wrap; margin-bottom:24px;">
        <div>
            <h1 class="page-title">Datos del pasajero</h1>
            <p class="page-subtitle"><?= htmlspecialchars($viaje['CiudadOrigen']) ?> a <?= htmlspecialchars($viaje['CiudadDestino']) ?> · $<?= number_format((float)$viaje['Precio'], 0, ',', '.') ?></p>
        </div>
        <a href="<?= BASE_URL ?>detalle_viaje.php?id=<?= $viaje_id ?>" class="btn btn-outline">Volver al detalle</a>
    </div>

    <div class="tabs">
        <a class="tab <?= $vista === 'propio' ? 'active' : '' ?>" href="<?= BASE_URL ?>pre_reserva.php?id=<?= $viaje_id ?>&tipo=propio">Para mi</a>
        <a class="tab <?= $vista === 'tercero' ? 'active' : '' ?>" href="<?= BASE_URL ?>pre_reserva.php?id=<?= $viaje_id ?>&tipo=tercero">Para un tercero</a>
    </div>

    <section class="auth-card" style="max-width:760px;">
        <?php if ($vista === 'tercero'): ?>
            <h2 style="margin-top:0;">Pasaje para un tercero</h2>
            <p class="text-muted">Vos sos el usuario responsable de esta reserva. El conductor usara los datos del pasajero que cargues aca para verificar su identidad al abordar.</p>

            <form method="POST" action="<?= BASE_URL ?>reservar_viaje.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $viaje_id ?>">
                <input type="hidden" name="tipo_pasaje" value="tercero">

                <div class="auth-grid">
                    <div>
                        <label>Nombre del pasajero</label>
                        <input type="text" name="pasajero_nombre" placeholder="Ej: Juan" required minlength="2" maxlength="100" title="Solo letras y espacios">
                    </div>
                    <div>
                        <label>Apellido del pasajero</label>
                        <input type="text" name="pasajero_apellido" placeholder="Ej: Perez" required minlength="2" maxlength="100" title="Solo letras y espacios">
                    </div>
                    <div>
                        <label>DNI del pasajero</label>
                        <input type="text" name="pasajero_dni" placeholder="Ej: 12345678" required pattern="[0-9]{7,8}" minlength="7" maxlength="8">
                    </div>
                    <div>
                        <label>Telefono del pasajero</label>
                        <input type="tel" name="pasajero_telefono" placeholder="Ej: 3431234567" required pattern="[0-9]{8,15}" minlength="8" maxlength="15">
                    </div>
                    <div class="full">
                        <label>Correo del pasajero</label>
                        <input type="email" name="pasajero_correo" placeholder="opcional@email.com" maxlength="150">
                    </div>
                </div>

                <div class="info-tile" style="margin:8px 0 18px;">
                    <span>Responsable de la reserva</span>
                    <strong><?= htmlspecialchars(trim($usuario['Nombre'] . ' ' . $usuario['Apellido'])) ?> · DNI <?= htmlspecialchars($usuario['DNI']) ?> · <?= htmlspecialchars($usuario['Telefono']) ?></strong>
                </div>

                <button type="submit" class="success-bg">Continuar reserva</button>
            </form>
        <?php else: ?>
            <h2 style="margin-top:0;">Pasaje para mi</h2>
            <p class="text-muted">Vamos a usar los datos de tu cuenta para la verificacion del viaje. Estos datos no se pueden modificar desde este paso.</p>

            <form method="POST" action="<?= BASE_URL ?>reservar_viaje.php">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $viaje_id ?>">
                <input type="hidden" name="tipo_pasaje" value="propio">

                <div class="auth-grid">
                    <div>
                        <label>Nombre</label>
                        <input type="text" value="<?= htmlspecialchars($usuario['Nombre']) ?>" disabled>
                    </div>
                    <div>
                        <label>Apellido</label>
                        <input type="text" value="<?= htmlspecialchars($usuario['Apellido']) ?>" disabled>
                    </div>
                    <div>
                        <label>DNI</label>
                        <input type="text" value="<?= htmlspecialchars($usuario['DNI']) ?>" disabled>
                    </div>
                    <div>
                        <label>Telefono</label>
                        <input type="text" value="<?= htmlspecialchars($usuario['Telefono']) ?>" disabled>
                    </div>
                    <div class="full">
                        <label>Correo electronico</label>
                        <input type="text" value="<?= htmlspecialchars($usuario['Correo']) ?>" disabled>
                    </div>
                </div>

                <button type="submit" class="success-bg">Continuar reserva</button>
            </form>
        <?php endif; ?>
    </section>
</div>

</body>
</html>
