<?php
$current_page = basename($_SERVER['PHP_SELF']);
require_once __DIR__ . '/../../public/header.php';
?>
<div class="page-shell">
    <div style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start; flex-wrap:wrap;">
        <div>
            <h1 class="page-title">Panel de Conductor</h1>
            <p class="page-subtitle">Gestiona tu perfil, vehiculos y viajes publicados</p>
        </div>
        <a href="manual_conductor.php" class="driver-help-link <?= $current_page == 'manual_conductor.php' ? 'active' : '' ?>" title="Manual de ayuda" aria-label="Manual de ayuda">
            <span class="driver-wheel" aria-hidden="true"></span>
            <span class="help-dot" aria-hidden="true">?</span>
            <span class="driver-help-text">Manual de ayuda</span>
        </a>
    </div>

    <div class="tabs">
        <a href="dashboard.php" class="tab <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Mi Perfil</a>
        <a href="vehiculos.php" class="tab <?= $current_page == 'vehiculos.php' ? 'active' : '' ?>">Mis Vehiculos</a>
        <a href="viajes.php" class="tab <?= $current_page == 'viajes.php' ? 'active' : '' ?>">Mis Viajes</a>
    </div>
</div>
