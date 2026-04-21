<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<h1>Carpooling</h1>

<div class="nav-menu">
<?php if (!isset($_SESSION['user_id'])): ?>
    <a href="<?= BASE_URL ?>login.php" class="btn">Iniciar sesión</a>
    <a href="<?= BASE_URL ?>registro_usuario.php">Registrarse</a>
<?php else: ?>
    <?php
    $unread_count = 0;
    $notificaciones = [];
    if (isset($_SESSION['user_id'])) {
        // 1. Chequear reservaciones próximas a 24 hs
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
                // Verificar si ya le notificamos sobre esto
                $msg_notif = "Recordatorio: Tu viaje de {$v_notif['CiudadOrigen']} a {$v_notif['CiudadDestino']} sale en menos de 24 horas.";
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM Notificaciones WHERE ID_usuario = ? AND Mensaje = ?");
                $stmt_check->execute([$_SESSION['user_id'], $msg_notif]);
                if ($stmt_check->fetchColumn() == 0) {
                    $stmt_ins = $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)");
                    $stmt_ins->execute([$_SESSION['user_id'], $msg_notif]);
                }
            }
        }

        // 2. Traer las notificaciones
        $stmt_notif = $pdo->prepare("SELECT * FROM Notificaciones WHERE ID_usuario = ? ORDER BY Fecha DESC LIMIT 20");
        $stmt_notif->execute([$_SESSION['user_id']]);
        $notificaciones = $stmt_notif->fetchAll(PDO::FETCH_ASSOC);
        foreach($notificaciones as $n) {
            if (!$n['Leida']) $unread_count++;
        }
    }
    ?>
    <div style="display: flex; align-items: center; margin-left: auto;">
        <!-- Botón para abrir sidebar notificaciones -->
        <button id="notifOpen" style="background:none; border:none; cursor:pointer; font-size:1.5em; position:relative; margin-right: 15px;">
            <span style="filter: grayscale(<?= $unread_count > 0 ? '0' : '100%' ?>);">🔔</span>
            <?php if ($unread_count > 0): ?>
                <span id="notifBadge" style="position:absolute; top:-5px; right:-10px; background:red; color:white; font-size:12px; font-weight:bold; padding:2px 5px; border-radius:50%;">!</span>
            <?php endif; ?>
        </button>

        <span>Hola <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></span>
    </div>

    <!-- Botón flotante para abrir sidebar izquierdo -->
    <button id="sidebarOpen" class="sidebar-main-toggle">&#9776;</button>

    
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Sidebar Menu -->
    <div id="sidebarMenu" class="sidebar">
        <div class="sidebar-header" style="text-align: right; padding-right: 15px; padding-bottom: 10px;">
            <button id="sidebarClose" style="background: none; border: none; color: var(--text-main); font-size: 28px; padding: 0; cursor: pointer; box-shadow: none;">&times;</button>
        </div>
        
        <a href="<?= BASE_URL ?>perfil.php" class="sidebar-link">Perfil</a>
        <div class="sidebar-separator"></div>
        
        <a href="<?= BASE_URL ?>index.php" class="sidebar-link">Ver viajes</a>
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="sidebar-link">Mis reservas</a>

        <?php if (!$_SESSION['is_conductor']): ?>
            <a href="<?= BASE_URL ?>registro_conductor.php" class="sidebar-link">Convertirme en conductor</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>conductor/dashboard.php" class="sidebar-link">Panel conductor</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>manual.php" class="sidebar-link">Manual de Ayuda</a>

        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="sidebar-link">Panel de admin</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>logout.php" class="sidebar-link sidebar-logout">Salir</a>
    </div>

    <!-- Notif Sidebar Menu -->
    <div id="notifMenu" class="sidebar" style="right: 0; left: auto; transform: translateX(100%);">
        <div class="sidebar-header" style="justify-content: space-between;">
            <h3 style="margin: 0; color: white;">Notificaciones</h3>
            <button id="notifClose" class="sidebar-toggle" style="color: white; font-size: 1.5em;">&times;</button>
        </div>
        <div style="padding: 15px; overflow-y: auto; height: calc(100vh - 60px);">
            <?php if(empty($notificaciones)): ?>
                <p style="color: #cbd5e1; text-align: center;">No tienes notificaciones.</p>
            <?php else: ?>
                <?php foreach($notificaciones as $n): ?>
                    <div style="background-color: <?= $n['Leida'] ? '#f1f5f9' : '#e2e8f0' ?>; padding: 10px; border-radius: 5px; margin-bottom: 10px; color: #334155; font-size: 0.9em; border-left: 4px solid <?= $n['Leida'] ? '#cbd5e1' : '#3b82f6' ?>;">
                        <span style="font-size: 0.8em; color: #64748b;"><?= date('d/m H:i', strtotime($n['Fecha'])) ?></span><br>
                        <?= htmlspecialchars($n['Mensaje']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebarMenu');
            const notifSidebar = document.getElementById('notifMenu');
            const overlay = document.getElementById('sidebarOverlay');
            const btnOpen = document.getElementById('sidebarOpen');
            const btnClose = document.getElementById('sidebarClose');
            const btnNotifOpen = document.getElementById('notifOpen');
            const btnNotifClose = document.getElementById('notifClose');

            function updateSidebarState(isOpen) {
                if (isOpen) {
                    sidebar.classList.add('active');
                    sidebar.style.transform = 'translateX(0)';
                    overlay.style.display = 'block';
                    if (btnOpen) btnOpen.style.display = 'none';
                    setTimeout(() => overlay.style.opacity = '1', 10);
                    localStorage.setItem('sidebar_open', 'true');
                } else {
                    sidebar.classList.remove('active');
                    sidebar.style.transform = 'translateX(-100%)';
                    overlay.style.opacity = '0';
                    if (btnOpen) btnOpen.style.display = 'flex';
                    setTimeout(() => overlay.style.display = 'none', 300);
                    localStorage.setItem('sidebar_open', 'false');
                }
            }

            function updateNotifState(isOpen) {
                if (isOpen) {
                    notifSidebar.style.transform = 'translateX(0)';
                    overlay.style.display = 'block';
                    setTimeout(() => overlay.style.opacity = '1', 10);
                    
                    // Mark as read via AJAX
                    fetch('<?= BASE_URL ?>notificaciones_api.php', { method: 'POST' })
                    .then(() => {
                        let badge = document.getElementById('notifBadge');
                        if(badge) badge.style.display = 'none';
                        let bell = btnNotifOpen.querySelector('span');
                        if (bell) bell.style.filter = 'grayscale(100%)';
                    });
                } else {
                    notifSidebar.style.transform = 'translateX(100%)';
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            }

            const isSidebarOpen = localStorage.getItem('sidebar_open') === 'true';
            updateSidebarState(isSidebarOpen);

            function toggleSidebar() {
                const isCurrentlyOpen = sidebar.classList.contains('active');
                updateSidebarState(!isCurrentlyOpen);
                if (notifSidebar.style.transform === 'translateX(0px)' || notifSidebar.style.transform === 'translateX(0)') {
                    updateNotifState(false);
                }
            }
            
            function toggleNotif() {
                const isCurrentlyOpen = notifSidebar.style.transform === 'translateX(0px)' || notifSidebar.style.transform === 'translateX(0)';
                updateNotifState(!isCurrentlyOpen);
                if (sidebar.classList.contains('active')) {
                    updateSidebarState(false);
                }
            }

            if(btnOpen) btnOpen.addEventListener('click', toggleSidebar);
            if(btnClose) btnClose.addEventListener('click', toggleSidebar);
            if(btnNotifOpen) btnNotifOpen.addEventListener('click', toggleNotif);
            if(btnNotifClose) btnNotifClose.addEventListener('click', toggleNotif);
            
            if(overlay) {
                overlay.addEventListener('click', () => {
                    updateSidebarState(false);
                    updateNotifState(false);
                });
            }
        });
    </script>
<?php endif; ?>
</div>

<hr>
