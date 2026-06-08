<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

if (!PAYMENTS_ENABLED) {
    header("Location: " . BASE_URL . "perfil.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener el saldo actual
$stmt = $pdo->prepare("SELECT Saldo FROM Usuarios WHERE ID_usuario = ?");
$stmt->execute([$user_id]);
$perfil = $stmt->fetch();

if (!$perfil) {
    die("Usuario no encontrado.");
}
$saldo = (float)$perfil['Saldo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $monto = (float)$_POST['monto'];
    
    if ($monto <= 0) {
        $error = "El monto a retirar debe ser mayor a 0.";
    } elseif ($monto > 500000) {
        $error = "El monto maximo por retiro es de $500.000.";
    } elseif ($monto > $saldo) {
        $error = "No tienes saldo suficiente para retirar $" . number_format($monto, 2, ',', '.') . ". Tu saldo actual es de $" . number_format($saldo, 2, ',', '.') . ".";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt_upd = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo - ? WHERE ID_usuario = ? AND Saldo >= ?");
            $stmt_upd->execute([$monto, $user_id, $monto]);
            if ($stmt_upd->rowCount() !== 1) {
                throw new Exception('Saldo insuficiente.');
            }
            $pdo->commit();
            
            $saldo -= $monto; // Actualizar vista local
            $success = "Retiro de $" . number_format($monto, 2, ',', '.') . " realizado con éxito.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Error al procesar retiro: " . $e->getMessage());
            $error = "No se pudo procesar el retiro.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Retirar Dinero - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        .container {
            max-width: 500px;
            margin: 80px auto;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        .input-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .input-group input {
            width: 100%;
            padding: 10px;
            font-size: 1.2em;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }
    </style>
</head>
<body style="background-color: #f4f6f9; font-family: -apple-system, sans-serif;">
    <div class="nav-menu">
        <h2>Retirar Dinero</h2>
        <a href="<?= BASE_URL ?>perfil.php" style="margin-left: auto;">← Volver al perfil</a>
    </div>

    <div class="container">
        <h2 style="color: #3b82f6; margin-top:0;">Retirar Fondos</h2>
        <p style="color: #555; margin-bottom: 20px;">
            Tu saldo disponible es:<br>
            <strong style="font-size: 2em; color: #15803d;">$<?= number_format($saldo, 2, ',', '.') ?></strong>
        </p>
        
        <?php if ($error): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="input-group">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Monto a retirar ($ ARS):</label>
                <input type="number" name="monto" min="1" max="<?= $saldo ?>" step="0.01" required placeholder="Ej: 5000" <?php if ($saldo <= 0) echo 'disabled'; ?>>
            </div>
            <button type="submit" class="btn" style="background-color: #3b82f6; color: white; width: 100%; font-size: 1.1em; border: none; padding: 12px; cursor: pointer;" <?php if ($saldo <= 0) echo 'disabled style="background-color: #9ca3af; cursor: not-allowed;"'; ?>>
                Confirmar Retiro
            </button>
        </form>
        <p style="margin-top: 20px; font-size: 0.9em; color: #6b7280;">
            El dinero se transferirá a la cuenta vinculada a tu perfil (simulado).
        </p>
    </div>
</body>
</html>
