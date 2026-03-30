<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

/* Si no hay pago pendiente, volver al inicio */
if (!isset($_SESSION['user_id']) || !isset($_SESSION['pago_pendiente'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$pago = $_SESSION['pago_pendiente'];
$reserva_id = (int) $pago['reserva_id'];

/* Verificar que la reserva sigue Pendiente y pertenece a este usuario */
$stmt_check = $pdo->prepare("
    SELECT r.ID_reserva
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros p ON pr.ID_pasajero = p.ID_pasajero
    WHERE r.ID_reserva = ? AND p.ID_usuario = ? AND r.Estado = 'Pendiente'
");
$stmt_check->execute([$reserva_id, $_SESSION['user_id']]);
if (!$stmt_check->fetch()) {
    unset($_SESSION['pago_pendiente']);
    header("Location: " . BASE_URL . "reservas/mis_reservas.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tarjeta = preg_replace('/\D/', '', $_POST['tarjeta'] ?? '');
    $titular = trim($_POST['titular'] ?? '');
    $venci   = trim($_POST['vencimiento'] ?? '');
    $cvv     = trim($_POST['cvv'] ?? '');

    if (strlen($tarjeta) < 14 || strlen($tarjeta) > 19) {
        $error = "Número de tarjeta inválido (entre 14 y 19 dígitos).";
    } elseif (empty($titular)) {
        $error = "Ingresá el nombre del titular.";
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $venci)) {
        $error = "Fecha de vencimiento inválida (formato MM/AA).";
    } elseif (strlen($cvv) < 3 || strlen($cvv) > 4) {
        $error = "CVV inválido.";
    } else {
        /* Pago aprobado → actualizar reserva a Aceptada */
        $stmt_upd = $pdo->prepare("UPDATE Reservas SET Estado = 'Aceptada' WHERE ID_reserva = ?");
        $stmt_upd->execute([$reserva_id]);

        unset($_SESSION['pago_pendiente']);
        $_SESSION['mensaje_exito'] = "✅ Pago exitoso. ¡Tu reserva fue confirmada!";
        header("Location: " . BASE_URL . "reservas/mis_reservas.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago Seguro - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    <style>
        /* ── Layout general ── */
        body {
            background-color: #f8fafc;
            min-height: 100vh;
        }

        .pay-wrapper {
            max-width: 560px;
            margin: 0 auto;
            padding-bottom: 60px;
        }

        /* ── Encabezado de sección ── */
        .pay-header {
            text-align: center;
            margin: 30px 0 24px;
        }
        .pay-header h1 {
            font-size: 1.75em;
            color: var(--text-main);
            margin: 0 0 6px;
        }
        .pay-header p {
            color: #64748b;
            margin: 0;
            font-size: 0.95em;
        }

        /* ── Tarjeta contenedora ── */
        .pay-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px 32px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }

        /* ── Resumen del viaje ── */
        .resumen {
            background: #f0f9ff;
            border: 1px solid rgba(56,189,248,0.35);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 28px;
        }
        .resumen h3 {
            margin: 0 0 8px;
            font-size: 0.78em;
            color: var(--primary-hover);
            text-transform: uppercase;
            letter-spacing: .07em;
        }
        .resumen .ruta {
            font-size: 1.1em;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        .resumen .precio {
            font-size: 1.5em;
            font-weight: 700;
            color: var(--success-hover);
        }

        /* ── Labels ── */
        .pay-card label {
            display: block;
            font-size: 0.8em;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin: 18px 0 5px;
        }

        /* ── Inputs ── */
        .pay-card input[type="text"] {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            background: #fff;
            color: var(--text-main);
            font-size: 1em;
            box-sizing: border-box;
            margin: 0;
            transition: border-color .2s, box-shadow .2s;
        }
        .pay-card input[type="text"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(56,189,248,.18);
        }
        .pay-card input[type="text"]::placeholder {
            color: #94a3b8;
        }

        /* ── Fila 2 columnas ── */
        .row-2 {
            display: flex;
            gap: 14px;
        }
        .row-2 > div { flex: 1; }

        /* ── Botón principal ── */
        .btn-pagar {
            width: 100%;
            margin-top: 26px;
            padding: 14px;
            font-size: 1.05em;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            background-color: var(--success);
            color: #fff;
            cursor: pointer;
            letter-spacing: .03em;
            transition: background-color .2s, transform .15s, box-shadow .15s;
        }
        .btn-pagar:hover {
            background-color: var(--success-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(132,204,22,.3);
        }

        /* ── Mensaje de error ── */
        .error-msg {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #b91c1c;
            border-radius: 7px;
            padding: 11px 16px;
            margin-bottom: 18px;
            font-size: .9em;
        }

        /* ── Link cancelar ── */
        .back-link {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #94a3b8;
            font-size: .88em;
            text-decoration: none;
            transition: color .2s;
        }
        .back-link:hover {
            color: #64748b;
            text-decoration: underline;
        }

        /* ── Badge de seguro ── */
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: #64748b;
            font-size: .8em;
            margin-top: 14px;
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/../../public/header.php'; ?>

<div class="pay-wrapper">

    <div class="pay-header">
        <h1>🔒 Pago Seguro</h1>
        <p>Completá los datos de tu tarjeta para confirmar la reserva</p>
    </div>

    <div class="pay-card">

        <!-- Resumen del viaje -->
        <div class="resumen">
            <h3>Resumen del viaje</h3>
            <div class="ruta"><?= htmlspecialchars($pago['origen']) ?> → <?= htmlspecialchars($pago['destino']) ?></div>
            <div class="precio">$<?= number_format($pago['precio'], 2) ?></div>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
            <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" autocomplete="off" style="border:none; box-shadow:none; padding:0; max-width:none; margin:0;">

            <label for="tarjeta">Número de Tarjeta</label>
            <input type="text"
                   id="tarjeta"
                   name="tarjeta"
                   placeholder="0000 0000 0000 0000"
                   maxlength="19"
                   required
                   inputmode="numeric">

            <label for="titular">Nombre del Titular</label>
            <input type="text"
                   id="titular"
                   name="titular"
                   placeholder="Como figura en la tarjeta"
                   required>

            <div class="row-2">
                <div>
                    <label for="vencimiento">Vencimiento</label>
                    <input type="text"
                           id="vencimiento"
                           name="vencimiento"
                           placeholder="MM/AA"
                           maxlength="5"
                           required
                           inputmode="numeric">
                </div>
                <div>
                    <label for="cvv">CVV</label>
                    <input type="text"
                           id="cvv"
                           name="cvv"
                           placeholder="123"
                           maxlength="4"
                           required
                           inputmode="numeric">
                </div>
            </div>

            <button type="submit" class="btn-pagar">🔐 Confirmar y Pagar</button>
        </form>

        <a class="back-link" href="<?= BASE_URL ?>index.php">← Cancelar y volver al inicio</a>

        <div class="secure-badge">
            🛡️ Pago simulado — tus datos no serán procesados
        </div>

    </div>
</div>

<script>
/* ── Formateo correcto del número de tarjeta ── */
const inputTarjeta = document.getElementById('tarjeta');
inputTarjeta.addEventListener('input', function () {
    // Guardar posición del cursor
    let pos = this.selectionStart;
    let prevLen = this.value.length;

    // Quitar todo lo que no sea dígito
    let digits = this.value.replace(/\D/g, '');

    // Limitar a 16 dígitos
    digits = digits.slice(0, 16);

    // Insertar espacio cada 4 dígitos
    let formatted = digits.replace(/(.{4})/g, '$1 ').trim();

    this.value = formatted;

    // Ajustar cursor: compensar los espacios agregados
    let newLen = this.value.length;
    let diff = newLen - prevLen;
    this.setSelectionRange(pos + diff, pos + diff);
});

/* ── Formateo de vencimiento MM/AA ── */
const inputVenci = document.getElementById('vencimiento');
inputVenci.addEventListener('input', function () {
    let digits = this.value.replace(/\D/g, '').slice(0, 4);
    if (digits.length > 2) {
        this.value = digits.slice(0,2) + '/' + digits.slice(2);
    } else {
        this.value = digits;
    }
});

/* ── CVV: solo dígitos ── */
const inputCvv = document.getElementById('cvv');
inputCvv.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});
</script>

</body>
</html>
