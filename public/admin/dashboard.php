<?php
// Requerimos la verificación de que sea admin
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

// Aquí irán futuras consultas a la base de datos para mostrar estadísticas (viajes, usuarios, etc)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .admin-nav {
            background-color: #333;
            color: white;
            padding: 10px;
        }
        .admin-nav a {
            color: white;
            margin-right: 15px;
            text-decoration: none;
        }
        .admin-nav a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="admin-nav">
    <strong>Admin Panel</strong> |
    <a href="dashboard.php">Dashboard</a>
    <!-- Enlaces futuros que vamos a armar -->
    <a href="conductores.php">Conductores</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="viajes.php">Viajes</a>
    <a href="reportes.php">Reportes</a>
    <a href="pagos.php">Pagos</a>
    <a style="float: right;" href="../logout.php">Cerrar Sesión</a>
</div>

<div style="padding: 20px;">
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?></h2>
    <p>Has ingresado al panel de administración del sistema.</p>
    
    <hr>
    <h3>Estadísticas Rápidas (Próximamente)</h3>
    <ul>
        <li>Total Viajes: --</li>
        <li>Usuarios Activos: --</li>
        <li>Conductores Pendientes: --</li>
    </ul>
</div>

</body>
</html>
