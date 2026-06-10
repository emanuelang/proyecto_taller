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
    ['href' => 'dashboard.php', 'page' => 'dashboard.php', 'icon' => '[]', 'label' => 'Dashboard'],
    ['href' => 'conductores.php', 'page' => 'conductores.php', 'icon' => 'U', 'label' => 'Conductores'],
    ['href' => 'vehiculos.php', 'page' => 'vehiculos.php', 'icon' => 'V', 'label' => 'Vehiculos'],
    ['href' => 'usuarios.php', 'page' => 'usuarios.php', 'icon' => 'P', 'label' => 'Usuarios'],
    ['href' => 'viajes.php', 'page' => 'viajes.php', 'icon' => 'O', 'label' => 'Viajes'],
    ['href' => 'reportes.php', 'page' => 'reportes.php', 'icon' => '!', 'label' => 'Reportes'],
    ['href' => 'soporte.php', 'page' => 'soporte.php', 'icon' => '?', 'label' => 'Soporte'],
];

if (PAYMENTS_ENABLED) {
    $admin_tabs[] = ['href' => 'pagos.php', 'page' => 'pagos.php', 'icon' => '$', 'label' => 'Pagos'];
}
?>

<style>
    .manual-help-button {
        min-width: 190px;
        border: 1px solid rgba(37, 99, 235, .18);
        border-radius: 22px;
        background: #fff;
        color: var(--text-main);
        box-shadow: var(--shadow);
        padding: 10px 14px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 900;
        text-decoration: none;
        white-space: nowrap;
        transition: transform .16s ease, box-shadow .16s ease;
    }

    .manual-help-button:hover,
    .manual-help-button.active {
        transform: translateY(-1px);
        box-shadow: 0 18px 34px rgba(37, 99, 235, .16);
    }

    .manual-help-icon {
        width: 54px;
        height: 54px;
        border-radius: 20px;
        background:
            radial-gradient(circle at 72% 22%, #3bb9ff 0 10%, transparent 11%),
            radial-gradient(circle at 22% 76%, #ff6b57 0 8%, transparent 9%),
            radial-gradient(circle at 72% 68%, #d7f850 0 16%, transparent 17%),
            linear-gradient(135deg, #37b9ff 0%, #13c991 52%, #e8f65c 100%);
        display: grid;
        place-items: center;
        position: relative;
        color: white;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.35);
    }

    .manual-help-icon::before {
        content: "";
        width: 28px;
        height: 22px;
        border: 3px solid #fff;
        border-radius: 4px 4px 8px 8px;
        border-top: 0;
        box-shadow: 0 -9px 0 -6px #fff;
    }

    .manual-help-icon::after {
        content: "?";
        position: absolute;
        right: 7px;
        bottom: 6px;
        width: 20px;
        height: 20px;
        border-radius: 8px;
        background: rgba(255,255,255,.96);
        color: var(--primary);
        display: grid;
        place-items: center;
        font-size: 13px;
        font-weight: 900;
    }

    .manual-help-copy {
        display: grid;
        gap: 1px;
    }

    .manual-help-copy small {
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 800;
    }

    @media (max-width: 760px) {
        .admin-page-head {
            flex-direction: column;
        }

        .manual-help-button {
            width: 100%;
        }
    }
</style>

<div class="admin-shell">
    <div class="admin-page-head" style="display:flex; justify-content:space-between; gap:18px; align-items:flex-start;">
        <div>
            <span class="admin-pill">ADMIN</span>
            <h1 class="page-title">Panel de Administracion</h1>
            <p class="page-subtitle">Bienvenido, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Administrador') ?>. Tenes acceso completo al sistema.</p>
        </div>
        <a href="manual_admin.php" class="manual-help-button <?= $current_page === 'manual_admin.php' ? 'active' : '' ?>">
            <span class="manual-help-icon" aria-hidden="true"></span>
            <span class="manual-help-copy">
                Manual de uso
                <small>Ayuda del panel</small>
            </span>
        </a>
    </div>

    <nav class="admin-tabs" aria-label="Navegacion de administracion">
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
