<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Procesar acciones sobre los reportes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'eliminar_reporte' && isset($_POST['reporte_id'])) {
        $reporte_target = (int)$_POST['reporte_id'];
        $stmt_del = $pdo->prepare("DELETE FROM Reportes WHERE ID_reporte = ?");
        $stmt_del->execute([$reporte_target]);
        $msg_exito = "Queja/Reporte descartado.";
    } elseif ($_POST['accion'] === 'sancionar_conductor' && isset($_POST['conductor_id'])) {
        // Here we could update 'Estado' in Conductores to something like 'Suspendido' or 'Baneado'
        $conductor_target = (int)$_POST['conductor_id'];
        $stmt_ban = $pdo->prepare("UPDATE Conductores SET Estado = 'Rechazado' WHERE ID_conductor = ?"); // or add a Ban state if necessary
        $stmt_ban->execute([$conductor_target]);
        
        // Also cancel all active publications
        $stmt_cancel_viajes = $pdo->prepare("
            UPDATE Publicaciones p 
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion 
            SET p.Estado = 'Cancelada' 
            WHERE cp.ID_conductor = ?
        ");
        $stmt_cancel_viajes->execute([$conductor_target]);
        
         $msg_exito = "Conductor sancionado permanentemente (estado Rechazado) y viajes cancelados.";
    } elseif ($_POST['accion'] === 'suspender_conductor' && isset($_POST['conductor_id'])) {
        $conductor_target = (int)$_POST['conductor_id'];
        $fecha_ban = $_POST['fecha_ban'] ?? '';
        
        if (!empty($fecha_ban)) {
            $stmt_ban = $pdo->prepare("UPDATE Conductores SET BaneadoHasta = ? WHERE ID_conductor = ?");
            $stmt_ban->execute([$fecha_ban, $conductor_target]);
            
            $stmt_cancel_viajes = $pdo->prepare("UPDATE Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion SET p.Estado = 'Cancelada' WHERE cp.ID_conductor = ? AND p.Estado = 'Activa'");
            $stmt_cancel_viajes->execute([$conductor_target]);
            
            $msg_exito = "Conductor suspendido temporalmente hasta el $fecha_ban y se han cancelado sus viajes activos.";
        }
    }
}

// Obtener la lista de quejas (Reportes)
$stmt = $pdo->query("
    SELECT r.ID_reporte AS id, r.Fecha, r.Hora, r.Descripcion,
           c.ID_conductor AS conductor_id, u.Nombre AS conductor_nombre, u.Apellido AS conductor_apellido,
           c.Estado AS conductor_estado
    FROM Reportes r
    JOIN Conductores c ON r.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    ORDER BY r.Fecha DESC, r.Hora DESC
");
$reportes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Gestión de Reportes - Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .admin-nav { background-color: #333; color: white; padding: 10px; }
        .admin-nav a { color: white; margin-right: 15px; text-decoration: none; }
        .admin-nav a:hover { text-decoration: underline; }
        .table-admin { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-admin th, .table-admin td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        .table-admin th { background-color: #f2f2f2; }
        .btn-rechazar { background-color: #dc3545; color: white; padding: 5px 10px; border: none; cursor: pointer; border-radius: 3px; }
        .btn-sancionar { background-color: #f0ad4e; color: white; padding: 5px 10px; border: none; cursor: pointer; border-radius: 3px; }
    </style>
</head>
<body>

<div class="admin-nav">
    <strong>Admin Panel</strong> |
    <a href="dashboard.php">Dashboard</a>
    <a href="conductores.php">Conductores</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="viajes.php">Viajes</a>
    <a href="reportes.php">Reportes</a>
    <a href="pagos.php">Pagos</a>
    <a style="float: right;" href="../logout.php">Cerrar Sesión</a>
</div>

<div style="padding: 20px;">
    <h2>Buzón de Quejas Anónimas</h2>
    <p>Lista de quejas ingresadas contra conductores y sus vehículos en la plataforma.</p>
    
    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if (empty($reportes)): ?>
        <p>No hay mensajes ni quejas recientes. ¡Excelente!</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha y Hora</th>
                    <th>Conductor Reportado</th>
                    <th>Detalle de la Queja</th>
                    <th style="width:250px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportes as $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td>
                        <?= date('d/m/Y', strtotime($r['Fecha'])) ?><br>
                        A las <?= substr($r['Hora'], 0, 5) ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($r['conductor_nombre'] . ' ' . $r['conductor_apellido']) ?></strong><br>
                        Estado Actual: <?= htmlspecialchars($r['conductor_estado']) ?>
                    </td>
                    <td><?= nl2br(htmlspecialchars($r['Descripcion'])) ?></td>
                    <td style="text-align: center;">
                        <form method="post" style="display:inline-block; margin-bottom: 5px; width: 100%;">
                            <input type="hidden" name="reporte_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar_reporte">
                            <button type="submit" class="btn-rechazar" style="width: 100%;" onclick="return confirm('¿Descartar y borrar esta queja?');">Descartar (Falsa)</button>
                        </form>
                        
                        <form method="post" style="display:inline-block; margin-bottom: 5px; text-align: left; background: #f9f9f9; padding: 5px; border: 1px solid #ddd; width: 100%; box-sizing: border-box;">
                            <input type="hidden" name="conductor_id" value="<?= $r['conductor_id'] ?>">
                            <input type="hidden" name="accion" value="suspender_conductor">
                            <label style="font-size: 0.8em; font-weight: bold;">Suspender temporalmente:</label><br>
                            <input type="datetime-local" name="fecha_ban" required style="width: 100%; box-sizing: border-box; margin-bottom: 5px; font-size: 0.85em;">
                            <button type="submit" style="background-color: #f0ad4e; color: white; padding: 4px; border: none; cursor: pointer; border-radius: 3px; width: 100%; font-size: 0.85em;">Suspender Conductor</button>
                        </form>

                        <form method="post" style="display:inline-block; width: 100%;">
                            <input type="hidden" name="conductor_id" value="<?= $r['conductor_id'] ?>">
                            <input type="hidden" name="accion" value="sancionar_conductor">
                            <button type="submit" class="btn-sancionar" style="background-color: #333; width: 100%; font-size: 0.85em;" onclick="return confirm('ATENCION: Esto rechazará permanentemente al conductor. ¿Proceder?');">Rechazo Permanente</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
