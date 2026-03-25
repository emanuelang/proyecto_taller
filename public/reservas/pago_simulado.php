<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['reserva_pendiente'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$viaje_id = $_SESSION['reserva_pendiente'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simulamos validación de pago exitosa
    $tarjeta = $_POST['tarjeta'] ?? '';
    
    if (strlen($tarjeta) >= 14) {
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

        try {
            $pdo->beginTransaction();
            /* Insertar Reserva */
            $sql = "INSERT INTO Reservas (ID_publicacion, Estado, FechaReserva) VALUES (:viaje_id, 'Pendiente', NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':viaje_id' => $viaje_id]);
            $reserva_id = $pdo->lastInsertId();

            /* Conectar Pasajero-Reserva */
            $sql2 = "INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$pasajero_id, $reserva_id]);
            $pdo->commit();
            
            // Vaciar pendiente y definir un éxito para mostrar
            unset($_SESSION['reserva_pendiente']);
            $_SESSION['mensaje_exito'] = "Pago exitoso. Reserva confirmada.";
            header("Location: " . BASE_URL . "reservas/mis_reservas.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error de base de datos al confirmar la reserva.";
        }
    } else {
        $error = "Tarjeta inválida (mínimo 14 números).";
    }
}

// Obtener detalles del viaje para mostrar el total a pagar
$sql = "
    SELECT p.Precio as precio, p.CiudadOrigen as origen, p.CiudadDestino as destino 
    FROM Publicaciones p
    WHERE p.ID_publicacion = :viaje_id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje pendiente no existe.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Pago - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div class="nav-menu">
        <h2>Pago Seguro</h2>
        <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Cancelar y volver</a>
    </div>
    
    <div class="card" style="max-width: 600px; margin: 0 auto 20px auto; background-color: #f8fafc; border-left: 4px solid var(--primary);">
        <h3 style="margin-top: 0;">Resumen del viaje</h3>
        <p><strong>Ruta:</strong> <?= htmlspecialchars($viaje['origen']) ?> → <?= htmlspecialchars($viaje['destino']) ?></p>
        <p style="font-size: 1.2em;"><strong>Total a Pagar: <span style="color: var(--success);">$<?= number_format($viaje['precio'], 2) ?></span></strong></p>
    </div>

    <?php if (isset($error)): ?>
        <p style="color:red; text-align: center; font-weight: bold;"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <h3 style="margin-top: 0; color: var(--primary);">Datos de la Tarjeta</h3>
        
        <label>Nombre del Titular</label>
        <input type="text" name="titular" placeholder="Tal como figura en la tarjeta" required>
        
        <label>Número de Tarjeta</label>
        <input type="text" name="tarjeta" placeholder="0000 0000 0000 0000" required minlength="14">
        
        <div style="display: flex; gap: 15px;">
            <div style="flex: 1;">
                <label>Vencimiento</label>
                <input type="text" name="vencimiento" placeholder="MM/AA" required>
            </div>
            <div style="flex: 1;">
                <label>Código de Seguridad</label>
                <input type="text" name="cvv" placeholder="CVV" required minlength="3" maxlength="4">
            </div>
        </div>
        
        <button type="submit" class="success-bg" style="width: 100%; margin-top: 20px; font-size: 1.1em;">Confirmar Pago</button>
    </form>
</body>
</html>
