<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_badges = [
    'conductores.php' => 0,
    'vehiculos.php' => 0,
    'soporte.php' => 0,
];

try {
    $admin_badges['conductores.php'] = (int)$pdo->query("SELECT COUNT(*) FROM Conductores WHERE Estado = 'Esperando'")->fetchColumn();
    $admin_badges['vehiculos.php'] = (int)$pdo->query("SELECT COUNT(*) FROM Vehiculos WHERE Estado = 'Pendiente'")->fetchColumn();
    $admin_badges['soporte.php'] = (int)$pdo->query("SELECT COUNT(*) FROM Soporte WHERE Estado = 'Pendiente'")->fetchColumn();
} catch (Exception $e) {
    $admin_badges = array_fill_keys(array_keys($admin_badges), 0);
}

$admin_tabs = [
    ['href' => 'dashboard.php', 'page' => 'dashboard.php', 'icon' => '▦', 'label' => 'Dashboard'],
    ['href' => 'conductores.php', 'page' => 'conductores.php', 'icon' => '♙', 'label' => 'Conductores'],
    ['href' => 'vehiculos.php', 'page' => 'vehiculos.php', 'icon' => '▰', 'label' => 'Vehículos'],
    ['href' => 'usuarios.php', 'page' => 'usuarios.php', 'icon' => '♧', 'label' => 'Usuarios'],
    ['href' => 'viajes.php', 'page' => 'viajes.php', 'icon' => '⌖', 'label' => 'Viajes'],
    ['href' => 'reportes.php', 'page' => 'reportes.php', 'icon' => '▤', 'label' => 'Reportes'],
    ['href' => 'soporte.php', 'page' => 'soporte.php', 'icon' => '☎', 'label' => 'Soporte'],
    ['href' => 'pagos.php', 'page' => 'pagos.php', 'icon' => '$', 'label' => 'Pagos'],
];
?>

<div class="admin-shell">
    <div class="admin-page-head">
        <span class="admin-pill">◇ ADMIN</span>
        <h1 class="page-title">Panel de Administración</h1>
        <p class="page-subtitle">Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Administrador') ?>. Tenés acceso completo al sistema.</p>
    </div>

    <nav class="admin-tabs" aria-label="Navegación de administración">
        <?php foreach ($admin_tabs as $tab): ?>
            <?php $active = $current_page === $tab['page']; ?>
            <a href="<?= $tab['href'] ?>" class="admin-tab <?= $active ? 'active' : '' ?>">
                <span><?= $tab['icon'] ?></span>
                <?= $tab['label'] ?>
                <?php if (!empty($admin_badges[$tab['page']])): ?>
                    <em><?= $admin_badges[$tab['page']] ?></em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
