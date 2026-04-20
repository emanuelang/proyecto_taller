<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

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
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cálculo de ganancias totales
$stmt_ganancias = $pdo->query("SELECT SUM(Monto) FROM Pagos WHERE Estado = 'Completado'");
$total_bruto = $stmt_ganancias->fetchColumn() ?: 0;
$ganancia_neta = $total_bruto * 0.10; // 10% retención

require_once __DIR__ . '/../header.php';
?>

<div class="nav-menu" style="background-color: var(--border-color); padding: 10px; justify-content: center; margin-top: -20px; margin-bottom: 20px; border-radius: 8px;">
    <strong style="color: var(--primary);">Admin Panel</strong>
    <a href="dashboard.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Dashboard</a>
    <a href="conductores.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Conductores</a>
    <a href="usuarios.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Usuarios</a>
    <a href="viajes.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Viajes</a>
    <a href="reportes.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Reportes</a>
    <a href="pagos.php" class="btn" style="background-color: transparent; border: 1px solid var(--primary); color: var(--primary); padding: 5px 15px;">Pagos</a>
</div>

<div style="padding: 20px;">
    <h2>Historial de Pagos y Finanzas</h2>
    <p>Monitoriza todas las transacciones procesadas por la pasarela de pagos. Las comisiones del 10% se retienen automáticamente de los pagos en estado "Completado".</p>

    <!-- KPI Section for Payments -->
    <div class="kpi-container" style="margin-bottom: 30px;">
        <div class="kpi-card success">
            <div class="kpi-title">Monto Total Procesado</div>
            <div class="kpi-value">$<?= number_format($total_bruto, 2) ?></div>
        </div>
        <div class="kpi-card danger">
            <div class="kpi-title">Ganancia Neta (10%)</div>
            <div class="kpi-value">$<?= number_format($ganancia_neta, 2) ?></div>
        </div>
    </div>

    <!-- Filtros -->
    <form method="GET" style="margin-bottom: 20px; display:flex; gap: 10px; max-width: 600px; background: white; padding: 15px; border-radius: 8px; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
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
</div>
