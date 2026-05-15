<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Procesar resolución de tickets
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['soporte_id'])) {
    $soporte_id = (int)$_POST['soporte_id'];
    
    if ($_POST['accion'] === 'resolver') {
        $stmt_upd = $pdo->prepare("UPDATE Soporte SET Estado = 'Resuelto' WHERE ID_soporte = ?");
        $stmt_upd->execute([$soporte_id]);
        $msg_exito = "Ticket marcado como Resuelto.";
    } elseif ($_POST['accion'] === 'eliminar') {
        $stmt_del = $pdo->prepare("DELETE FROM Soporte WHERE ID_soporte = ?");
        $stmt_del->execute([$soporte_id]);
        $msg_exito = "Ticket eliminado.";
    }
}

// Filtro y Paginación
$estado_filtro = $_GET['estado'] ?? 'Pendiente'; // Mostrar pendientes por defecto

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$where_sql = "";
$params = [];
if ($estado_filtro !== 'Todos') {
    $where_sql = "WHERE s.Estado = ?";
    $params[] = $estado_filtro;
}

$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM Soporte s $where_sql");
$stmt_count->execute($params);
$total_registros = $stmt_count->fetchColumn();
$total_paginas = ceil($total_registros / $limite);

// Obtener tickets
$sql = "
    SELECT s.ID_soporte AS id, s.Asunto, s.Mensaje, s.Fecha, s.Estado,
           u.Nombre, u.Apellido, u.Correo
    FROM Soporte s
    JOIN Usuarios u ON s.ID_usuario = u.ID_usuario
    $where_sql
    ORDER BY s.Fecha DESC
    LIMIT $limite OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2>Tickets de Soporte al Usuario</h2>
    <p>Revisa y marca como resueltos los problemas reportados por los usuarios en la web.</p>

    <form method="GET" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 400px;">
        <select name="estado" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc;">
            <option value="Pendiente" <?= $estado_filtro === 'Pendiente' ? 'selected' : '' ?>>Pendientes de resolución</option>
            <option value="Resuelto" <?= $estado_filtro === 'Resuelto' ? 'selected' : '' ?>>Tickets Resueltos</option>
            <option value="Todos" <?= $estado_filtro === 'Todos' ? 'selected' : '' ?>>Ver Todos</option>
        </select>
        <button type="submit" style="padding: 10px 20px; background-color: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer;">Filtrar</button>
    </form>

    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if (empty($tickets)): ?>
        <p>No hay tickets en esta categoría.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th style="width: 150px;">Fecha y Usuario</th>
                    <th>Asunto y Mensaje</th>
                    <th>Estado</th>
                    <th style="width: 150px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td>
                        <?= date('d/m/Y H:i', strtotime($t['Fecha'])) ?><br><br>
                        <strong><?= htmlspecialchars($t['Nombre'] . ' ' . $t['Apellido']) ?></strong><br>
                        <small><?= htmlspecialchars($t['Correo']) ?></small>
                    </td>
                    <td>
                        <strong style="font-size: 1.1em; color: var(--primary);"><?= htmlspecialchars($t['Asunto']) ?></strong>
                        <p style="margin-top: 10px; color: #475569; background: #f8fafc; padding: 10px; border-radius: 4px; border-left: 3px solid #cbd5e1;"><?= nl2br(htmlspecialchars($t['Mensaje'])) ?></p>
                    </td>
                    <td>
                        <?php if ($t['Estado'] === 'Pendiente'): ?>
                            <span style="background: #fef9c3; color: #854d0e; padding: 3px 8px; border-radius: 20px; font-weight: bold; font-size: 0.85em;">PENDIENTE</span>
                        <?php else: ?>
                            <span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 20px; font-weight: bold; font-size: 0.85em;">RESUELTO</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if ($t['Estado'] === 'Pendiente'): ?>
                        <form method="post" style="margin-bottom: 5px;">
                            <input type="hidden" name="soporte_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="accion" value="resolver">
                            <button type="submit" class="btn-aprobar" style="width: 100%;">Marcar Resuelto</button>
                        </form>
                        <?php endif; ?>
                        
                        <form method="post">
                            <input type="hidden" name="soporte_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="btn-rechazar" style="width: 100%;" onclick="return confirm('¿Seguro que deseas ELIMINAR este ticket permanentemente?');">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?>&estado=<?= urlencode($estado_filtro) ?>">&laquo; Anterior</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?pagina=<?= $i ?>&estado=<?= urlencode($estado_filtro) ?>" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?>&estado=<?= urlencode($estado_filtro) ?>">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
