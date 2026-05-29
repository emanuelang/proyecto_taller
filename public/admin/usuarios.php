<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/account_lifecycle.php';

function dni_img_src(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    if (strpos($value, 'data:image/') === 0) {
        return $value;
    }

    return BASE_URL . ltrim($value, '/');
}

// Procesar eliminacion de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['usuario_id'])) {
    require_csrf();
    $usuario_target = (int)$_POST['usuario_id'];
    $accion = $_POST['accion'];
    
    // Safety check: Cannot delete yourself
    if ($accion === 'eliminar_usuario' || $accion === 'banear_usuario' || $accion === 'quitar_suspension') {
        if ($usuario_target === $_SESSION['user_id']) {
            $msg_error = "No puedes aplicarte sanciones a ti mismo.";
        } else {
            $stmt_check_admin = $pdo->prepare("SELECT * FROM Administradores WHERE ID_usuario = ?");
            $stmt_check_admin->execute([$usuario_target]);
            if ($stmt_check_admin->fetch()) {
                $msg_error = "No puedes sancionar o eliminar a otro administrador.";
            } else {
                if ($accion === 'eliminar_usuario') {
                    try {
                        $pdo->beginTransaction();
                        deactivate_user_account($pdo, $usuario_target, 'El usuario fue desactivado por administracion.');
                        $pdo->commit();
                        $msg_exito = "Usuario desactivado correctamente. Sus viajes y reservas activas fueron cancelados.";
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log("Error al desactivar usuario desde admin: " . $e->getMessage());
                        $msg_error = "No se pudo desactivar el usuario.";
                    }
                } elseif ($accion === 'banear_usuario') {
                    $fecha_ban = $_POST['fecha_ban'] ?? '';
                    if (!empty($fecha_ban)) {
                        $stmt_ban = $pdo->prepare("UPDATE Usuarios SET BaneadoHasta = ? WHERE ID_usuario = ?");
                        $stmt_ban->execute([$fecha_ban, $usuario_target]);
                        $msg_exito = "Usuario suspendido correctamente hasta el $fecha_ban.";
                    }
                } elseif ($accion === 'quitar_suspension') {
                    $stmt_ban = $pdo->prepare("UPDATE Usuarios SET BaneadoHasta = NULL WHERE ID_usuario = ?");
                    $stmt_ban->execute([$usuario_target]);
                    $msg_exito = "Suspension quitada correctamente.";
                }
            }
        }
    }
}

// Filtro de busqueda
$search = $_GET['search'] ?? '';
$tipo_usuarios = $_GET['tipo'] ?? 'activos';
if (!in_array($tipo_usuarios, ['activos', 'suspendidos', 'eliminados'], true)) {
    $tipo_usuarios = 'activos';
}
$viaje_filtro = max(0, (int)($_GET['viaje_id'] ?? 0));
$filtrando_pasajeros_viaje = $viaje_filtro > 0;
$search_sql = '';
$params = [];
$viaje_join = '';
$viaje_params = [];

if ($search !== '') {
    $search_sql = " AND (u.Nombre LIKE ? OR u.DNI LIKE ? OR u.Correo LIKE ?) ";
    $params = ["%$search%", "%$search%", "%$search%"];
}

if ($filtrando_pasajeros_viaje) {
    $viaje_join = "
        JOIN Pasajeros pas_filtro ON pas_filtro.ID_usuario = u.ID_usuario
        JOIN PasajerosReservas pr_filtro ON pr_filtro.ID_pasajero = pas_filtro.ID_pasajero
        JOIN Reservas r_filtro ON r_filtro.ID_reserva = pr_filtro.ID_reserva
    ";
    $search_sql .= " AND r_filtro.ID_publicacion = ? AND r_filtro.Estado = 'Completada' ";
    $viaje_params[] = $viaje_filtro;
}

$query_params = array_merge($params, $viaje_params);

