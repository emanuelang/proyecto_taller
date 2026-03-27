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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .admin-nav {
            background-color: #333;
            color: white;
            padding: 10px;
        }
        .admin-nav a {
            color: white;
            margin-right: 15px;
            text-decoration: none;
        }
        .admin-nav a:hover {
            text-decoration: underline;
        }
        
        /* Dashboard KPI Cards styling */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .kpi-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.04);
            transition: transform 0.2s;
            border-top: 4px solid var(--primary, #333);
        }
        .kpi-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
        }
        .kpi-title {
            font-size: 0.9em;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: bold;
        }
        .kpi-value {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        .kpi-card.success { border-top-color: #28a745; }
        .kpi-card.warning { border-top-color: #f0ad4e; }
        .kpi-card.danger { border-top-color: #dc3545; }
    </style>
</head>
<body>

<div class="admin-nav">
    <strong>Admin Panel</strong> |
    <a href="dashboard.php">Dashboard</a>
    <!-- Enlaces futuros que vamos a armar -->
    <a href="conductores.php">Conductores</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="viajes.php">Viajes</a>
    <a href="reportes.php">Reportes</a>
    <a href="pagos.php">Pagos</a>
    <a style="float: right;" href="../logout.php">Cerrar Sesión</a>
</div>

<div style="padding: 20px;">
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h2>
    <p>Has ingresado al panel de administración del sistema.</p>
    
    <hr>
    <h3>Visión General del Sistema</h3>
    
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
