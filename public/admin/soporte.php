<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

// Procesar resoluciÃ³n de tickets
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['soporte_id'])) {
    require_csrf();
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

// Filtro y PaginaciÃ³n
$estado_filtro = $_GET['estado'] ?? 'Pendiente'; // Mostrar pendientes por defecto
if (!in_array($estado_filtro, ['Pendiente', 'Resuelto'], true)) {
    $estado_filtro = 'Pendiente';
}

$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$where_sql = "WHERE s.Estado = ?";
$params = [$estado_filtro];

$stmt_totales = $pdo->query("
    SELECT
        SUM(CASE WHEN Estado = 'Pendiente' THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN Estado = 'Resuelto' THEN 1 ELSE 0 END) AS resueltos
    FROM Soporte
");
$totales_soporte = $stmt_totales->fetch(PDO::FETCH_ASSOC) ?: ['pendientes' => 0, 'resueltos' => 0];
$total_pendientes = (int)$totales_soporte['pendientes'];
$total_resueltos = (int)$totales_soporte['resueltos'];

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

    <div class="tabs" style="max-width:620px; margin:20px 0 24px;">
        <a href="soporte.php?estado=Pendiente#soporte-listado" class="tab <?= $estado_filtro === 'Pendiente' ? 'active' : '' ?>">
            Faltan resolver <span class="badge badge-orange" style="margin-left:8px;"><?= $total_pendientes ?></span>
        </a>
        <a href="soporte.php?estado=Resuelto#soporte-listado" class="tab <?= $estado_filtro === 'Resuelto' ? 'active' : '' ?>">
            Resueltos <span class="badge badge-orange" style="margin-left:8px;"><?= $total_resueltos ?></span>
        </a>
    </div>

    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <h3 id="soporte-listado"><?= $estado_filtro === 'Pendiente' ? 'Tickets pendientes' : 'Tickets resueltos' ?></h3>

    <?php if (empty($tickets)): ?>
        <p>No hay tickets en esta categorÃ­a.</p>
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
                            <?= csrf_field() ?>
                            <input type="hidden" name="soporte_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="accion" value="resolver">
                            <button type="submit" class="btn-aprobar" style="width: 100%;">Marcar Resuelto</button>
                        </form>
                        <?php endif; ?>
                        
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="soporte_id" value="<?= $t['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button type="submit" class="btn-rechazar" style="width: 100%;" onclick="return confirm('Â¿Seguro que deseas ELIMINAR este ticket permanentemente?');">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_paginas > 1): ?>
        <div class="pagination">
            <?php if ($pagina > 1): ?>
                <a href="?pagina=<?= $pagina - 1 ?>&estado=<?= urlencode($estado_filtro) ?>#soporte-listado">&laquo; Anterior</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                <a href="?pagina=<?= $i ?>&estado=<?= urlencode($estado_filtro) ?>#soporte-listado" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($pagina < $total_paginas): ?>
                <a href="?pagina=<?= $pagina + 1 ?>&estado=<?= urlencode($estado_filtro) ?>#soporte-listado">Siguiente &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>
