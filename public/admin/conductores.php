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
    } elseif ($accion === 'banear_conductor') {
        $fecha_ban = $_POST['fecha_ban'] ?? '';
        if (!empty($fecha_ban)) {
            $stmt_ban = $pdo->prepare("UPDATE Conductores SET BaneadoHasta = ? WHERE ID_conductor = ?");
            $stmt_ban->execute([$fecha_ban, $conductor_id]);
            
            $stmt_cancel = $pdo->prepare("UPDATE Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion SET p.Estado = 'Cancelada' WHERE cp.ID_conductor = ? AND p.Estado = 'Activa'");
            $stmt_cancel->execute([$conductor_id]);
            
            $msg = "Conductor suspendido correctamente hasta el $fecha_ban. Sus viajes han sido cancelados.";
        }
    }
}

// Filtro de búsqueda
$search = $_GET['search'] ?? '';
$search_sql = '';
$params_pendientes = [];
$params_aceptados = [];

if ($search !== '') {
    $search_sql = " AND (u.Nombre LIKE ? OR u.DNI LIKE ? OR u.Correo LIKE ?) ";
    $params_pendientes = ["%$search%", "%$search%", "%$search%"];
    $params_aceptados = ["%$search%", "%$search%", "%$search%"];
}

// Obtener la lista de conductores pendientes y sus vehículos
$sql1 = "
    SELECT c.ID_conductor AS id, c.LicenciaConducir, c.SeguroVehiculo, c.CuentaBancaria, c.Estado, c.FechaRegistro AS creado_en,
           c.TelefonoContacto, c.AliasMP, c.FotoCarnet, c.FotoCara,
           u.ID_usuario AS usuario_id, u.Nombre AS nombre, u.Correo AS email, u.DNI,
           v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, 
           v.Foto AS vehiculo_doc, v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN ConductorVehiculo cv ON c.ID_conductor = cv.ID_conductor
    LEFT JOIN Vehiculos v ON cv.ID_vehiculo = v.ID_vehiculo
    WHERE c.Estado = 'Esperando' $search_sql
    ORDER BY c.FechaRegistro DESC
";
$stmt = $pdo->prepare($sql1);
$stmt->execute($params_pendientes);
$pendientes = $stmt->fetchAll();

// Obtener la lista de conductores aceptados
$sql2 = "
    SELECT c.ID_conductor AS id, c.LicenciaConducir, c.SeguroVehiculo, c.CuentaBancaria, c.Estado, c.FechaRegistro AS creado_en, c.BaneadoHasta,
           c.TelefonoContacto, c.AliasMP, c.FotoCarnet, c.FotoCara,
           u.ID_usuario AS usuario_id, u.Nombre AS nombre, u.Correo AS email, u.DNI,
           v.Marca AS marca, v.Modelo AS modelo, v.Color AS color, v.CantidadAsientos AS asientos, 
           v.Foto AS vehiculo_doc, v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras
    FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN ConductorVehiculo cv ON c.ID_conductor = cv.ID_conductor
    LEFT JOIN Vehiculos v ON cv.ID_vehiculo = v.ID_vehiculo
    WHERE c.Estado = 'Aceptada' $search_sql
    ORDER BY c.FechaRegistro DESC
