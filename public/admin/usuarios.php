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

// Procesar eliminaciÃ³n de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['usuario_id'])) {
    require_csrf();
    $usuario_target = (int)$_POST['usuario_id'];
    $accion = $_POST['accion'];
    
    // Safety check: Cannot delete yourself
    if ($accion === 'eliminar_usuario' || $accion === 'banear_usuario') {
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
                }
            }
        }
    }
}

// Filtro de bÃºsqueda
$search = $_GET['search'] ?? '';
$tipo_usuarios = $_GET['tipo'] ?? 'activos';
if (!in_array($tipo_usuarios, ['activos', 'suspendidos', 'eliminados'], true)) {
    $tipo_usuarios = 'activos';
}
$search_sql = '';
$params = [];

if ($search !== '') {
    $search_sql = " AND (u.Nombre LIKE ? OR u.DNI LIKE ? OR u.Correo LIKE ?) ";
    $params = ["%$search%", "%$search%", "%$search%"];
}

$deleted_user_sql = "(u.Correo LIKE 'deleted\\_%@deleted.moveon.local' OR u.DNI LIKE 'deleted\\_%')";
$estado_usuario_sql = "COALESCE(u.estado, 'activo') = 'activo' AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta <= NOW()) AND NOT $deleted_user_sql";
if ($tipo_usuarios === 'suspendidos') {
    $estado_usuario_sql = "NOT $deleted_user_sql AND (u.BaneadoHasta > NOW() OR u.estado IN ('suspendido', 'baneado'))";
} elseif ($tipo_usuarios === 'eliminados') {
    $estado_usuario_sql = $deleted_user_sql;
}

$count_estado_sql = "
    SELECT
        SUM(CASE WHEN COALESCE(u.estado, 'activo') = 'activo' AND (u.BaneadoHasta IS NULL OR u.BaneadoHasta <= NOW()) AND NOT $deleted_user_sql THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN NOT $deleted_user_sql AND (u.BaneadoHasta > NOW() OR u.estado IN ('suspendido', 'baneado')) THEN 1 ELSE 0 END) AS suspendidos,
        SUM(CASE WHEN $deleted_user_sql THEN 1 ELSE 0 END) AS eliminados
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL $search_sql
";
$stmt_estado_count = $pdo->prepare($count_estado_sql);
$stmt_estado_count->execute($params);
$totales_estado = $stmt_estado_count->fetch(PDO::FETCH_ASSOC) ?: ['activos' => 0, 'suspendidos' => 0, 'eliminados' => 0];
$total_activos = (int)$totales_estado['activos'];
$total_suspendidos = (int)$totales_estado['suspendidos'];
$total_eliminados = (int)$totales_estado['eliminados'];

// PaginaciÃ³n
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$count_sql = "SELECT COUNT(*) FROM Usuarios u LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario WHERE a.ID_administrador IS NULL AND $estado_usuario_sql $search_sql";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_paginas = ceil($stmt_count->fetchColumn() / $limite);

// Obtener la lista de usuarios (que no sean administradores)
$sql = "
SELECT u.ID_usuario AS id, u.Nombre, u.Apellido, u.Correo, u.Telefono, u.DNI, u.DniFrenteImagen, u.DniDorsoImagen, u.BaneadoHasta, u.estado,
       (SELECT COUNT(*) FROM Conductores WHERE ID_usuario = u.ID_usuario AND Estado = 'Aceptada') AS es_conductor
