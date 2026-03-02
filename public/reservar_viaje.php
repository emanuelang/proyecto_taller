<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Viaje no especificado.");
}

$viaje_id = (int) $_GET['id'];

/* Verificar viaje */
$sql = "
    SELECT v.id, c.usuario_id
    FROM viajes v
    JOIN conductores c ON v.conductor_id = c.id
    WHERE v.id = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje no existe.");
}

/* Evitar reservar propio viaje */
if ($viaje['usuario_id'] == $_SESSION['user_id']) {
    die("No podés reservar tu propio viaje.");
}

/* Evitar duplicado */
$sql = "
    SELECT COUNT(*)
    FROM reservas
    WHERE viaje_id = :viaje_id
    AND usuario_id = :usuario_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':viaje_id' => $viaje_id,
    ':usuario_id' => $_SESSION['user_id']
]);

if ($stmt->fetchColumn() > 0) {
    die("Ya reservaste este viaje.");
}

/* Insertar */
$sql = "
    INSERT INTO reservas (viaje_id, usuario_id, fecha_reserva)
    VALUES (:viaje_id, :usuario_id, NOW())
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':viaje_id' => $viaje_id,
    ':usuario_id' => $_SESSION['user_id']
]);

header("Location: " . BASE_URL . "reservas/mis_reservas.php");
exit;