";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($params_aceptados);
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
    <h2>Conductores</h2>
    <p>Aquí puedes buscar y revisar las solicitudes u conductores activos.</p>
    
    <form method="GET" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 500px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por Nombre, DNI o Correo" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Buscar</button>
        <?php if($search): ?>
            <a href="conductores.php" style="padding: 10px; background-color: #ccc; color: black; border-radius: 4px; text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>

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
                            <li><strong>Tel:</strong> <?= htmlspecialchars($c['TelefonoContacto'] ?? '---') ?></li>
                            <li><strong>Licencia N°:</strong> <?= htmlspecialchars($c['LicenciaConducir']) ?></li>
                            <li><strong>Seguro policial:</strong> <?= htmlspecialchars($c['SeguroVehiculo']) ?></li>
                            <li><strong>CBU Bancario:</strong> <?= htmlspecialchars($c['CuentaBancaria']) ?></li>
                            <li><strong>Alias MP:</strong> <?= htmlspecialchars($c['AliasMP'] ?? '---') ?></li>
                            
                            <?php if($c['FotoCara']): ?>
                                <li style="margin-top: 5px;"><strong>Foto Cara:</strong><br><img src="<?= $c['FotoCara'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                            <?php if($c['FotoCarnet']): ?>
                                <li style="margin-top: 5px;"><strong>Carnet Conducir:</strong><br><img src="<?= $c['FotoCarnet'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                        </ul>
                    </td>
                    <td>
                        <?php if($c['marca']): ?>
                            <ul class="details-list">
                                <li><strong>Auto:</strong> <?= htmlspecialchars($c['marca'] . ' ' . $c['modelo']) ?></li>
                                <li><strong>Color:</strong> <?= htmlspecialchars($c['color']) ?></li>
                                <li><strong>Asientos disp:</strong> <?= $c['asientos'] ?></li>
                                
                                <li style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px;">
                                    <?php if($c['PapelesAuto']): ?>
                                        <div><small>Papeles</small><br><img src="<?= $c['PapelesAuto'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($c['FotoFrente']): ?>
                                        <div><small>Frente</small><br><img src="<?= $c['FotoFrente'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($c['FotoCostado']): ?>
                                        <div><small>Costado</small><br><img src="<?= $c['FotoCostado'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($c['FotoAtras']): ?>
                                        <div><small>Atrás</small><br><img src="<?= $c['FotoAtras'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
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
                            <li><strong>Tel:</strong> <?= htmlspecialchars($a['TelefonoContacto'] ?? '---') ?></li>
                            <li><strong>Licencia N°:</strong> <?= htmlspecialchars($a['LicenciaConducir']) ?></li>
                            <li><strong>Seguro policial:</strong> <?= htmlspecialchars($a['SeguroVehiculo']) ?></li>
                            <li><strong>CBU Bancario:</strong> <?= htmlspecialchars($a['CuentaBancaria']) ?></li>
                            <li><strong>Alias MP:</strong> <?= htmlspecialchars($a['AliasMP'] ?? '---') ?></li>
                            <li><strong>Registrado el:</strong> <?= htmlspecialchars($a['creado_en']) ?></li>
                            
                            <?php if ($a['BaneadoHasta'] && strtotime($a['BaneadoHasta']) > time()): ?>
                                <li><strong style="color:red;">Baneado como Conductor hasta:</strong><br><span style="color:red;"><?= date('d/m/Y H:i', strtotime($a['BaneadoHasta'])) ?></span></li>
                            <?php endif; ?>

                            <?php if($a['FotoCara']): ?>
                                <li style="margin-top: 5px;"><strong>Foto Cara:</strong><br><img src="<?= $a['FotoCara'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                            <?php if($a['FotoCarnet']): ?>
                                <li style="margin-top: 5px;"><strong>Carnet Conducir:</strong><br><img src="<?= $a['FotoCarnet'] ?>" style="max-height: 80px; border-radius: 4px; border: 1px solid #ccc;"></li>
                            <?php endif; ?>
                        </ul>
                    </td>
                    <td>
                        <?php if($a['marca']): ?>
                            <ul class="details-list">
                                <li><strong>Auto:</strong> <?= htmlspecialchars($a['marca'] . ' ' . $a['modelo']) ?></li>
                                <li><strong>Color:</strong> <?= htmlspecialchars($a['color']) ?></li>
                                <li><strong>Asientos disp:</strong> <?= $a['asientos'] ?></li>
                                
                                <li style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px;">
                                    <?php if($a['PapelesAuto']): ?>
                                        <div><small>Papeles</small><br><img src="<?= $a['PapelesAuto'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($a['FotoFrente']): ?>
                                        <div><small>Frente</small><br><img src="<?= $a['FotoFrente'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($a['FotoCostado']): ?>
                                        <div><small>Costado</small><br><img src="<?= $a['FotoCostado'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                    <?php if($a['FotoAtras']): ?>
                                        <div><small>Atrás</small><br><img src="<?= $a['FotoAtras'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;"></div>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        <?php else: ?>
                            <em>No registró vehículo</em>
                        <?php endif; ?>
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                        <form method="post" style="margin-bottom: 5px; text-align: left; background: #f9f9f9; padding: 5px; border: 1px solid #ddd;">
                            <input type="hidden" name="conductor_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="accion" value="banear_conductor">
                            <label style="font-size: 0.8em; font-weight: bold;">Suspender conductor hasta:</label><br>
                            <input type="datetime-local" name="fecha_ban" required style="width: 100%; box-sizing: border-box; margin-bottom: 5px; font-size: 0.85em;">
                            <button type="submit" style="background-color: #f0ad4e; color: white; padding: 4px; border: none; cursor: pointer; border-radius: 3px; width: 100%; font-size: 0.85em;">Suspender</button>
                        </form>

                        <form method="post">
                            <input type="hidden" name="conductor_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="btn-rechazar" style="width: 100%; font-size: 0.85em;" onclick="return confirm('¿Seguro que deseas ELIMINAR a este conductor de la plataforma de forma permanente? Se borrarán sus viajes y vehículos.');">Eliminar Permanente</button>
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
