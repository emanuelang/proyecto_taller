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

/* Verificar viaje y cupo */
$sql = "
    SELECT v.id, c.usuario_id, veh.asientos,
           (SELECT COUNT(*) FROM reservas r WHERE r.viaje_id = v.id AND r.estado = 'activa') as ocupados
    FROM viajes v
    JOIN conductores c ON v.conductor_id = c.id
    JOIN vehiculos veh ON v.vehiculo_id = veh.id
    WHERE v.id = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje no existe.");
}

if ($viaje['ocupados'] >= $viaje['asientos']) {
    die("No hay asientos disponibles en este viaje.");
}

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
    AND estado = 'activa'
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':viaje_id' => $viaje_id,
    ':usuario_id' => $_SESSION['user_id']
]);

if ($stmt->fetchColumn() > 0) {
    die("Ya reservaste este viaje.");
}

/* Redirigir a pago simulado */
$_SESSION['reserva_pendiente'] = $viaje_id;
header("Location: " . BASE_URL . "reservas/pago_simulado.php");
exit;