$deleted_user_sql = "(u.Correo LIKE 'deleted\\_%@deleted.moveon.local' OR u.DNI LIKE 'deleted\\_%')";
$estado_usuario_sql = "(u.BaneadoHasta IS NULL OR u.BaneadoHasta <= NOW()) AND NOT $deleted_user_sql";
if ($tipo_usuarios === 'suspendidos') {
    $estado_usuario_sql = "NOT $deleted_user_sql AND u.BaneadoHasta > NOW()";
} elseif ($tipo_usuarios === 'eliminados') {
    $estado_usuario_sql = $deleted_user_sql;
}
if ($filtrando_pasajeros_viaje) {
    $estado_usuario_sql = "1 = 1";
}
$admin_join_sql = $filtrando_pasajeros_viaje ? "" : "LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario";
$admin_where_sql = $filtrando_pasajeros_viaje ? "" : "AND a.ID_administrador IS NULL";

$count_estado_sql = "
    SELECT
        COUNT(DISTINCT CASE WHEN (u.BaneadoHasta IS NULL OR u.BaneadoHasta <= NOW()) AND NOT $deleted_user_sql THEN u.ID_usuario END) AS activos,
        COUNT(DISTINCT CASE WHEN NOT $deleted_user_sql AND u.BaneadoHasta > NOW() THEN u.ID_usuario END) AS suspendidos,
        COUNT(DISTINCT CASE WHEN $deleted_user_sql THEN u.ID_usuario END) AS eliminados
    FROM Usuarios u
    $viaje_join
    $admin_join_sql
    WHERE 1 = 1 $admin_where_sql $search_sql
";
$stmt_estado_count = $pdo->prepare($count_estado_sql);
$stmt_estado_count->execute($query_params);
$totales_estado = $stmt_estado_count->fetch(PDO::FETCH_ASSOC) ?: ['activos' => 0, 'suspendidos' => 0, 'eliminados' => 0];
$total_activos = (int)$totales_estado['activos'];
$total_suspendidos = (int)$totales_estado['suspendidos'];
$total_eliminados = (int)$totales_estado['eliminados'];

// Paginacion
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$count_sql = "SELECT COUNT(DISTINCT u.ID_usuario) FROM Usuarios u $viaje_join $admin_join_sql WHERE $estado_usuario_sql $admin_where_sql $search_sql";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($query_params);
$total_paginas = ceil($stmt_count->fetchColumn() / $limite);

// Obtener la lista de usuarios (que no sean administradores)
$sql = "
SELECT DISTINCT u.ID_usuario AS id, u.Nombre, u.Apellido, u.Correo, u.Telefono, u.DNI, u.DniFrenteImagen, u.DniDorsoImagen, u.BaneadoHasta,
       (SELECT COUNT(*) FROM Conductores WHERE ID_usuario = u.ID_usuario AND Estado = 'Aceptada') AS es_conductor,
       (
            SELECT COUNT(*)
            FROM ReportesPasajeros rp_count
            WHERE rp_count.ID_usuario_reportado = u.ID_usuario
       ) AS reportes_asociados,
       (
            SELECT CONCAT_WS('||',
                rp.ID_reporte_pasajero,
                rp.Fecha,
                rp.Motivo,
                COALESCE(rp.Descripcion, ''),
                COALESCE(p.CiudadOrigen, ''),
                COALESCE(p.CiudadDestino, ''),
                COALESCE(p.HoraSalida, ''),
                COALESCE(p.ID_publicacion, 0)
            )
            FROM ReportesPasajeros rp
            JOIN Reservas r ON rp.ID_reserva = r.ID_reserva
            JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
            WHERE rp.ID_usuario_reportado = u.ID_usuario
            ORDER BY rp.Fecha DESC
            LIMIT 1
       ) AS reporte_asociado
