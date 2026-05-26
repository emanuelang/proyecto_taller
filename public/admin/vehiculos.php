<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['vehiculo_id'])) {
    require_csrf();
    $vehiculo_id = (int)$_POST['vehiculo_id'];
    $accion = $_POST['accion'];
    
    if ($accion === 'aprobar') {
        $stmt = $pdo->prepare("UPDATE Vehiculos SET Estado = 'Aceptado' WHERE ID_vehiculo = ?");
        $stmt->execute([$vehiculo_id]);
        $msg = "Vehículo aprobado con éxito.";
    } elseif ($accion === 'rechazar' || $accion === 'eliminar') {
        try {
            $pdo->beginTransaction();

            // 1. Buscar publicaciones activas con este vehículo
            $stmt_pub = $pdo->prepare("SELECT ID_publicacion, CiudadOrigen, CiudadDestino, HoraSalida, Precio FROM Publicaciones WHERE ID_vehiculo = ?");
            $stmt_pub->execute([$vehiculo_id]);
            $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

            foreach ($publicaciones as $pub) {
                // 2. Reembolsar a pasajeros con reservas completadas
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
                    $mensaje = "Tu viaje de " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] . " ha sido cancelado por el administrador. Se han reembolsado $" . number_format($pub['Precio'], 2) . " a tu saldo.";
                    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$res['ID_usuario'], $mensaje]);
                }
                
                // Marcar publicación como cancelada (Soft cancel)
                $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$pub['ID_publicacion']]);
            }

            // 3. Eliminar el vehículo físicamente
            $pdo->prepare("DELETE FROM Vehiculos WHERE ID_vehiculo = ?")->execute([$vehiculo_id]);

            $pdo->commit();
            $msg = ($accion === 'rechazar') ? "Vehículo rechazado y viajes cancelados/reembolsados." : "Vehículo eliminado y viajes cancelados/reembolsados.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error: " . $e->getMessage();
        }
    }

}

// Filtro y paginación
$search = $_GET['search'] ?? '';
$search_sql = '';
$params_pendientes = [];
$params_aceptados = [];

if ($search !== '') {
    $search_sql = " AND (v.Marca LIKE ? OR v.Modelo LIKE ? OR v.Patente LIKE ? OR u.Nombre LIKE ?) ";
    $params_pendientes = ["%$search%", "%$search%", "%$search%", "%$search%"];
    $params_aceptados = ["%$search%", "%$search%", "%$search%", "%$search%"];
}

// Vehiculos pendientes (sin paginación, se asume que se procesan rápido)
$sql1 = "
    SELECT v.ID_vehiculo AS id, v.Marca, v.Modelo, v.Color, v.Patente, v.CantidadAsientos,
           v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras,
           u.Nombre AS conductor_nombre, u.Apellido AS conductor_apellido, u.Correo
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado = 'Pendiente' $search_sql
    ORDER BY v.ID_vehiculo ASC
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
    SELECT COUNT(*) FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado = 'Aceptado' $search_sql
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params_aceptados);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $limite);

// Obtener la lista de aceptados
$sql2 = "
    SELECT v.ID_vehiculo AS id, v.Marca, v.Modelo, v.Color, v.Patente, v.CantidadAsientos,
           v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras,
           u.Nombre AS conductor_nombre, u.Apellido AS conductor_apellido, u.Correo
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado = 'Aceptado' $search_sql
    ORDER BY v.ID_vehiculo DESC
    LIMIT $limite OFFSET $offset
";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($params_aceptados);
$aceptados = $stmt2->fetchAll();
require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2>Vehículos Adicionales</h2>
    <p>Revisa y aprueba los nuevos vehículos agregados por los conductores.</p>
    
    <form method="GET" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 500px;">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por Patente, Marca o Dueño" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Buscar</button>
        <?php if($search): ?>
            <a href="vehiculos.php" style="padding: 10px; background-color: #ccc; color: black; border-radius: 4px; text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (isset($msg)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <h3>Vehículos Pendientes</h3>
    <?php if (empty($pendientes)): ?>
        <p>No hay vehículos pendientes de aprobación.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Dueño</th>
                    <th>Detalles Vehículo</th>
                    <th>Imágenes Registradas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendientes as $v): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($v['conductor_nombre'] . ' ' . $v['conductor_apellido']) ?></strong><br>
                        <?= htmlspecialchars($v['Correo']) ?>
                    </td>
                    <td>
                        <strong>Marca/Mod:</strong> <?= htmlspecialchars($v['Marca'] . ' ' . $v['Modelo']) ?><br>
                        <strong>Patente:</strong> <?= htmlspecialchars($v['Patente']) ?><br>
                        <strong>Color:</strong> <?= htmlspecialchars($v['Color']) ?><br>
                        <strong>Asientos:</strong> <?= $v['CantidadAsientos'] ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <?php if($v['PapelesAuto']): ?><div><small>Papeles</small><br><img src="<?= $v['PapelesAuto'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                            <?php if($v['FotoFrente']): ?><div><small>Frente</small><br><img src="<?= $v['FotoFrente'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                            <?php if($v['FotoCostado']): ?><div><small>Costado</small><br><img src="<?= $v['FotoCostado'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                            <?php if($v['FotoAtras']): ?><div><small>Atrás</small><br><img src="<?= $v['FotoAtras'] ?>" style="max-height: 80px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <form method="post" style="margin-bottom: 5px;">
                            <input type="hidden" name="vehiculo_id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="accion" value="aprobar">
                            <button type="submit" class="btn-aprobar" onclick="return confirm('¿Aprobar este vehículo?');">Aprobar</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="vehiculo_id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="accion" value="rechazar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Rechazar este vehículo?');">Rechazar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr style="margin-top: 40px; margin-bottom: 20px;">

    <h3>Vehículos Aprobados</h3>
    <?php if (empty($aceptados)): ?>
        <p>No hay vehículos activos aprobados.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Dueño</th>
                    <th>Detalles Vehículo</th>
                    <th>Imágenes Registradas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aceptados as $a): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($a['conductor_nombre'] . ' ' . $a['conductor_apellido']) ?></strong><br>
                        <?= htmlspecialchars($a['Correo']) ?>
                    </td>
                    <td>
                        <strong>Marca/Mod:</strong> <?= htmlspecialchars($a['Marca'] . ' ' . $a['Modelo']) ?><br>
                        <strong>Patente:</strong> <?= htmlspecialchars($a['Patente']) ?><br>
                        <strong>Color:</strong> <?= htmlspecialchars($a['Color']) ?>
                    </td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <?php if($a['PapelesAuto']): ?><div><img src="<?= $a['PapelesAuto'] ?>" style="max-height: 50px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                            <?php if($a['FotoFrente']): ?><div><img src="<?= $a['FotoFrente'] ?>" style="max-height: 50px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                            <?php if($a['FotoCostado']): ?><div><img src="<?= $a['FotoCostado'] ?>" style="max-height: 50px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                            <?php if($a['FotoAtras']): ?><div><img src="<?= $a['FotoAtras'] ?>" style="max-height: 50px; border: 1px solid #ccc; border-radius: 3px;" class="img-preview"></div><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="vehiculo_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Seguro que deseas ELIMINAR este vehículo? Se cancelarán viajes activos.');">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_paginas > 1): ?>
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
document.querySelectorAll('.img-preview').forEach(img => {
    img.style.cursor = 'pointer';
    img.onclick = () => openModal(img.src);
});
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

</body>
</html>
