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
<?php require_once __DIR__ . '/../header.php'; ?>

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
