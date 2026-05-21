<?php
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../../public/header.php';
?>
<div class="page-shell">
    <h1 class="page-title">Panel de Conductor</h1>
    <p class="page-subtitle">Gestioná tu perfil, vehículos y viajes publicados</p>

    <div class="tabs">
        <a href="dashboard.php" class="tab <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Mi Perfil</a>
        <a href="vehiculos.php" class="tab <?= $current_page == 'vehiculos.php' ? 'active' : '' ?>">Mis Vehículos</a>
        <a href="viajes.php" class="tab <?= $current_page == 'viajes.php' ? 'active' : '' ?>">Mis Viajes</a>
    </div>
</div>
