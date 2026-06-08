<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'], $_POST['vehiculo_id'])) {
    require_csrf();
    $vehiculo_id = (int)$_POST['vehiculo_id'];
    $accion = $_POST['accion'];

    if ($accion === 'aprobar') {
        $stmt = $pdo->prepare("UPDATE Vehiculos SET Estado = 'Aceptado' WHERE ID_vehiculo = ?");
        $stmt->execute([$vehiculo_id]);
        $msg = "Vehiculo aprobado con exito.";
    } elseif ($accion === 'reactivar') {
        $stmt_estado = $pdo->prepare("
            SELECT c.BaneadoHasta, c.Estado AS ConductorEstado
            FROM Vehiculos v
            JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
            JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
            WHERE v.ID_vehiculo = ?
            LIMIT 1
        ");
        $stmt_estado->execute([$vehiculo_id]);
        $conductor_estado = $stmt_estado->fetch(PDO::FETCH_ASSOC);

        if (
            $conductor_estado
            && $conductor_estado['ConductorEstado'] === 'Aceptada'
            && (empty($conductor_estado['BaneadoHasta']) || strtotime($conductor_estado['BaneadoHasta']) <= time())
        ) {
            $stmt = $pdo->prepare("UPDATE Vehiculos SET Estado = 'Aceptado' WHERE ID_vehiculo = ? AND Estado = 'Suspendido'");
            $stmt->execute([$vehiculo_id]);
            $msg = "Vehiculo puesto nuevamente en circulacion.";
        } else {
            $msg = "No se puede reactivar este vehiculo porque el conductor sigue suspendido.";
        }
    } elseif ($accion === 'suspender') {
        $stmt = $pdo->prepare("UPDATE Vehiculos SET Estado = 'Suspendido' WHERE ID_vehiculo = ? AND Estado = 'Aceptado'");
        $stmt->execute([$vehiculo_id]);
        $msg = "Vehiculo suspendido correctamente.";
    } elseif ($accion === 'rechazar' || $accion === 'eliminar') {
        try {
            $pdo->beginTransaction();

            $stmt_pub = $pdo->prepare("SELECT ID_publicacion, CiudadOrigen, CiudadDestino, HoraSalida, Precio FROM Publicaciones WHERE ID_vehiculo = ?");
            $stmt_pub->execute([$vehiculo_id]);
            $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

            foreach ($publicaciones as $pub) {
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
                    if (PAYMENTS_ENABLED) {
                        $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")->execute([$pub['Precio'], $res['ID_usuario']]);
                        $mensaje = "Tu viaje de " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] . " fue cancelado por administracion. Se reembolso $" . number_format($pub['Precio'], 2) . " a tu saldo.";
                    } else {
                        $mensaje = "Tu viaje de " . $pub['CiudadOrigen'] . " a " . $pub['CiudadDestino'] . " fue cancelado por administracion.";
                    }
                    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$res['ID_usuario'], $mensaje]);
                }

                $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$pub['ID_publicacion']]);
            }

            $pdo->prepare("UPDATE Vehiculos SET Estado = 'Rechazado' WHERE ID_vehiculo = ?")->execute([$vehiculo_id]);
            $pdo->commit();
            $msg = $accion === 'rechazar' ? "Vehiculo rechazado y viajes cancelados." : "Vehiculo eliminado y viajes cancelados.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = "Error: " . $e->getMessage();
        }
    }
}

