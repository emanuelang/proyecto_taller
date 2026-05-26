<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';
require_once __DIR__ . '/../core/mercadopago.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

$error = '';
$sandbox_url = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $monto = (float)$_POST['monto'];
    
    if ($monto <= 0 || $monto > 500000) {
        $error = "El monto debe ser mayor a 0 y no superar $500.000.";
    } else {
        $external_reference = bin2hex(random_bytes(24));
        $_SESSION['pending_saldo'][$external_reference] = [
            'user_id' => (int)$_SESSION['user_id'],
            'monto' => $monto,
            'created_at' => time(),
        ];
        
        $preference_data = array(
            "items" => array(
                array(
                    "title" => "Ingreso de Saldo - Carpooling",
                    "quantity" => 1,
                    "currency_id" => "ARS",
                    "unit_price" => $monto
                )
            ),
            "back_urls" => array(
                "success" => app_public_url("mp_success_saldo.php?external_reference=" . urlencode($external_reference)),
                "failure" => app_public_url("perfil.php?mp_status=failure"),
                "pending" => app_public_url("perfil.php?mp_status=pending")
            ),
            "external_reference" => $external_reference
        );

        if (stripos(app_public_url(), 'https://') === 0) {
            $preference_data["auto_return"] = "approved";
        }

        $mp_result = mp_create_preference($preference_data);
        $sandbox_url = $mp_result['ok'] ? mp_checkout_url($mp_result['data']) : '';

        if ($sandbox_url === '') {
            unset($_SESSION['pending_saldo'][$external_reference]);
            error_log("Error creando preferencia MP saldo: " . ($mp_result['error'] ?? '') . " status=" . ($mp_result['status'] ?? 0));
            $error = "Error al conectar con Mercado Pago. Intente nuevamente.";
        } else {
            header("Location: " . $sandbox_url);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ingresar Dinero - Carpooling</title>
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
        <h2>Ingresar Dinero</h2>
        <a href="<?= BASE_URL ?>perfil.php" style="margin-left: auto;">← Volver al perfil</a>
    </div>

    <div class="container">
        <?php if ($sandbox_url): ?>
            <h2 style="color: #009ee3; margin-top:0;">Damos inicio a tu pago</h2>
            <p style="color: #555; margin-bottom: 30px; font-size: 1.1em;">Por seguridad, la pasarela de Mercado Pago se abrirá de forma independiente.</p>
            
            <a href="<?= $sandbox_url ?>" onclick="this.style.display='none'; document.getElementById('post-pago').style.display='block';" class="btn" style="background-color:#009ee3; color:white; font-size:1.2em; text-decoration:none; display:inline-block; margin-bottom:15px; border:none;">
                Pagar en Mercado Pago 🔒
            </a>
            
            <div id="post-pago" style="display:none; margin-top: 40px; padding-top: 25px; border-top: 1px solid #eee;">
                <h3 style="color:#28a745;">¿Completaste el pago exitosamente?</h3>
                <?php if (mp_local_test_mode()): ?>
                    <p style="color: #666; font-size:0.9em; margin-bottom:15px;">Modo prueba local habilitado. Usalo solo si Mercado Pago no puede redirigir al entorno local.</p>
                    <a href="<?= BASE_URL ?>mp_success_saldo.php?collection_status=approved&external_reference=<?= htmlspecialchars($external_reference) ?>&local_test=1" class="btn" style="background-color:#28a745; width:100%; box-sizing:border-box; font-size:1.1em; color:white; text-decoration:none; display:inline-block;">
                        Confirmar carga en modo prueba local
                    </a>
                <?php else: ?>
                    <p style="color: #666; font-size:0.9em; margin-bottom:15px;">Cuando Mercado Pago apruebe la operacion, la acreditacion se validara automaticamente.</p>
                <?php endif; ?>
            </div>
            
            <script>
                setTimeout(function() {
                    const btn = document.querySelector('a[href="<?= $sandbox_url ?>"]');
                    if (btn && btn.style.display !== 'none') {
                        window.location.href = '<?= $sandbox_url ?>';
                        btn.style.display = 'none';
                        document.getElementById('post-pago').style.display='block';
                    }
                }, 1000);
            </script>
        <?php else: ?>
            <h2 style="color: #22c55e;">Agregar Saldo</h2>
            <p style="color: #555; margin-bottom: 30px;">Ingresa el monto que deseas cargar en tu billetera. Este saldo podrá ser utilizado para pagar tus viajes automáticamente.</p>
            
            <?php if ($error): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <div class="input-group">
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">Monto a ingresar ($ ARS):</label>
                    <input type="number" name="monto" min="1" step="0.01" required placeholder="Ej: 5000">
                </div>
                <button type="submit" class="btn" style="background-color: #22c55e; color: white; width: 100%; font-size: 1.1em; border: none; padding: 12px; cursor: pointer;">
                    Continuar al Pago
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