FROM Usuarios u
$viaje_join
$admin_join_sql
WHERE $estado_usuario_sql $admin_where_sql $search_sql
ORDER BY u.ID_usuario DESC
LIMIT $limite OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($query_params);
$usuarios = $stmt->fetchAll();
$viaje_query = $filtrando_pasajeros_viaje ? '&viaje_id=' . urlencode((string)$viaje_filtro) : '';
$viaje_info = null;
if ($filtrando_pasajeros_viaje) {
    $stmt_viaje_info = $pdo->prepare("
        SELECT CiudadOrigen, CiudadDestino, HoraSalida, Estado
        FROM Publicaciones
        WHERE ID_publicacion = ?
    ");
    $stmt_viaje_info->execute([$viaje_filtro]);
    $viaje_info = $stmt_viaje_info->fetch(PDO::FETCH_ASSOC) ?: null;
}
require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2><?= $filtrando_pasajeros_viaje ? 'Pasajeros del viaje' : 'Gestión de Usuarios' ?></h2>
    <p>
        <?php if ($filtrando_pasajeros_viaje): ?>
            Lista de pasajeros con reserva confirmada para este viaje.
        <?php else: ?>
            Lista de todos los usuarios estándar registrados (pasajeros/conductores). Desde aquí puedes expulsarlos del sistema.
        <?php endif; ?>
    </p>

    <?php if (!$filtrando_pasajeros_viaje): ?>
        <div class="tabs" style="max-width:720px; margin:20px 0 24px;">
            <a href="usuarios.php?tipo=activos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $viaje_query ?>#usuarios-listado" class="tab <?= $tipo_usuarios === 'activos' ? 'active' : '' ?>">
                Activos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_activos ?></span>
            </a>
            <a href="usuarios.php?tipo=suspendidos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $viaje_query ?>#usuarios-listado" class="tab <?= $tipo_usuarios === 'suspendidos' ? 'active' : '' ?>">
                Suspendidos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_suspendidos ?></span>
            </a>
            <a href="usuarios.php?tipo=eliminados<?= $search !== '' ? '&search=' . urlencode($search) : '' ?><?= $viaje_query ?>#usuarios-listado" class="tab <?= $tipo_usuarios === 'eliminados' ? 'active' : '' ?>">
                Eliminados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_eliminados ?></span>
            </a>
        </div>
    <?php endif; ?>
    
    <form method="GET" action="usuarios.php#usuarios-listado" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 500px;">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_usuarios) ?>">
        <?php if ($viaje_filtro > 0): ?>
            <input type="hidden" name="viaje_id" value="<?= (int)$viaje_filtro ?>">
        <?php endif; ?>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por Nombre, DNI o Correo" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Buscar</button>
        <?php if($search): ?>
            <a href="usuarios.php?tipo=<?= urlencode($tipo_usuarios) ?><?= $viaje_query ?>#usuarios-listado" style="padding: 10px; background-color: #ccc; color: black; border-radius: 4px; text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if ($filtrando_pasajeros_viaje): ?>
        <div class="card" style="padding:12px 16px; margin-bottom:18px;">
            <strong>Viaje #<?= (int)$viaje_filtro ?></strong>
            <?php if ($viaje_info): ?>
                - <?= htmlspecialchars($viaje_info['CiudadOrigen']) ?> -> <?= htmlspecialchars($viaje_info['CiudadDestino']) ?>
                (<?= date('d/m/Y H:i', strtotime($viaje_info['HoraSalida'])) ?>, <?= htmlspecialchars($viaje_info['Estado']) ?>)
            <?php endif; ?>
            <a href="viajes.php?tipo=activos" style="margin-left:10px;">Volver a viajes activos</a>
            <a href="viajes.php?tipo=finalizados" style="margin-left:10px;">Volver a viajes finalizados</a>
            <a href="usuarios.php?tipo=<?= urlencode($tipo_usuarios) ?>#usuarios-listado" style="margin-left:10px;">Ver todos los usuarios</a>
        </div>
    <?php endif; ?>
    
    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if (isset($msg_error)): ?>
        <p style="color: red; font-weight: bold; background: #ffebee; padding: 10px; border: 1px solid #ffcdd2;"><?= htmlspecialchars($msg_error) ?></p>
    <?php endif; ?>

    <h3 id="usuarios-listado"><?= $filtrando_pasajeros_viaje ? 'Pasajeros confirmados' : ($tipo_usuarios === 'activos' ? 'Usuarios activos' : ($tipo_usuarios === 'suspendidos' ? 'Usuarios suspendidos' : 'Usuarios eliminados')) ?></h3>

    <?php if (empty($usuarios)): ?>
        <p><?= $filtrando_pasajeros_viaje ? 'Este viaje no tiene pasajeros con reserva confirmada.' : 'No hay usuarios estándar registrados en la plataforma.' ?></p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre y Apellido</th>
                    <th>Contacto</th>
                    <th>DNI</th>
                    <th>Imagen DNI</th>
                    <th>Rol Principal</th>
                    <?php if ($tipo_usuarios !== 'activos'): ?>
                        <th>Reporte asociado</th>
                    <?php endif; ?>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['Nombre'] . ' ' . $u['Apellido']) ?></td>
                    <td>
                        <strong>Email:</strong> <?= htmlspecialchars($u['Correo']) ?><br>
                        <strong>Tel:</strong> <?= htmlspecialchars($u['Telefono'] ?? '---') ?>
                    </td>
                    <td><?= htmlspecialchars($u['DNI']) ?></td>
                    <td>
                        <?php $dni_frente_src = dni_img_src($u['DniFrenteImagen'] ?? ''); ?>
                        <?php $dni_dorso_src = dni_img_src($u['DniDorsoImagen'] ?? ''); ?>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <?php if ($dni_frente_src !== ''): ?>
                                <img src="<?= htmlspecialchars($dni_frente_src) ?>" alt="DNI frente" class="dni-preview" style="width:120px; height:78px; object-fit:cover; border-radius:8px; border:1px solid var(--border-color); cursor:pointer;">
                            <?php else: ?>
                                <span class="text-muted">Sin frente</span>
                            <?php endif; ?>

                            <?php if ($dni_dorso_src !== ''): ?>
                                <img src="<?= htmlspecialchars($dni_dorso_src) ?>" alt="DNI dorso" class="dni-preview" style="width:120px; height:78px; object-fit:cover; border-radius:8px; border:1px solid var(--border-color); cursor:pointer;">
                            <?php else: ?>
                                <span class="text-muted">Sin dorso</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($u['es_conductor'] > 0): ?>
                            <strong style="color: #0275d8;">Conductor</strong> / Pasajero
                        <?php else: ?>
                            Pasajero
                        <?php endif; ?>
                        
                        <?php if ($u['BaneadoHasta'] && strtotime($u['BaneadoHasta']) > time()): ?>
                            <br><span style="color: red; font-size: 0.85em; font-weight: bold;">Suspendido hasta:<br><?= date('d/m/Y H:i', strtotime($u['BaneadoHasta'])) ?></span>
                        <?php endif; ?>
                        <?php if ($tipo_usuarios === 'eliminados'): ?>
                            <br><span class="badge badge-orange" style="margin-top:8px;">Eliminado</span>
                        <?php endif; ?>
                    </td>
                    <?php if ($tipo_usuarios !== 'activos'): ?>
                        <td>
                            <?php
                                $reporte = $u['reporte_asociado'] ? explode('||', (string)$u['reporte_asociado']) : [];
                                $tiene_reporte = count($reporte) >= 8 && (int)$u['reportes_asociados'] > 0;
                            ?>
                            <?php if ($tiene_reporte): ?>
                                <strong>Reporte #<?= (int)$reporte[0] ?></strong><br>
                                <span class="text-muted"><?= date('d/m/Y H:i', strtotime($reporte[1])) ?></span><br>
                                <strong><?= htmlspecialchars($reporte[2]) ?></strong><br>
                                <span><?= nl2br(htmlspecialchars($reporte[3] !== '' ? $reporte[3] : 'Sin detalle adicional.')) ?></span><br>
                                <span class="text-muted">
                                    Viaje #<?= (int)$reporte[7] ?>:
                                    <?= htmlspecialchars($reporte[4]) ?> -> <?= htmlspecialchars($reporte[5]) ?>
                                    <?php if ($reporte[6] !== ''): ?>
                                        (<?= date('d/m/Y H:i', strtotime($reporte[6])) ?>)
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Sin reporte de viaje asociado</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td style="text-align: center;">
                        <?php if ($tipo_usuarios === 'eliminados'): ?>
                            <span class="badge badge-orange">Eliminado permanente</span>
                        <?php else: ?>
                        <?php $esta_suspendido = !empty($u['BaneadoHasta']) && strtotime($u['BaneadoHasta']) > time(); ?>
                        <?php if ($esta_suspendido): ?>
                            <form method="post" style="margin-bottom: 8px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="usuario_id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="accion" value="quitar_suspension">
                                <button type="submit" class="btn btn-outline" style="width: 100%; font-size: 0.85em;" onclick="return confirm('Quitar la suspension de este usuario?');">Quitar suspension</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="margin-bottom: 5px; text-align: left; background: #f9f9f9; padding: 5px; border: 1px solid #ddd;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="accion" value="banear_usuario">
                                <label style="font-size: 0.8em; font-weight: bold;">Suspender hasta:</label><br>
                                <input type="datetime-local" name="fecha_ban" required style="width: 100%; box-sizing: border-box; margin-bottom: 5px; font-size: 0.85em;">
                                <button type="submit" style="background-color: #f0ad4e; color: white; padding: 4px; border: none; cursor: pointer; border-radius: 3px; width: 100%; font-size: 0.85em;">Suspender Usuario</button>
                            </form>
                        <?php endif; ?>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar_usuario">
                            <button type="submit" class="btn-rechazar" style="width: 100%; font-size: 0.85em;" onclick="return confirm('ATENCIÓN: ¿Seguro que deseas ELIMINAR a este usuario permanentemente?');">Eliminar usuario</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (isset($total_paginas) && $total_paginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?tipo=<?= urlencode($tipo_usuarios) ?>&pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?><?= $viaje_query ?>#usuarios-listado">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?tipo=<?= urlencode($tipo_usuarios) ?>&pagina=<?= $i ?>&search=<?= urlencode($search) ?><?= $viaje_query ?>#usuarios-listado" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?tipo=<?= urlencode($tipo_usuarios) ?>&pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?><?= $viaje_query ?>#usuarios-listado">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="dniModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.82); align-items:center; justify-content:center;">
    <button type="button" id="dniModalClose" aria-label="Cerrar imagen" style="position:absolute; top:28px; right:38px; color:#fff; background:transparent; border:none; font-size:46px; font-weight:bold; line-height:1; cursor:pointer;">&times;</button>
    <img id="dniModalImage" alt="Imagen DNI ampliada" style="max-width:82%; max-height:82%; object-fit:contain; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.5);">
</div>

<script>
const dniModal = document.getElementById('dniModal');
const dniModalImage = document.getElementById('dniModalImage');
const dniModalClose = document.getElementById('dniModalClose');

document.querySelectorAll('.dni-preview').forEach(img => {
    img.addEventListener('click', () => {
        dniModalImage.src = img.src;
        dniModalImage.alt = img.alt || 'Imagen DNI ampliada';
        dniModal.style.display = 'flex';
    });
});

function closeDniModal() {
    dniModal.style.display = 'none';
    dniModalImage.src = '';
}

dniModalClose.addEventListener('click', closeDniModal);
dniModal.addEventListener('click', event => {
    if (event.target === dniModal) {
        closeDniModal();
    }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && dniModal.style.display === 'flex') {
        closeDniModal();
    }
});
</script>

</body>
</html>