$pdo->exec("
    UPDATE Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    SET v.Estado = 'Suspendido'
    WHERE c.Estado = 'Aceptada'
      AND c.BaneadoHasta IS NOT NULL
      AND c.BaneadoHasta > NOW()
      AND v.Estado = 'Aceptado'
");

$search = $_GET['search'] ?? '';
$tipo_vehiculos = $_GET['tipo'] ?? 'pendientes';
if (!in_array($tipo_vehiculos, ['pendientes', 'aprobados', 'suspendidos', 'eliminados'], true)) {
    $tipo_vehiculos = 'pendientes';
}
$conductor_id_filtro = (int)($_GET['conductor_id'] ?? 0);
$search_sql = '';
$params_pendientes = [];
$params_aceptados = [];
$conductor_sql = '';

if ($search !== '') {
    $search_sql = " AND (v.Marca LIKE ? OR v.Modelo LIKE ? OR v.Patente LIKE ? OR u.Nombre LIKE ?) ";
    $params_pendientes = ["%$search%", "%$search%", "%$search%", "%$search%"];
    $params_aceptados = $params_pendientes;
}

if ($conductor_id_filtro > 0) {
    $conductor_sql = " AND c.ID_conductor = ? ";
    $params_pendientes[] = $conductor_id_filtro;
    $params_aceptados[] = $conductor_id_filtro;
}

$stmt_total_pendientes = $pdo->prepare("
    SELECT COUNT(*)
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado = 'Pendiente' $search_sql $conductor_sql
");
$stmt_total_pendientes->execute($params_pendientes);
$total_pendientes = (int)$stmt_total_pendientes->fetchColumn();

$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;

$stmt_total_aprobados = $pdo->prepare("
    SELECT COUNT(*)
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado = 'Aceptado' $search_sql $conductor_sql
");
$stmt_total_aprobados->execute($params_aceptados);
$total_aprobados = (int)$stmt_total_aprobados->fetchColumn();

$stmt_total_suspendidos = $pdo->prepare("
    SELECT COUNT(*)
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado = 'Suspendido' $search_sql $conductor_sql
");
$stmt_total_suspendidos->execute($params_aceptados);
$total_suspendidos = (int)$stmt_total_suspendidos->fetchColumn();

$stmt_total_eliminados = $pdo->prepare("
    SELECT COUNT(*)
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    WHERE v.Estado IN ('Rechazado', 'Eliminado') $search_sql $conductor_sql
");
$stmt_total_eliminados->execute($params_aceptados);
$total_eliminados = (int)$stmt_total_eliminados->fetchColumn();

$total_filtrado = $tipo_vehiculos === 'eliminados' ? $total_eliminados : ($tipo_vehiculos === 'suspendidos' ? $total_suspendidos : $total_aprobados);
$total_paginas = (int)ceil($total_filtrado / $limite);

$base_sql = "
    SELECT v.ID_vehiculo AS id, v.Marca, v.Modelo, v.Color, v.Patente, v.CantidadAsientos,
           v.PapelesAuto, v.FotoFrente, v.FotoCostado, v.FotoAtras, v.Estado,
           c.ID_conductor AS conductor_id, c.BaneadoHasta, c.Estado AS conductor_estado,
           u.Nombre AS conductor_nombre, u.Apellido AS conductor_apellido, u.Correo
    FROM Vehiculos v
    JOIN ConductorVehiculo cv ON v.ID_vehiculo = cv.ID_vehiculo
    JOIN Conductores c ON cv.ID_conductor = c.ID_conductor
    JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
";

$stmt_pendientes = $pdo->prepare($base_sql . " WHERE v.Estado = 'Pendiente' $search_sql $conductor_sql ORDER BY v.ID_vehiculo ASC");
$stmt_pendientes->execute($params_pendientes);
$pendientes = $stmt_pendientes->fetchAll(PDO::FETCH_ASSOC);

$stmt_aprobados = $pdo->prepare($base_sql . " WHERE v.Estado = 'Aceptado' $search_sql $conductor_sql ORDER BY v.ID_vehiculo DESC LIMIT $limite OFFSET $offset");
$stmt_aprobados->execute($params_aceptados);
$aceptados = $stmt_aprobados->fetchAll(PDO::FETCH_ASSOC);

$stmt_suspendidos = $pdo->prepare($base_sql . " WHERE v.Estado = 'Suspendido' $search_sql $conductor_sql ORDER BY v.ID_vehiculo DESC LIMIT $limite OFFSET $offset");
$stmt_suspendidos->execute($params_aceptados);
$suspendidos = $stmt_suspendidos->fetchAll(PDO::FETCH_ASSOC);

$stmt_eliminados = $pdo->prepare($base_sql . " WHERE v.Estado IN ('Rechazado', 'Eliminado') $search_sql $conductor_sql ORDER BY v.ID_vehiculo DESC LIMIT $limite OFFSET $offset");
$stmt_eliminados->execute($params_aceptados);
$eliminados = $stmt_eliminados->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../header.php';
include __DIR__ . '/_nav.php';
$conductor_query = $conductor_id_filtro > 0 ? '&conductor_id=' . urlencode((string)$conductor_id_filtro) : '';
?>

<div style="padding: 20px;">
    <h2>Vehiculos</h2>
    <p>Revisa vehiculos pendientes de aprobacion y gestiona los ya aprobados.</p>

    <div class="tabs" style="max-width:720px; margin:20px 0 24px;">
        <a href="vehiculos.php?tipo=pendientes<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $conductor_query ?>" class="tab <?= $tipo_vehiculos === 'pendientes' ? 'active' : '' ?>">
            Pendientes <span class="badge badge-orange" style="margin-left:8px;"><?= $total_pendientes ?></span>
        </a>
        <a href="vehiculos.php?tipo=aprobados<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $conductor_query ?>#vehiculos-listado" class="tab <?= $tipo_vehiculos === 'aprobados' ? 'active' : '' ?>">
            Aprobados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_aprobados ?></span>
        </a>
        <a href="vehiculos.php?tipo=suspendidos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $conductor_query ?>#vehiculos-listado" class="tab <?= $tipo_vehiculos === 'suspendidos' ? 'active' : '' ?>">
            Suspendidos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_suspendidos ?></span>
        </a>
        <a href="vehiculos.php?tipo=eliminados<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $conductor_query ?>#vehiculos-listado" class="tab <?= $tipo_vehiculos === 'eliminados' ? 'active' : '' ?>">
            Eliminados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_eliminados ?></span>
        </a>
    </div>

    <form method="GET" action="vehiculos.php<?= $tipo_vehiculos !== 'pendientes' ? '#vehiculos-listado' : '' ?>" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 500px;">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_vehiculos) ?>">
        <?php if ($conductor_id_filtro > 0): ?>
            <input type="hidden" name="conductor_id" value="<?= (int)$conductor_id_filtro ?>">
        <?php endif; ?>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por patente, marca o duenio" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Buscar</button>
        <?php if ($search): ?>
            <a href="vehiculos.php?tipo=<?= urlencode($tipo_vehiculos) ?><?= $conductor_query ?><?= $tipo_vehiculos !== 'pendientes' ? '#vehiculos-listado' : '' ?>" style="padding: 10px; background-color: #ccc; color: black; border-radius: 4px; text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if ($conductor_id_filtro > 0): ?>
        <div class="card" style="padding:12px 16px; margin-bottom:18px;">
            Mostrando vehiculos del conductor #<?= (int)$conductor_id_filtro ?>.
            <a href="vehiculos.php?tipo=<?= urlencode($tipo_vehiculos) ?>#vehiculos-listado" style="margin-left:10px;">Ver todos</a>
        </div>
    <?php endif; ?>

    <?php if (isset($msg)): ?>
        <p style="color: green; font-weight: bold;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <?php
    $lista = $pendientes;
    $titulo_lista = 'Vehiculos pendientes';
    $mensaje_vacio = 'pendientes de aprobacion';
    if ($tipo_vehiculos === 'aprobados') {
        $lista = $aceptados;
        $titulo_lista = 'Vehiculos aprobados';
        $mensaje_vacio = 'aprobados';
    } elseif ($tipo_vehiculos === 'suspendidos') {
        $lista = $suspendidos;
        $titulo_lista = 'Vehiculos suspendidos';
        $mensaje_vacio = 'suspendidos';
    } elseif ($tipo_vehiculos === 'eliminados') {
        $lista = $eliminados;
        $titulo_lista = 'Vehiculos eliminados';
        $mensaje_vacio = 'eliminados';
    }
    ?>
    <h3 id="vehiculos-listado"><?= $titulo_lista ?></h3>

    <?php if (empty($lista)): ?>
        <p>No hay vehiculos <?= $mensaje_vacio ?>.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Duenio</th>
                    <th>Detalles vehiculo</th>
                    <th>Imagenes registradas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $v): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars(trim($v['conductor_nombre'] . ' ' . $v['conductor_apellido'])) ?></strong><br>
                        <?= htmlspecialchars($v['Correo']) ?>
                    </td>
                    <td>
                        <strong>Marca/Mod:</strong> <?= htmlspecialchars($v['Marca'] . ' ' . $v['Modelo']) ?><br>
                        <strong>Patente:</strong> <?= htmlspecialchars($v['Patente']) ?><br>
                        <strong>Color:</strong> <?= htmlspecialchars($v['Color']) ?><br>
                        <strong>Asientos:</strong> <?= htmlspecialchars((string)$v['CantidadAsientos']) ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                            <?php if ($v['PapelesAuto']): ?><div><small>Papeles</small><br><img src="<?= htmlspecialchars($v['PapelesAuto']) ?>" style="max-height:80px; border:1px solid #ccc; border-radius:3px;" class="img-preview"></div><?php endif; ?>
                            <?php if ($v['FotoFrente']): ?><div><small>Frente</small><br><img src="<?= htmlspecialchars($v['FotoFrente']) ?>" style="max-height:80px; border:1px solid #ccc; border-radius:3px;" class="img-preview"></div><?php endif; ?>
                            <?php if ($v['FotoCostado']): ?><div><small>Costado</small><br><img src="<?= htmlspecialchars($v['FotoCostado']) ?>" style="max-height:80px; border:1px solid #ccc; border-radius:3px;" class="img-preview"></div><?php endif; ?>
                            <?php if ($v['FotoAtras']): ?><div><small>Atras</small><br><img src="<?= htmlspecialchars($v['FotoAtras']) ?>" style="max-height:80px; border:1px solid #ccc; border-radius:3px;" class="img-preview"></div><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($tipo_vehiculos === 'pendientes'): ?>
                            <form method="post" style="margin-bottom:5px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vehiculo_id" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="accion" value="aprobar">
                                <button type="submit" class="btn-aprobar" onclick="return confirm('Aprobar este vehiculo?');">Aprobar</button>
                            </form>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vehiculo_id" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="accion" value="rechazar">
                                <button type="submit" class="btn-rechazar" onclick="return confirm('Rechazar este vehiculo?');">Rechazar</button>
                            </form>
                        <?php elseif ($tipo_vehiculos === 'aprobados'): ?>
                            <form method="post" style="margin-bottom:5px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vehiculo_id" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="accion" value="suspender">
                                <button type="submit" style="background-color:#f0ad4e; color:white; padding:8px 12px; border:none; cursor:pointer; border-radius:8px; width:100%;" onclick="return confirm('Suspender este vehiculo?');">Suspender</button>
                            </form>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="vehiculo_id" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="accion" value="eliminar">
                                <button type="submit" class="btn-rechazar" onclick="return confirm('Seguro que deseas eliminar este vehiculo? Se cancelaran viajes activos.');">Eliminar</button>
                            </form>
                        <?php elseif ($tipo_vehiculos === 'suspendidos'): ?>
                            <?php $conductor_suspendido = !empty($v['BaneadoHasta']) && strtotime($v['BaneadoHasta']) > time(); ?>
                            <?php if ($conductor_suspendido): ?>
                                <span class="badge badge-orange">Conductor suspendido</span>
                                <div class="text-muted" style="margin-top:8px; font-size:14px;">
                                    Hasta <?= date('d/m/Y H:i', strtotime($v['BaneadoHasta'])) ?>.
                                </div>
                            <?php else: ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="vehiculo_id" value="<?= (int)$v['id'] ?>">
                                    <input type="hidden" name="accion" value="reactivar">
                                    <button type="submit" class="btn-aprobar" onclick="return confirm('Poner este vehiculo nuevamente en circulacion?');">Poner en circulacion</button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-orange">Eliminado</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($tipo_vehiculos !== 'pendientes' && $total_paginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="?tipo=<?= urlencode($tipo_vehiculos) ?>&pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?><?= $conductor_query ?>#vehiculos-listado">&laquo; Anterior</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?tipo=<?= urlencode($tipo_vehiculos) ?>&pagina=<?= $i ?>&search=<?= urlencode($search) ?><?= $conductor_query ?>#vehiculos-listado" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
                <a href="?tipo=<?= urlencode($tipo_vehiculos) ?>&pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?><?= $conductor_query ?>#vehiculos-listado">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="imageModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.8); align-items:center; justify-content:center;">
    <span onclick="document.getElementById('imageModal').style.display='none'" style="position:absolute; top:20px; right:35px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer;">&times;</span>
    <img id="modalImage" style="max-width:90%; max-height:90%; object-fit:contain; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.5);">
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
    if (e.target === this) this.style.display = 'none';
});
</script>

</body>
</html>
