<?php require_once __DIR__ . '/../core/security.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MOVEON</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= time() ?>">
    <script>
        window.BASE_URL = <?= json_encode(BASE_URL) ?>;
        window.SESSION_TIMEOUT_MS = <?= isset($_SESSION['user_id']) ? (int)SESSION_TIMEOUT_SECONDS * 1000 : 0 ?>;
        window.CSRF_TOKEN = <?= json_encode(function_exists('csrf_token') ? csrf_token() : '') ?>;
    </script>
    <script src="<?= BASE_URL ?>main.js?v=<?= time() ?>" defer></script>
</head>
<?php if (!isset($_SESSION['user_id'])): ?>
<body class="guest-body">
    <div class="nav-menu">
        <div class="guest-brand">
            <img src="<?= BASE_URL ?>assets/moveon-logo.svg" alt="MOVEON">
            <h1>MOVEON</h1>
        </div>
        <div style="margin-left:auto; display:flex; gap:10px; align-items:center;">
            <a href="<?= BASE_URL ?>login.php" class="btn">Iniciar sesión</a>
            <a href="<?= BASE_URL ?>registro_usuario.php" class="btn btn-outline">Registrarse</a>
        </div>
    </div>
<?php else: ?>
<?php
$unread_count = 0;
$notificaciones = [];

