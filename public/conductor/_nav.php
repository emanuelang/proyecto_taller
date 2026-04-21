<?php
// Función auxiliar para saber qué página está activa
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../../public/header.php';
?>
<div class="nav-menu" style="background-color: var(--border-color); padding: 10px 15px; justify-content: space-between; align-items: center; margin-top: -20px; border-radius: 8px;">
    <div>
        <h2 style="margin: 0; color: var(--primary);">Panel de Conductor</h2>
    </div>
    
    <!-- Pestañas de navegación interna -->
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="dashboard.php" class="btn" style="background-color: <?= $current_page == 'dashboard.php' ? 'var(--primary-hover)' : 'var(--bg-main)' ?>; border: 1px solid var(--primary); color: <?= $current_page == 'dashboard.php' ? 'white' : 'var(--primary)' ?>;">
            👤 Mi Perfil
        </a>
        <a href="vehiculos.php" class="btn" style="background-color: <?= $current_page == 'vehiculos.php' ? 'var(--primary-hover)' : 'var(--bg-main)' ?>; border: 1px solid var(--primary); color: <?= $current_page == 'vehiculos.php' ? 'white' : 'var(--primary)' ?>;">
            🚗 Mis Vehículos
        </a>
        <a href="viajes.php" class="btn" style="background-color: <?= $current_page == 'viajes.php' ? 'var(--primary-hover)' : 'var(--bg-main)' ?>; border: 1px solid var(--primary); color: <?= $current_page == 'viajes.php' ? 'white' : 'var(--primary)' ?>;">
            🛣️ Mis Viajes
        </a>
    </div>

    <!-- Enlaces externos/salida -->
    <div>
        <a href="<?= BASE_URL ?>index.php" style="margin-right: 15px;">Ir a la web</a>
        <a href="<?= BASE_URL ?>logout.php" style="color: #ef4444; font-weight: bold;">Salir</a>
    </div>
</div>
