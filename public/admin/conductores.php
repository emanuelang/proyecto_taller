<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Procesar acciones de aprobar/rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['conductor_id'])) {
    $conductor_id = (int)$_POST['conductor_id'];
    $accion = $_POST['accion']; // 'aprobar' o 'rechazar'
    
    if ($accion === 'aprobar') {
        $stmt = $pdo->prepare("UPDATE Conductores SET Estado = 'Aceptada' WHERE ID_conductor = ?");
        $stmt->execute([$conductor_id]);
        $msg = "Conductor aprobado con éxito.";
    } elseif ($accion === 'rechazar' || $accion === 'eliminar') {
        // Obtenemos publicaciones vinculadas para borrarlas y evitar fallo en foreign key de Vehiculos
        $stmt_pub = $pdo->prepare("SELECT ID_publicacion FROM ConductorPublicacion WHERE ID_conductor = ?");
        $stmt_pub->execute([$conductor_id]);
        $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

        foreach ($publicaciones as $p) {
            $stmt_del_pub = $pdo->prepare("DELETE FROM Publicaciones WHERE ID_publicacion = ?");
            $stmt_del_pub->execute([$p['ID_publicacion']]);
        }

        // Al borrar el conductor, la BD borrará automáticamente su registro en ConductorVehiculo (ON DELETE CASCADE)
        // pero necesitamos borrar el Vehiculo también para no dejar huérfanos.
        $stmt_vehiculo = $pdo->prepare("SELECT ID_vehiculo FROM ConductorVehiculo WHERE ID_conductor = ?");
        $stmt_vehiculo->execute([$conductor_id]);
        $vehiculos = $stmt_vehiculo->fetchAll(PDO::FETCH_ASSOC);

        $stmt_del_conductor = $pdo->prepare("DELETE FROM Conductores WHERE ID_conductor = ?");
        $stmt_del_conductor->execute([$conductor_id]);

        foreach ($vehiculos as $v) {
            $stmt_del_veh = $pdo->prepare("DELETE FROM Vehiculos WHERE ID_vehiculo = ?");
            $stmt_del_veh->execute([$v['ID_vehiculo']]);
        }
        
        $msg = ($accion === 'rechazar') ? "Conductor rechazado (solicitud eliminada)." : "Conductor eliminado correctamente del sistema.";
    }
}

