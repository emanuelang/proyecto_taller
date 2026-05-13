<?php
// Script to update nav menus and add pagination
$files = ['conductores.php', 'usuarios.php', 'viajes.php', 'reportes.php', 'pagos.php', 'dashboard.php'];
$dir = __DIR__ . '/public/admin/';

$new_nav = '<div class="nav-menu" style="background-color: var(--border-color); padding: 10px; justify-content: center; margin-top: -20px; margin-bottom: 20px; border-radius: 8px;">
    <strong style="color: var(--primary);">Admin Panel</strong>
    <a href="dashboard.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Dashboard</a>
    <a href="conductores.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Conductores</a>
    <a href="vehiculos.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Vehículos</a>
    <a href="usuarios.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Usuarios</a>
    <a href="viajes.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Viajes</a>
    <a href="reportes.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Reportes</a>
    <a href="soporte.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Soporte</a>
    <a href="pagos.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Pagos</a>
</div>';

foreach ($files as $file) {
    $path = $dir . $file;
    if (!file_exists($path)) continue;
    
    $content = file_get_contents($path);
    // Replace old nav menu with regex
    $content = preg_replace('/<div class="nav-menu".*?<\/div>/s', $new_nav, $content);
    file_put_contents($path, $content);
    echo "Updated nav in $file\n";
}
