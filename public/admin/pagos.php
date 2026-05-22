<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

// Filtros
$estado_filtro = $_GET['estado'] ?? '';
$search = $_GET['search'] ?? '';

$where_clauses = [];
$params = [];

if ($estado_filtro !== '') {
    $where_clauses[] = "p.Estado = ?";
    $params[] = $estado_filtro;
}
if ($search !== '') {
    $where_clauses[] = "(u_pasajero.Nombre LIKE ? OR u_pasajero.Apellido LIKE ? OR r.ID_reserva = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = $search;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Paginación
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 10;
$offset = ($pagina - 1) * $limite;

$count_sql = "
    SELECT COUNT(*) 
    FROM Pagos p
    JOIN Reservas r ON p.ID_reserva = r.ID_reserva
    JOIN Publicaciones pub ON r.ID_publicacion = pub.ID_publicacion
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Usuarios u_pasajero ON pas.ID_usuario = u_pasajero.ID_usuario
    $where_sql
";
$stmt_count = $pdo->prepare($count_sql);
$stmt_count->execute($params);
$total_paginas = ceil($stmt_count->fetchColumn() / $limite);

$sql = "
    SELECT p.ID_pago, p.Monto, p.Estado as EstadoPago, p.Fecha,
           r.ID_reserva, pub.CiudadOrigen, pub.CiudadDestino, pub.Precio as PrecioViaje,
           u_pasajero.Nombre as PasajeroNombre, u_pasajero.Apellido as PasajeroApellido,
           u_conductor.Nombre as ConductorNombre, u_conductor.Apellido as ConductorApellido
    FROM Pagos p
    JOIN Reservas r ON p.ID_reserva = r.ID_reserva
    JOIN Publicaciones pub ON r.ID_publicacion = pub.ID_publicacion
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    JOIN Usuarios u_pasajero ON pas.ID_usuario = u_pasajero.ID_usuario
    JOIN ConductorPublicacion cp ON pub.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Usuarios u_conductor ON c.ID_usuario = u_conductor.ID_usuario
    $where_sql
    ORDER BY p.Fecha DESC
    LIMIT $limite OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Manejo de Retiros
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'retirar') {
    require_csrf();
    $monto_a_retirar = (float)$_POST['monto'];
    
    // Calcular ganancia neta actual antes de retirar
    $stmt_bruto = $pdo->query("SELECT SUM(Monto) FROM Pagos WHERE Estado = 'Completado'");
    $bruto = $stmt_bruto->fetchColumn() ?: 0;
    $stmt_retirado = $pdo->query("SELECT SUM(Monto) FROM RetirosAdmin");
    $retirado = $stmt_retirado->fetchColumn() ?: 0;
    $disponible = ($bruto * 0.10) - $retirado;

    if ($monto_a_retirar > 0 && $monto_a_retirar <= $disponible) {
        $stmt_ins = $pdo->prepare("INSERT INTO RetirosAdmin (Monto) VALUES (?)");
        $stmt_ins->execute([$monto_a_retirar]);
        $success_msg = "Retiro de $" . number_format($monto_a_retirar, 2) . " realizado con éxito.";
    } else {
        $error_msg = "Monto inválido o superior al disponible.";
    }
}

// Cálculo de ganancias totales
$stmt_ganancias = $pdo->query("SELECT SUM(Monto) FROM Pagos WHERE Estado = 'Completado'");
$total_bruto = $stmt_ganancias->fetchColumn() ?: 0;
$ganancia_neta_total = $total_bruto * 0.10; // 10% retención total histórica

$stmt_total_retirado = $pdo->query("SELECT SUM(Monto) FROM RetirosAdmin");
$total_retirado = $stmt_total_retirado->fetchColumn() ?: 0;

$ganancia_disponible = $ganancia_neta_total - $total_retirado;

require_once __DIR__ . '/../header.php';
?>

<?php include __DIR__ . '/_nav.php'; ?>

