<?php
session_start();
require_once __DIR__ . '/../../config/app.php';

if (!isset($_SESSION['pago_pendiente'])) {
    die("No hay pago pendiente.");
}
$pago = $_SESSION['pago_pendiente'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mercado Pago - Checkout de Prueba</title>
    <link href="https://fonts.googleapis.com/css2?family=Proxima+Nova:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #009ee3; 
            font-family: 'Proxima Nova', -apple-system, BlinkMacSystemFont, Arial, sans-serif; 
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; margin: 0; 
        }
        .checkout-card { 
            background: white; 
            width: 90%; max-width: 400px; 
            border-radius: 8px; 
            padding: 40px 30px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.2); 
            text-align: center; 
        }
        .header { display: flex; flex-direction: column; align-items: center; margin-bottom: 20px;}
        .mp-badge {
            background-color: #f5f5f5; color: #666; font-size: 0.75em; text-transform: uppercase; 
            letter-spacing: 1px; padding: 4px 8px; border-radius: 4px; margin-bottom: 10px; font-weight: bold;
        }
        .details { text-align: left; background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 25px; border-left: 4px solid #009ee3;}
        .price { font-size: 2.8em; font-weight: 600; color: #333; margin: 10px 0 25px 0; }
        .btn-pay { 
            background-color: #009ee3; color: white; border: none; padding: 15px; 
            width: 100%; font-size: 1.1em; border-radius: 6px; cursor: pointer; 
            font-weight: 600; text-decoration: none; display: inline-block; box-sizing: border-box;
            transition: background-color 0.2s;
        }
        .btn-pay:hover { background-color: #008cc9; }
        .btn-cancel { display: block; margin-top: 20px; color: #999; text-decoration: none; font-size: 0.9em; transition: color 0.2s;}
        .btn-cancel:hover { color: #666; }
    </style>
</head>
<body>
    <div class="checkout-card">
        <div class="header">
            <span class="mp-badge">Entorno de Pruebas (Taller)</span>
            <svg viewBox="0 0 100 28" width="180" height="40" style="fill:#009ee3; margin-top:5px;">
                <path d="M19.1,19.3c-1.4,0-2.6-1.1-2.6-2.5V8c0-1.4,1.1-2.5,2.5-2.5h3.3v13.8H19.1z M8.9,19.3c-1.4,0-2.5-1.1-2.5-2.5V8 C6.4,6.6,7.5,5.5,8.9,5.5h3.3v13.8H8.9z M25.8,5.5h2.5c1.4,0,2.5,1.1,2.5,2.5v8.8c0,1.4-1.1,2.5-2.5,2.5h-2.5V5.5z M25.8,2.7v24.6 l7.1-1.7V5.2C32.9,3.8,31.7,2.7,30.3,2.7H25.8z M5.5,5.5v13.8H3C1.6,19.3,0.5,18.2,0.5,16.8V8c0-1.4,1.1-2.5,2.5-2.5H5.5z M45.8,11.5 h-6.4v7.7h7.2v2.8H36.1V5.5h10.4v2.8h-7.2v3.1h6.4V11.5z M66.4,21.6l-2.4-3.7h-4.3v4.1h-3.3V5.5h8.3c3.5,0,5.7,2,5.7,5.5 c0,2.5-1.3,4.4-3.6,5.1l2.9,4.4H66.4z M64.7,11c0-1.8-1.1-2.6-3-2.6h-5v5.3h5C63.6,13.7,64.7,12.9,64.7,11z M57,11c0,3.6-2.5,6-6,6 c-3.5,0-6-2.4-6-6c0-3.6,2.5-6,6-6C54.4,4.9,57,7.4,57,11z M53.5,11c0-2-1.1-3.2-2.5-3.2c-1.4,0-2.5,1.2-2.5,3.2 c0,2,1.1,3.2,2.5,3.2C52.4,14.3,53.5,13.1,53.5,11z M93.1,11c0,3.6-2.5,6-6,6c-3.5,0-6-2.4-6-6c0-3.6,2.5-6,6-6 C90.5,4.9,93.1,7.4,93.1,11z M89.5,11c0-2-1.1-3.2-2.5-3.2c-1.4,0-2.5,1.2-2.5,3.2c0,2,1.1,3.2,2.5,3.2C88.4,14.3,89.5,13.1,89.5,11z  M81.5,5.5v16.4h-3.3V5.5H81.5z M96.1,10.6v11.4h-3.3V10.6h-1.9V8h1.9V6.4c0-2.4,1.4-3.7,4.3-3.7h2v2.8h-1.3 c-1.2,0-1.7,0.5-1.7,1.5V8h3v2.6H96.1z"/>
            </svg>
        </div>
        
        <div class="details">
            <div style="color:#666; font-size:0.85em; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Resumen de tu compra</div>
            <div style="font-weight:600; margin-top:8px; line-height: 1.4; color: #333;">Pago de Reserva: <?= htmlspecialchars($pago['origen']) ?> &rarr; <?= htmlspecialchars($pago['destino']) ?></div>
        </div>
        
        <div class="price"><span style="font-size: 0.6em; vertical-align: top; margin-right: 2px;">$</span><?= number_format($pago['precio'], 2) ?></div>
        
        <!-- Redirects to mp_success.php perfectly matching the webhook response style -->
        <a href="<?= BASE_URL ?>reservas/mp_success.php?collection_status=approved&external_reference=<?= $pago['reserva_id'] ?>&collection_id=SIMULADO-<?= rand(10000,99999) ?>" class="btn-pay">Pagar con Mercado Pago</a>
        
        <a href="<?= BASE_URL ?>reservas/mp_failure.php?external_reference=<?= $pago['reserva_id'] ?>" class="btn-cancel">Cancelar pago y volver</a>
    </div>
</body>
</html>