if (isset($pdo)) {
    $stmt_res = $pdo->prepare("
        SELECT r.ID_reserva, p.HoraSalida, p.CiudadOrigen, p.CiudadDestino
        FROM Reservas r
        JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
        WHERE pas.ID_usuario = ? AND r.Estado NOT IN ('Cancelada', 'Rechazada')
    ");
    $stmt_res->execute([$_SESSION['user_id']]);
    $mis_viajes_notif = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

    foreach ($mis_viajes_notif as $v_notif) {
        $hs_restantes = (strtotime($v_notif['HoraSalida']) - time()) / 3600;
        if ($hs_restantes > 0 && $hs_restantes <= 24) {
            $msg_notif = "Recordatorio: Tu viaje de {$v_notif['CiudadOrigen']} a {$v_notif['CiudadDestino']} sale en menos de 24 horas.";
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Notificaciones WHERE ID_usuario = ? AND Mensaje = ?");
            $stmt_check->execute([$_SESSION['user_id'], $msg_notif]);
            if ($stmt_check->fetchColumn() == 0) {
                $stmt_ins = $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)");
                $stmt_ins->execute([$_SESSION['user_id'], $msg_notif]);
            }
        }
    }

    $stmt_notif = $pdo->prepare("SELECT * FROM Notificaciones WHERE ID_usuario = ? ORDER BY Fecha DESC LIMIT 20");
    $stmt_notif->execute([$_SESSION['user_id']]);
    $notificaciones = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
    foreach ($notificaciones as $n) {
        if (!$n['Leida']) $unread_count++;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_path = str_replace('\\', '/', $_SERVER['PHP_SELF']);
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$inicial = strtoupper(substr($nombre_usuario, 0, 1));
$es_conductor = !empty($_SESSION['is_conductor']);
$es_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$foto_usuario = '';
if (isset($pdo, $_SESSION['user_id'])) {
    try {
        $stmt_foto_header = $pdo->prepare("SELECT FotoPerfil FROM Usuarios WHERE ID_usuario = ?");
        $stmt_foto_header->execute([$_SESSION['user_id']]);
        $foto_usuario = (string)($stmt_foto_header->fetchColumn() ?: '');
    } catch (Exception $e) {
        $foto_usuario = '';
    }
}
$admin_pending_count = 0;
if ($es_admin && isset($pdo)) {
    try {
        $admin_pending_count += (int)$pdo->query("SELECT COUNT(*) FROM Conductores WHERE Estado = 'Esperando'")->fetchColumn();
        $admin_pending_count += (int)$pdo->query("SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Pendiente'")->fetchColumn();
        $admin_pending_count += (int)$pdo->query("SELECT COUNT(*) FROM Soporte WHERE Estado = 'Pendiente'")->fetchColumn();
    } catch (Exception $e) {
        $admin_pending_count = 0;
    }
}
?>
<body class="app-body">
    <button id="sidebarOpen" class="sidebar-main-toggle" type="button" aria-label="Abrir menú">☰</button>
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <aside id="sidebarMenu" class="app-sidebar">
        <div class="brand-block">
            <img src="<?= BASE_URL ?>assets/moveon-logo.svg" alt="MOVEON" class="brand-logo">
            <span class="brand-name">MOVEON</span>
        </div>

        <div class="sidebar-user">
            <?php if ($foto_usuario !== ''): ?>
                <img src="<?= htmlspecialchars($foto_usuario) ?>" class="avatar avatar-img" alt="Foto de perfil">
            <?php else: ?>
                <span class="avatar"><?= htmlspecialchars($inicial) ?></span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>perfil.php" class="sidebar-user-link" title="Ver mi perfil">
                <small>Bienvenido</small>
                <strong><?= htmlspecialchars($nombre_usuario) ?></strong>
            </a>
            <button id="notifOpen" class="sidebar-notif" type="button" aria-label="Abrir notificaciones">
                🔔
                <?php if ($unread_count > 0): ?>
                    <span id="notifBadge" class="notif-dot"></span>
                <?php endif; ?>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="<?= BASE_URL ?>index.php" class="sidebar-link <?= $current_page == 'index.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">⌕</span> Buscar viajes
            </a>
            <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="sidebar-link <?= $current_page == 'mis_reservas.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">▣</span> Mis reservas
            </a>

            <?php if (!$es_conductor): ?>
                <a href="<?= BASE_URL ?>registro_conductor.php" class="sidebar-link <?= $current_page == 'registro_conductor.php' ? 'active' : '' ?>">
                    <span class="sidebar-icon">+</span> Ser conductor
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>conductor/dashboard.php" class="sidebar-link <?= (strpos($current_path, '/conductor/') !== false || $current_page === 'crear_viaje.php') ? 'active' : '' ?>">
                    <span class="sidebar-icon">⚙</span> Panel conductor
                </a>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>perfil.php" class="sidebar-link <?= $current_page == 'perfil.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">◉</span> Mi perfil
            </a>
            <a href="<?= BASE_URL ?>manual.php" class="sidebar-link <?= $current_page == 'manual.php' ? 'active' : '' ?>">
                <span class="sidebar-icon">?</span> Manual de Ayuda
            </a>

            <?php if ($es_admin): ?>
                <div class="sidebar-separator"></div>
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="sidebar-link <?= strpos($current_path, '/admin/') !== false ? 'active' : '' ?>">
                    <span class="sidebar-icon">◇</span> Panel de admin
                    <?php if ($admin_pending_count > 0): ?>
                        <em class="sidebar-count"><?= $admin_pending_count ?></em>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </nav>

        <a href="<?= BASE_URL ?>logout.php" class="sidebar-link sidebar-logout">
            <span class="sidebar-icon">↪</span> Salir de tu cuenta
        </a>
    </aside>

    <aside id="notifMenu" class="notif-drawer" aria-label="Notificaciones">
        <div class="notif-header">
            <h3 style="margin:0;">Notificaciones</h3>
            <button id="notifClose" class="btn btn-outline" type="button" aria-label="Cerrar notificaciones">×</button>
        </div>
        <div class="notif-list">
            <?php if (empty($notificaciones)): ?>
                <p class="text-muted" style="text-align:center;">No tenés notificaciones.</p>
            <?php else: ?>
                <?php foreach ($notificaciones as $n): ?>
                    <div class="notif-item <?= !$n['Leida'] ? 'unread' : '' ?>">
                        <small class="text-muted"><?= date('d/m H:i', strtotime($n['Fecha'])) ?></small><br>
                        <?= htmlspecialchars($n['Mensaje']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <main class="app-main">
<?php endif; ?>
