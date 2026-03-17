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
    SELECT p.ID_publicacion, c.ID_usuario
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    WHERE p.ID_publicacion = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje no existe.");
}

/* Evitar reservar propio viaje */
if ($viaje['ID_usuario'] == $_SESSION['user_id']) {
    die("No podés reservar tu propio viaje.");
}

// Obtener o Crear ID Pasajero
$stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
$stmt_pasajero->execute([$_SESSION['user_id']]);
$pasajero = $stmt_pasajero->fetch();

if (!$pasajero) {
    $stmt_insert = $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)");
    $stmt_insert->execute([$_SESSION['user_id']]);
    $pasajero_id = $pdo->lastInsertId();
} else {
    $pasajero_id = $pasajero['ID_pasajero'];
}

/* Evitar duplicado */
$sql = "
    SELECT COUNT(*)
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    WHERE r.ID_publicacion = :viaje_id
    AND pr.ID_pasajero = :pasajero_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':viaje_id' => $viaje_id,
    ':pasajero_id' => $pasajero_id
]);

if ($stmt->fetchColumn() > 0) {
    die("Ya reservaste este viaje.");
}

try {
    $pdo->beginTransaction();
    
    /* Insertar Reserva */
    $sql = "
        INSERT INTO Reservas (ID_publicacion, Estado, FechaReserva)
        VALUES (:viaje_id, 'Pendiente', NOW())
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':viaje_id' => $viaje_id
    ]);
    
    $reserva_id = $pdo->lastInsertId();
    
    /* Conectar Pasajero-Reserva */
    $sql2 = "INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([$pasajero_id, $reserva_id]);
    
    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al reservar el viaje: " . $e->getMessage());
}

header("Location: " . BASE_URL . "reservas/mis_reservas.php");
exit;
