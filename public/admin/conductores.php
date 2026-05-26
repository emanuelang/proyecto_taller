<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

// Procesar acciones de aprobar/rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['conductor_id'])) {
    require_csrf();
    $conductor_id = (int)$_POST['conductor_id'];
    $accion = $_POST['accion']; // 'aprobar' o 'rechazar'
    
    if ($accion === 'aprobar') {
        $stmt = $pdo->prepare("UPDATE Conductores SET Estado = 'Aceptada' WHERE ID_conductor = ?");
        $stmt->execute([$conductor_id]);
        $msg = "Conductor aprobado con éxito.";
    } elseif ($accion === 'rechazar' || $accion === 'eliminar') {
        try {
            $pdo->beginTransaction();

            // 1. Buscar viajes vinculados para reembolsar
            $stmt_pub = $pdo->prepare("SELECT p.ID_publicacion, p.Precio, p.CiudadOrigen, p.CiudadDestino FROM Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion WHERE cp.ID_conductor = ?");
            $stmt_pub->execute([$conductor_id]);
            $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

            foreach ($publicaciones as $pub) {
                // Reembolsar a pasajeros
                $stmt_res = $pdo->prepare("
                    SELECT u.ID_usuario
                    FROM Reservas r
                    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
                    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
                    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
                    WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
                ");
                $stmt_res->execute([$pub['ID_publicacion']]);
                $reservas = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

                foreach ($reservas as $res) {
                    $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")->execute([$pub['Precio'], $res['ID_usuario']]);
                    $mensaje = "El conductor de tu viaje (" . $pub['CiudadOrigen'] . " → " . $pub['CiudadDestino'] . ") ha sido eliminado por la administración. Se reembolsaron $" . number_format($pub['Precio'], 2) . ".";
                    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$res['ID_usuario'], $mensaje]);
                }
                
                // Marcar publicación como cancelada
                $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$pub['ID_publicacion']]);
            }

            // 2. Obtener vehículos para borrar después
            $stmt_vehiculo = $pdo->prepare("SELECT ID_vehiculo FROM ConductorVehiculo WHERE ID_conductor = ?");
            $stmt_vehiculo->execute([$conductor_id]);
            $vehiculos = $stmt_vehiculo->fetchAll(PDO::FETCH_ASSOC);

            // 3. Borrar el conductor (borrado físico del conductor según lo actual)
            $pdo->prepare("DELETE FROM Conductores WHERE ID_conductor = ?")->execute([$conductor_id]);

            // 4. Borrar vehículos
            foreach ($vehiculos as $v) {
                $pdo->prepare("DELETE FROM Vehiculos WHERE ID_vehiculo = ?")->execute([$v['ID_vehiculo']]);
            }

            $pdo->commit();
            $msg = ($accion === 'rechazar') ? "Conductor rechazado y viajes cancelados." : "Conductor eliminado y viajes cancelados.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    } elseif ($accion === 'banear_conductor') {
        $fecha_ban = $_POST['fecha_ban'] ?? '';
        if (!empty($fecha_ban)) {
            try {
                $pdo->beginTransaction();
                
                $stmt_ban = $pdo->prepare("UPDATE Conductores SET BaneadoHasta = ? WHERE ID_conductor = ?");
                $stmt_ban->execute([$fecha_ban, $conductor_id]);
                
                // Buscar viajes activos para cancelar y reembolsar
                $stmt_viajes = $pdo->prepare("SELECT p.ID_publicacion, p.Precio, p.CiudadOrigen, p.CiudadDestino FROM Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion WHERE cp.ID_conductor = ? AND p.Estado = 'Activa'");
                $stmt_viajes->execute([$conductor_id]);
                $viajes_activos = $stmt_viajes->fetchAll(PDO::FETCH_ASSOC);

                foreach ($viajes_activos as $v) {
                    $stmt_res = $pdo->prepare("
                        SELECT u.ID_usuario
                        FROM Reservas r
                        JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
                        JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
                        JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
                        WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
                    ");
                    $stmt_res->execute([$v['ID_publicacion']]);
                    $pasajeros = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($pasajeros as $pas) {
                        $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")->execute([$v['Precio'], $pas['ID_usuario']]);
                        $mensaje = "El conductor de tu viaje (" . $v['CiudadOrigen'] . " → " . $v['CiudadDestino'] . ") ha sido suspendido temporalmente. Se han reembolsado $" . number_format($v['Precio'], 2) . ".";
                        $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$pas['ID_usuario'], $mensaje]);
                    }
                    
                    $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$v['ID_publicacion']]);
                }

                $pdo->commit();
                $msg = "Conductor suspendido correctamente hasta el $fecha_ban. Sus viajes han sido cancelados y reembolsados.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "Error: " . $e->getMessage();
            }
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

// Paginación para Aceptados
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$count_sql = "
    SELECT COUNT(*) FROM Conductores c
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE c.Estado = 'Aceptada' $search_sql
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params_aceptados);
$total_paginas = ceil($stmt_count->fetchColumn() / $limite);

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
    LIMIT $limite OFFSET $offset
";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($params_aceptados);
$aceptados = $stmt2->fetchAll();
require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

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

    <?php if (isset($total_paginas) && $total_paginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal para ver imágenes en tamaño completo -->
<div id="imageModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <span onclick="document.getElementById('imageModal').style.display='none'" style="position:absolute; top:20px; right:35px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="modalImage" style="max-width:90%; max-height:90%; object-fit:contain; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
</div>
<script>
function openModal(src) {
    document.getElementById('modalImage').src = src;
    document.getElementById('imageModal').style.display = 'flex';
}
document.querySelectorAll('.details-list img').forEach(img => {
    img.style.cursor = 'pointer';
    img.onclick = () => openModal(img.src);
});
// Cerrar modal al clickear fuera de la imagen
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

</body>
</html>
