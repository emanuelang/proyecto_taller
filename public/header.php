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
    <!-- Botón para abrir sidebar -->
    <button id="sidebarOpen" class="sidebar-toggle">&#9776;</button>
    <span>Hola <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></span>
    
    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>

    <!-- Sidebar Menu -->
    <div id="sidebarMenu" class="sidebar">
        <div class="sidebar-header">
            <!-- Arriba a la izquierda las 3 lineas para cerrar -->
            <button id="sidebarClose" class="sidebar-toggle">&#9776;</button>
        </div>
        
        <a href="#" class="sidebar-link">Perfil</a>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebarMenu');
            const overlay = document.getElementById('sidebarOverlay');
            const btnOpen = document.getElementById('sidebarOpen');
            const btnClose = document.getElementById('sidebarClose');

            function toggleSidebar() {
                sidebar.classList.toggle('active');
                if (sidebar.classList.contains('active')) {
                    overlay.style.display = 'block';
                    // Pequeño timeout para permitir la transición de opacidad
                    setTimeout(() => overlay.style.opacity = '1', 10);
                } else {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            }

            btnOpen.addEventListener('click', toggleSidebar);
            btnClose.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
        });
    </script>
<?php endif; ?>
</div>

<hr>