FROM Usuarios u
LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
WHERE a.ID_administrador IS NULL AND $estado_usuario_sql $search_sql
ORDER BY u.ID_usuario DESC
LIMIT $limite OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();
require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2>GestiÃ³n de Usuarios</h2>
    <p>Lista de todos los usuarios estÃ¡ndar registrados (pasajeros/conductores). Desde aquÃ­ puedes expulsarlos del sistema.</p>

    <div class="tabs" style="max-width:720px; margin:20px 0 24px;">
        <a href="usuarios.php?tipo=activos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#usuarios-listado" class="tab <?= $tipo_usuarios === 'activos' ? 'active' : '' ?>">
            Activos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_activos ?></span>
        </a>
        <a href="usuarios.php?tipo=suspendidos<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#usuarios-listado" class="tab <?= $tipo_usuarios === 'suspendidos' ? 'active' : '' ?>">
            Suspendidos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_suspendidos ?></span>
        </a>
        <a href="usuarios.php?tipo=eliminados<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>#usuarios-listado" class="tab <?= $tipo_usuarios === 'eliminados' ? 'active' : '' ?>">
            Eliminados <span class="badge badge-orange" style="margin-left:8px;"><?= $total_eliminados ?></span>
        </a>
    </div>
    
    <form method="GET" action="usuarios.php#usuarios-listado" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 500px;">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo_usuarios) ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por Nombre, DNI o Correo" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
        <button type="submit" style="padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Buscar</button>
        <?php if($search): ?>
            <a href="usuarios.php?tipo=<?= urlencode($tipo_usuarios) ?>#usuarios-listado" style="padding: 10px; background-color: #ccc; color: black; border-radius: 4px; text-decoration: none;">Limpiar</a>
        <?php endif; ?>
    </form>
    
    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if (isset($msg_error)): ?>
        <p style="color: red; font-weight: bold; background: #ffebee; padding: 10px; border: 1px solid #ffcdd2;"><?= htmlspecialchars($msg_error) ?></p>
    <?php endif; ?>

    <h3 id="usuarios-listado"><?= $tipo_usuarios === 'activos' ? 'Usuarios activos' : ($tipo_usuarios === 'suspendidos' ? 'Usuarios suspendidos' : 'Usuarios eliminados') ?></h3>

    <?php if (empty($usuarios)): ?>
        <p>No hay usuarios estÃ¡ndar registrados en la plataforma.</p>
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
                            <br><span style="color: red; font-size: 0.85em; font-weight: bold;">Baneado hasta:<br><?= date('d/m/Y H:i', strtotime($u['BaneadoHasta'])) ?></span>
                        <?php endif; ?>
                        <?php if ($tipo_usuarios === 'eliminados'): ?>
                            <br><span class="badge badge-orange" style="margin-top:8px;">Eliminado</span>
                        <?php elseif ($tipo_usuarios === 'suspendidos' && (!$u['BaneadoHasta'] || strtotime($u['BaneadoHasta']) <= time())): ?>
                            <br><span class="badge badge-orange" style="margin-top:8px;">Suspendido</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($tipo_usuarios === 'eliminados'): ?>
                            <span class="badge badge-orange">Eliminado permanente</span>
                        <?php else: ?>
                        <form method="post" style="margin-bottom: 5px; text-align: left; background: #f9f9f9; padding: 5px; border: 1px solid #ddd;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="accion" value="banear_usuario">
                            <label style="font-size: 0.8em; font-weight: bold;">Suspender hasta:</label><br>
                            <input type="datetime-local" name="fecha_ban" required style="width: 100%; box-sizing: border-box; margin-bottom: 5px; font-size: 0.85em;">
                            <button type="submit" style="background-color: #f0ad4e; color: white; padding: 4px; border: none; cursor: pointer; border-radius: 3px; width: 100%; font-size: 0.85em;">Suspender Usuario</button>
                        </form>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar_usuario">
                            <button type="submit" class="btn-rechazar" style="width: 100%; font-size: 0.85em;" onclick="return confirm('ATENCIÃ“N: Â¿Seguro que deseas ELIMINAR a este usuario permanentemente?');">Eliminar Permanente</button>
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
            <a href="?tipo=<?= urlencode($tipo_usuarios) ?>&pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>#usuarios-listado">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?tipo=<?= urlencode($tipo_usuarios) ?>&pagina=<?= $i ?>&search=<?= urlencode($search) ?>#usuarios-listado" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?tipo=<?= urlencode($tipo_usuarios) ?>&pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>#usuarios-listado">Siguiente &raquo;</a>
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
