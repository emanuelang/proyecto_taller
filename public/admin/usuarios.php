<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Procesar eliminación de usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && isset($_POST['usuario_id'])) {
    $usuario_target = (int)$_POST['usuario_id'];
    $accion = $_POST['accion'];
    
    // Safety check: Cannot delete yourself
    if ($accion === 'eliminar_usuario') {
        if ($usuario_target === $_SESSION['user_id']) {
            $msg_error = "No puedes eliminar tu propia cuenta de administrador.";
        } else {
            // Verificar si el objetivo es otro administrador (opcional protección adicional)
            $stmt_check_admin = $pdo->prepare("SELECT * FROM Administradores WHERE ID_usuario = ?");
            $stmt_check_admin->execute([$usuario_target]);
            if ($stmt_check_admin->fetch()) {
                $msg_error = "No puedes eliminar a otro administrador del sistema.";
            } else {
                // Proceder con la eliminación.
                // Como las tablas tienen ON DELETE CASCADE, esto borrará sus roles de pasajero, conductor, publicaciones, reservas y calificaciones automáticamente.
                $stmt_del = $pdo->prepare("DELETE FROM Usuarios WHERE ID_usuario = ?");
                $stmt_del->execute([$usuario_target]);
                $msg_exito = "Usuario eliminado permanentemente del sistema.";
            }
        }
    }
}

// Obtener la lista de usuarios (que no sean administradores)
$stmt = $pdo->query("
    SELECT u.ID_usuario AS id, u.Nombre, u.Apellido, u.Correo, u.Telefono, u.DNI,
           (SELECT COUNT(*) FROM Conductores WHERE ID_usuario = u.ID_usuario) AS es_conductor
    FROM Usuarios u
    LEFT JOIN Administradores a ON u.ID_usuario = a.ID_usuario
    WHERE a.ID_administrador IS NULL
    ORDER BY u.ID_usuario DESC
");
$usuarios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Gestión de Usuarios - Admin</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .admin-nav { background-color: #333; color: white; padding: 10px; }
        .admin-nav a { color: white; margin-right: 15px; text-decoration: none; }
        .admin-nav a:hover { text-decoration: underline; }
        .table-admin { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-admin th, .table-admin td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: top; }
        .table-admin th { background-color: #f2f2f2; }
        .btn-rechazar { background-color: #dc3545; color: white; padding: 5px 10px; border: none; cursor: pointer; border-radius: 3px; }
    </style>
</head>
<body>

<div class="admin-nav">
    <strong>Admin Panel</strong> |
    <a href="dashboard.php">Dashboard</a>
    <a href="conductores.php">Conductores</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="viajes.php">Viajes</a>
    <a href="reportes.php">Reportes</a>
    <a href="pagos.php">Pagos</a>
    <a style="float: right;" href="../logout.php">Cerrar Sesión</a>
</div>

<div style="padding: 20px;">
    <h2>Gestión de Usuarios</h2>
    <p>Lista de todos los usuarios estándar registrados (pasajeros/conductores). Desde aquí puedes expulsarlos del sistema.</p>
    
    <?php if (isset($msg_exito)): ?>
        <p style="color: green; font-weight: bold; background: #e8f5e9; padding: 10px; border: 1px solid #c8e6c9;"><?= htmlspecialchars($msg_exito) ?></p>
    <?php endif; ?>

    <?php if (isset($msg_error)): ?>
        <p style="color: red; font-weight: bold; background: #ffebee; padding: 10px; border: 1px solid #ffcdd2;"><?= htmlspecialchars($msg_error) ?></p>
    <?php endif; ?>

    <?php if (empty($usuarios)): ?>
        <p>No hay usuarios estándar registrados en la plataforma.</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre y Apellido</th>
                    <th>Contacto</th>
                    <th>DNI</th>
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
                        <?php if ($u['es_conductor'] > 0): ?>
                            <strong style="color: #0275d8;">Conductor</strong> / Pasajero
                        <?php else: ?>
                            Pasajero
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <form method="post">
                            <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar_usuario">
                            <button type="submit" class="btn-rechazar" onclick="return confirm('ATENCIÓN: ¿Seguro que deseas ELIMINAR a este usuario de la plataforma de forma permanente? Se borrarán sus viajes, vehículos, reservas y cuenta.');">Eliminar Usuario</button>
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
