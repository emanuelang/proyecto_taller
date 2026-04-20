<?php
// Requerimos la verificación de que sea admin
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Total Usuarios Activos (Usuarios estándar que no están baneados)
$stmt_users = $pdo->query("
    SELECT COUNT(*) 
    FROM Usuarios u 
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario 
    WHERE a.ID_administrador IS NULL 
    AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta < NOW())
");
$usuarios_activos = $stmt_users->fetchColumn();

// Total Viajes (Publicaciones)
$stmt_viajes = $pdo->query("SELECT COUNT(*) FROM Publicaciones");
$total_viajes = $stmt_viajes->fetchColumn();

// Conductores Pendientes
$stmt_cond = $pdo->query("SELECT COUNT(*) FROM Conductores WHERE Estado = 'Esperando'");
$conductores_pendientes = $stmt_cond->fetchColumn();

// Rentabilidad Estimada (Asumiendo 10% de comisión sobre los pagos o reservas exitosas)
// Buscamos pagos en estado Completado
$stmt_rentabilidad = $pdo->query("SELECT SUM(Monto) FROM Pagos WHERE Estado = 'Completado'");
$total_pagos = $stmt_rentabilidad->fetchColumn() ?: 0.00;
$rentabilidad_plataforma = $total_pagos * 0.10;

// Próximamente: Ganancias del mes actual, reservas activas, etc.
require_once __DIR__ . '/../header.php';
?>

<div class="nav-menu" style="background-color: var(--border-color); padding: 10px; justify-content: center; margin-top: -20px; margin-bottom: 20px; border-radius: 8px;">
    <strong style="color: var(--primary);">Admin Panel</strong>
    <a href="dashboard.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Dashboard</a>
    <a href="conductores.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Conductores</a>
    <a href="usuarios.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Usuarios</a>
    <a href="viajes.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Viajes</a>
    <a href="reportes.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Reportes</a>
    <a href="pagos.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Pagos</a>
</div>

<div style="padding: 20px;">
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h2>
    <p>Has ingresado al panel de administración del sistema.</p>
    
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;">Visión General del Sistema</h3>
        <a href="backup.php" class="btn" style="background-color: var(--success);" target="_blank">⬇️ Exportar Backup de DB</a>
    </div>
    
    <div class="kpi-container">
        <!-- Usuarios Activos -->
        <div class="kpi-card">
            <div class="kpi-title">Usuarios Activos</div>
            <div class="kpi-value"><?= number_format($usuarios_activos) ?></div>
        </div>
        
        <!-- Total Viajes -->
        <div class="kpi-card success">
            <div class="kpi-title">Viajes Registrados</div>
            <div class="kpi-value"><?= number_format($total_viajes) ?></div>
        </div>
        
        <!-- Conductores Pendientes -->
        <div class="kpi-card warning">
            <div class="kpi-title">Conductores Pendientes</div>
            <div class="kpi-value"><?= number_format($conductores_pendientes) ?></div>
        </div>
        
        <!-- Rentabilidad -->
        <div class="kpi-card danger">
            <div class="kpi-title">Rentabilidad (10% Com.)</div>
            <div class="kpi-value">$<?= number_format($rentabilidad_plataforma, 2) ?></div>
        </div>
    </div>
</div>

</body>
</html>
