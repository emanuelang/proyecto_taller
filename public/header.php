<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>

<h1>Carpooling</h1>

<div class="nav-menu">
<?php if (!isset($_SESSION['user_id'])): ?>
    <a href="<?= BASE_URL ?>login.php" class="btn">Iniciar sesión</a>
    <a href="<?= BASE_URL ?>registro_usuario.php">Registrarse</a>
<?php else: ?>
    <span>Hola <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong></span>
    <a href="<?= BASE_URL ?>index.php">Ver viajes</a>
    <a href="<?= BASE_URL ?>reservas/mis_reservas.php">Mis reservas</a>

    <?php if (!$_SESSION['is_conductor']): ?>
        <a href="<?= BASE_URL ?>registro_conductor.php">Convertirme en conductor</a>
    <?php else: ?>
        <a href="<?= BASE_URL ?>conductor/dashboard.php">Panel conductor</a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>logout.php" style="color: #ef4444; margin-left: auto;">Salir</a>
<?php endif; ?>
</div>

<hr>