<div style="padding: 20px;">
    <h2>Historial de Pagos y Finanzas</h2>
    <p>Monitoriza todas las transacciones procesadas por la pasarela de pagos. Las comisiones del 10% se retienen automáticamente de los pagos en estado "Completado".</p>

    <?php if (isset($success_msg)): ?>
        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
            <?= $success_msg ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
            <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <!-- KPI Section for Payments -->
    <div class="kpi-container" style="margin-bottom: 30px; display: flex; gap: 20px;">
        <div class="kpi-card success" style="flex: 1;">
            <div class="kpi-title">Monto Total Procesado (Bruto)</div>
            <div class="kpi-value">$<?= number_format($total_bruto, 2) ?></div>
        </div>
        <div class="kpi-card info" style="flex: 1; background-color: #f0f9ff; border-left: 5px solid #0ea5e9;">
            <div class="kpi-title" style="color: #0369a1;">Comisiones Generadas (10%)</div>
            <div class="kpi-value" style="color: #0369a1;">$<?= number_format($ganancia_neta_total, 2) ?></div>
        </div>
        <div class="kpi-card warning" style="flex: 1; background-color: #fffbeb; border-left: 5px solid #f59e0b;">
            <div class="kpi-title" style="color: #b45309;">Total Retirado</div>
            <div class="kpi-value" style="color: #b45309;">$<?= number_format($total_retirado, 2) ?></div>
        </div>
        <div class="kpi-card danger" style="flex: 1;">
            <div class="kpi-title">Ganancia Disponible</div>
            <div class="kpi-value">$<?= number_format($ganancia_disponible, 2) ?></div>
        </div>
    </div>

    <!-- Filtros y Acciones -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; gap: 20px; flex-wrap: wrap;">
        <form method="GET" style="display:flex; gap: 10px; flex: 1; min-width: 400px; background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Buscar por Nombre Pasajero o N° Reserva" style="flex:2; padding: 10px; border-radius: 4px; border: 1px solid #ccc; margin: 0;">
            <select name="estado" style="flex:1; padding: 10px; border-radius: 4px; border: 1px solid #ccc; margin: 0;">
                <option value="">Cualquier Estado</option>
                <option value="Completado" <?= $estado_filtro === 'Completado' ? 'selected' : '' ?>>Completado</option>
                <option value="Pendiente" <?= $estado_filtro === 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                <option value="Fallado" <?= $estado_filtro === 'Fallado' ? 'selected' : '' ?>>Fallado</option>
                <option value="Cancelado" <?= $estado_filtro === 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
            <button type="submit" style="padding: 10px 20px; background-color: var(--primary); color: white; border: none; border-radius: 4px; cursor: pointer; margin: 0;">Filtrar</button>
            <?php if($search || $estado_filtro): ?>
                <a href="pagos.php" style="padding: 10px; background-color: #cbd5e1; color: #1e293b; border-radius: 4px; text-decoration: none; font-weight: bold;">Limpiar</a>
            <?php endif; ?>
        </form>

        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <button onclick="document.getElementById('modalRetirar').style.display='flex'" style="padding: 10px 25px; background-color: #16a34a; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 1.1em;">
                💸 Retirar Ganancias
            </button>
        </div>
    </div>

    <!-- Modal Retirar -->
    <div id="modalRetirar" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <h3 style="margin-top: 0; color: var(--primary);">Retirar Ganancias</h3>
            <p style="color: #64748b; font-size: 0.9em; margin-bottom: 20px;">Indica el monto que deseas retirar de las comisiones disponibles.</p>
            
            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
                <div style="font-size: 0.85em; color: #64748b;">Máximo disponible:</div>
                <div style="font-size: 1.5em; font-weight: bold; color: #1e293b;">$<?= number_format($ganancia_disponible, 2) ?></div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="retirar">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Monto a retirar:</label>
                <input type="number" name="monto" step="0.01" min="0.01" max="<?= $ganancia_disponible ?>" required style="width: 100%; padding: 12px; border-radius: 6px; border: 1px solid #ccc; margin-bottom: 20px; font-size: 1.1em;" placeholder="0.00">
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="document.getElementById('modalRetirar').style.display='none'" style="flex: 1; padding: 10px; background: #e2e8f0; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Cancelar</button>
                    <button type="submit" style="flex: 1; padding: 10px; background: #16a34a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Confirmar Retiro</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($pagos)): ?>
        <p>No se encontraron pagos con estos filtros.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Fecha</th>
                    <th>Ruta y Viaje</th>
                    <th>De Pasajero</th>
                    <th>Para Conductor</th>
                    <th>Estado</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><strong>PG-<?= str_pad($p['ID_pago'], 5, '0', STR_PAD_LEFT) ?></strong><br><small>Reserva #<?= $p['ID_reserva'] ?></small></td>
                    <td><?= date('d/m/Y H:i', strtotime($p['Fecha'])) ?></td>
                    <td><?= htmlspecialchars($p['CiudadOrigen']) ?> &rarr; <?= htmlspecialchars($p['CiudadDestino']) ?></td>
                    <td><?= htmlspecialchars($p['PasajeroNombre'] . ' ' . $p['PasajeroApellido']) ?></td>
                    <td><?= htmlspecialchars($p['ConductorNombre'] . ' ' . $p['ConductorApellido']) ?></td>
                    <td>
                        <?php if ($p['EstadoPago'] === 'Completado'): ?>
                            <span style="background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 20px; font-weight: bold; font-size: 0.85em; border: 1px solid #bbf7d0;">COMPLETADO</span>
                        <?php elseif ($p['EstadoPago'] === 'Pendiente'): ?>
                            <span style="background: #fef9c3; color: #854d0e; padding: 3px 8px; border-radius: 20px; font-weight: bold; font-size: 0.85em; border: 1px solid #fef08a;">PENDIENTE</span>
                        <?php else: ?>
                            <span style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 20px; font-weight: bold; font-size: 0.85em; border: 1px solid #fecaca;"><?= strtoupper(htmlspecialchars($p['EstadoPago'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong style="color: var(--primary); font-size: 1.1em;">$<?= number_format($p['Monto'], 2) ?></strong>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (isset($total_paginas) && $total_paginas > 1): ?>
    <div class="pagination">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina - 1 ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($estado_filtro) ?>">&laquo; Anterior</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
            <a href="?pagina=<?= $i ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($estado_filtro) ?>" class="<?= $i == $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pagina < $total_paginas): ?>
            <a href="?pagina=<?= $pagina + 1 ?>&search=<?= urlencode($search) ?>&estado=<?= urlencode($estado_filtro) ?>">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
