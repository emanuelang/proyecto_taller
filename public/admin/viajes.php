<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Procesar eliminación de publicación (viaje)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['viaje_id'])) {
    $viaje_target = (int)$_POST['viaje_id'];
    $accion = $_POST['accion'];
    
    if ($accion === 'eliminar_viaje') {
        $stmt_del = $pdo->prepare("DELETE FROM Publicaciones WHERE ID_publicacion = ?");
        $stmt_del->execute([$viaje_target]);
        $msg_exito = "Viaje eliminado permanentemente del sistema.";
    }
}

// Traer todos los viajes
$stmt = $pdo->query("
    SELECT p.ID_publicacion AS id, p.CiudadOrigen, p.CiudadDestino, p.HoraSalida, p.Precio, p.Estado,
           u.Nombre, u.Apellido, v.Marca, v.Modelo, v.Patente
    FROM Publicaciones p
    LEFT JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    LEFT JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    LEFT JOIN Usuarios u ON c.ID_usuario = u.ID_usuario
    LEFT JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    ORDER BY p.HoraSalida DESC
");
$viajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
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
    <h2>Gestión de Viajes (Publicaciones)</h2>
    <p>Lista de todos los viajes del sistema. Puedes eliminar aquellos que infrinjan las reglas de la plataforma.</p>
    
    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if (empty($viajes)): ?>
        <p>No hay viajes publicados en el sistema.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ruta y Fecha</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th>Conductor</th>
                    <th>Vehículo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($viajes as $v): ?>
                <tr>
                    <td><?= $v['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($v['CiudadOrigen']) ?> &rarr; <?= htmlspecialchars($v['CiudadDestino']) ?></strong><br>
                        <?= date('d/m/Y H:i', strtotime($v['HoraSalida'])) ?>
                    </td>
                    <td>$<?= number_format($v['Precio'], 2) ?></td>
                    <td><?= htmlspecialchars($v['Estado']) ?></td>
                    <td><?= htmlspecialchars(($v['Nombre'] ?? '---') . ' ' . ($v['Apellido'] ?? '')) ?></td>
                    <td>
                        <?= htmlspecialchars($v['Marca'] ?? '???') ?> <?= htmlspecialchars($v['Modelo'] ?? '') ?><br>
                        <em><?= htmlspecialchars($v['Patente'] ?? 'Sin patente') ?></em>
                    </td>
                    <td style="text-align: center;">
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="viaje_id" value="<?= $v['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar_viaje">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('¿Estás seguro de ELIMINAR este viaje permanentemente? Todas las reservas asociadas se cancelarán/borrarán.');">Eliminar Viaje</button>
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
