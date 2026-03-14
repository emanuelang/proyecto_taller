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
        // Insertar la reserva
        $sql = "
            INSERT INTO reservas (viaje_id, usuario_id, fecha_reserva, estado)
            VALUES (:viaje_id, :usuario_id, NOW(), 'activa')
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':viaje_id' => $viaje_id,
            ':usuario_id' => $_SESSION['user_id']
        ]);

        // Vaciar pendiente y definir un éxito para mostrar
        unset($_SESSION['reserva_pendiente']);
        $_SESSION['mensaje_exito'] = "Pago exitoso. Reserva confirmada.";
        header("Location: " . BASE_URL . "reservas/mis_reservas.php");
        exit;
    } else {
        $error = "Tarjeta inválida (mínimo 14 números).";
    }
}

// Obtener detalles del viaje para mostrar el total a pagar
$sql = "
    SELECT v.precio, c1.nombre as origen, c2.nombre as destino 
    FROM viajes v
    JOIN ciudades c1 ON v.origen_id = c1.id
    JOIN ciudades c2 ON v.destino_id = c2.id
    WHERE v.id = :viaje_id
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
    <h1>Pago Seguro</h1>
    <a href="<?= BASE_URL ?>index.php">← Cancelar y volver</a>
    <hr>
    
    <h2>Resumen del viaje</h2>
    <p>Ruta: <?= htmlspecialchars($viaje['origen']) ?> → <?= htmlspecialchars($viaje['destino']) ?></p>
    <p>Total a Pagar: $<?= number_format($viaje['precio'], 2) ?></p>

    <?php if (isset($error)): ?>
        <p style="color:red;"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <h3>Datos de la Tarjeta</h3>
        <input type="text" name="titular" placeholder="Nombre en la tarjeta" required><br><br>
        <input type="text" name="tarjeta" placeholder="Número de tarjeta" required minlength="14"><br><br>
        <input type="text" name="vencimiento" placeholder="MM/AA" required style="width: 80px;">
        <input type="text" name="cvv" placeholder="CVV" required minlength="3" maxlength="4" style="width: 60px;"><br><br>
        <button type="submit">Confirmar Pago</button>
    </form>
</body>
</html>
