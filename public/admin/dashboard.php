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

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h2>
    <p>Has ingresado al panel de administración del sistema.</p>
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'import_success'): ?>
        <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            ✅ Base de datos restaurada correctamente a partir del backup.
        </div>
    <?php elseif (isset($_GET['error'])): ?>
        <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            ❌ Error en la importación: 
            <?php 
                if ($_GET['error'] === 'upload_failed') echo "No se pudo subir el archivo.";
                elseif ($_GET['error'] === 'invalid_extension') echo "El archivo debe ser extensión .SQL.";
                elseif ($_GET['error'] === 'invalid_signature') echo "Firma inválida. Este archivo SQL no fue generado por nuestro sistema y podría romper la base de datos.";
                elseif ($_GET['error'] === 'import_exception') echo "La consulta SQL contenía errores o era demasiado grande.";
                else echo "Error desconocido.";
            ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; gap: 20px;">
        <h3 style="margin: 0;">Visión General del Sistema</h3>
        
        <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
            <!-- Botón Exportar Original -->
            <a href="backup.php" class="btn" style="background-color: var(--success); text-align: center; width: 100%; box-sizing: border-box;" target="_blank">⬇️ Exportar Backup de DB</a>
            
            <!-- Formulario Importar Nuevo -->
            <form action="import_backup.php" method="POST" enctype="multipart/form-data" style="width: 100%; display: flex; flex-direction: column; gap: 5px; margin: 0; padding: 0; border: none; box-shadow: none; background: transparent;">
                <input type="file" name="backup_file" accept=".sql" required style="font-size: 0.85em; padding: 5px; border: 1px solid #ccc; background: white; border-radius: 4px; color: #555;">
                <button type="submit" class="btn btn-rechazar" style="margin: 0; padding: 10px; box-sizing: border-box;">⬆️ Importar Backup de DB</button>
            </form>
        </div>
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