// Obtener la lista de conductores pendientes y sus vehículos
$stmt = $pdo->query("
    SELECT c.ID_conductor AS id, c.LicenciaConducir, c.SeguroVehiculo, c.CuentaBancaria, c.Estado, c.FechaRegistro AS creado_en,
           u.ID_usuario AS usuario_id, u.Nombre AS nombre, u.Correo AS email,
           v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, v.Foto AS vehiculo_doc
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN ConductorVehiculo cv ON c.ID_conductor = cv.ID_conductor
    LEFT JOIN Vehiculos v ON cv.ID_vehiculo = v.ID_vehiculo
    WHERE c.Estado = 'Esperando'
    ORDER BY c.FechaRegistro DESC
");
$pendientes = $stmt->fetchAll();

// Obtener la lista de conductores aceptados
$stmt2 = $pdo->query("
    SELECT c.ID_conductor AS id, c.LicenciaConducir, c.SeguroVehiculo, c.CuentaBancaria, c.Estado, c.FechaRegistro AS creado_en,
           u.ID_usuario AS usuario_id, u.Nombre AS nombre, u.Correo AS email,
           v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, v.Foto AS vehiculo_doc
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN ConductorVehiculo cv ON c.ID_conductor = cv.ID_conductor
    LEFT JOIN Vehiculos v ON cv.ID_vehiculo = v.ID_vehiculo
    WHERE c.Estado = 'Aceptada'
    ORDER BY c.FechaRegistro DESC
");
$aceptados = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Aprobación de Conductores - Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .admin-nav { background-color: #333; color: white; padding: 10px; }
        .admin-nav a { color: white; margin-right: 15px; text-decoration: none; }
        .admin-nav a:hover { text-decoration: underline; }
        .table-admin { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-admin th, .table-admin td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        .table-admin th { background-color: #f2f2f2; }
        .btn-aprobar { background-color: #28a745; color: white; padding: 5px 10px; border: none; cursor: pointer; margin-bottom: 5px; width: 100%; }
        .btn-rechazar { background-color: #dc3545; color: white; padding: 5px 10px; border: none; cursor: pointer; width: 100%; }
        .details-list { margin: 0; padding-left: 15px; font-size: 0.9em; }
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
    <h2>Aprobación de Conductores</h2>
    <p>Aquí puedes revisar las solicitudes completas de los usuarios que quieren ser conductores.</p>
    
    <?php if (isset($msg)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <?php if (empty($pendientes)): ?>
        <p>No hay solicitudes de conductores pendientes.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Perfil y Licencia</th>
                    <th>Vehículo Inicial</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendientes as $c): ?>
                <tr>
                    <td>
                        <strong>Nom:</strong> <?= htmlspecialchars($c['nombre']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($c['email']) ?><br>
                        <strong>ID:</strong> <?= $c['usuario_id'] ?>
                    </td>
                    <td>
                        <ul class="details-list">
                            <li><strong>Licencia:</strong> <?= htmlspecialchars($c['LicenciaConducir']) ?></li>
                            <li><strong>Seguro:</strong> <?= htmlspecialchars($c['SeguroVehiculo']) ?></li>
                            <li><strong>Cta Bancaria:</strong> <?= htmlspecialchars($c['CuentaBancaria']) ?></li>
                        </ul>
                    </td>
                    <td>
                        <?php if($c['marca']): ?>
                            <ul class="details-list">
                                <li><strong>Auto:</strong> <?= htmlspecialchars($c['marca'] . ' ' . $c['modelo']) ?></li>
                                <li><strong>Color:</strong> <?= htmlspecialchars($c['color']) ?></li>
                                <li><strong>Asientos:</strong> <?= $c['asientos'] ?></li>
                                <li>
                                    <?php if($c['vehiculo_doc']): ?>
                                        <a href="<?= BASE_URL . $c['vehiculo_doc'] ?>" target="_blank">Ver Foto Vehículo</a>
                                    <?php else: ?>
                                        <em>Sin documento</em>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <em>No registró vehículo</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="margin-bottom: 5px;">
                            <input type="hidden" name="conductor_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar" onclick="return confirm('¿Aprobar a este conductor?');">Aprobar</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="conductor_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Rechazar a este conductor?');">Rechazar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr style="margin-top: 40px; margin-bottom: 20px;">

    <h2>Conductores Aprobados</h2>
    <p>Lista de conductores activos en la plataforma. Puedes eliminarlos si infringen las reglas.</p>

    <?php if (empty($aceptados)): ?>
        <p>No hay conductores activos.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Perfil y Licencia</th>
                    <th>Vehículo Asociado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aceptados as $a): ?>
                <tr>
                    <td>
                        <strong>Nom:</strong> <?= htmlspecialchars($a['nombre']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($a['email']) ?><br>
                        <strong>ID:</strong> <?= $a['usuario_id'] ?>
                    </td>
                    <td>
                        <ul class="details-list">
                            <li><strong>Licencia:</strong> <?= htmlspecialchars($a['LicenciaConducir']) ?></li>
                            <li><strong>Seguro:</strong> <?= htmlspecialchars($a['SeguroVehiculo']) ?></li>
                            <li><strong>Cta Bancaria:</strong> <?= htmlspecialchars($a['CuentaBancaria']) ?></li>
                            <li><strong>Registrado el:</strong> <?= htmlspecialchars($a['creado_en']) ?></li>
                        </ul>
                    </td>
                    <td>
                        <?php if($a['marca']): ?>
                            <ul class="details-list">
                                <li><strong>Auto:</strong> <?= htmlspecialchars($a['marca'] . ' ' . $a['modelo']) ?></li>
                                <li><strong>Color:</strong> <?= htmlspecialchars($a['color']) ?></li>
                                <li><strong>Asientos:</strong> <?= $a['asientos'] ?></li>
                                <li>
                                    <?php if($a['vehiculo_doc']): ?>
                                        <a href="<?= BASE_URL . $a['vehiculo_doc'] ?>" target="_blank">Ver Foto Vehículo</a>
                                    <?php else: ?>
                                        <em>Sin documento</em>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <em>No registró vehículo</em>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                        <form method="post">
                            <input type="hidden" name="conductor_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Seguro que deseas ELIMINAR a este conductor de la plataforma de forma permanente? Se borrarán sus viajes y vehículos.');">Eliminar Definitivamente</button>
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
