<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/header.php';
?>

<div class="page-shell" style="max-width:760px;">
    <h1 class="page-title" style="text-align:center;">Centro de Ayuda</h1>
    <p class="page-subtitle" style="text-align:center;">Todo lo que necesitás saber para usar la plataforma</p>

    <div class="help-grid">
        <div class="card" style="margin:0; display:flex; align-items:center; gap:14px; box-shadow:none;">
            <span class="brand-icon" style="background:#eef4ff; color:var(--primary);">⚙</span>
            <strong>Registrarse</strong>
        </div>
        <div class="card" style="margin:0; display:flex; align-items:center; gap:14px; box-shadow:none;">
            <span class="brand-icon" style="background:#ecfdf5; color:var(--success);">▣</span>
            <strong>Reservar viaje</strong>
        </div>
        <div class="card" style="margin:0; display:flex; align-items:center; gap:14px; box-shadow:none;">
            <span class="brand-icon" style="background:#f4efff; color:#7c3aed;">🚗</span>
            <strong>Publicar viaje</strong>
        </div>
        <div class="card" style="margin:0; display:flex; align-items:center; gap:14px; box-shadow:none;">
            <span class="brand-icon" style="background:#fff7ed; color:#f59e0b;">✓</span>
            <strong>Pagos</strong>
        </div>
        <div class="card" style="margin:0; display:flex; align-items:center; gap:14px; box-shadow:none;">
            <span class="brand-icon" style="background:#fff1f2; color:#e11d48;">◇</span>
            <strong>Seguridad</strong>
        </div>
        <div class="card" style="margin:0; display:flex; align-items:center; gap:14px; box-shadow:none;">
            <span class="brand-icon" style="background:#f1f5f9; color:var(--text-muted);">?</span>
            <strong>Soporte</strong>
        </div>
    </div>

    <h2 style="font-size:18px; text-transform:uppercase; letter-spacing:.5px;">Preguntas frecuentes</h2>

    <details class="card" open>
        <summary style="cursor:pointer; font-size:20px; font-weight:800;">¿Cómo registrarse en la plataforma?</summary>
        <p class="text-muted" style="line-height:1.7; margin-bottom:0;">Hacerse una cuenta es gratis y rápido. Hacé clic en "Registrarse", completá el formulario con nombre, apellido, DNI, correo electrónico y una contraseña segura. Tu cuenta como pasajero se crea al instante y ya podés buscar viajes.</p>
    </details>

    <details class="card">
        <summary style="cursor:pointer; font-size:20px; font-weight:800;">¿Cómo reservar un viaje?</summary>
        <p class="text-muted" style="line-height:1.7; margin-bottom:0;">Buscá por ciudad de salida y llegada, entrá al detalle del viaje, revisá conductor, vehículo, precio y asientos disponibles. Si todo está bien, confirmá la reserva y seguí el proceso de pago.</p>
    </details>

    <details class="card">
        <summary style="cursor:pointer; font-size:20px; font-weight:800;">¿Cómo publicar un viaje como conductor?</summary>
        <p class="text-muted" style="line-height:1.7; margin-bottom:0;">Primero solicitá convertirte en conductor desde el menú lateral. Cuando administración apruebe tu documentación, vas a poder cargar vehículos y publicar viajes desde el panel de conductor.</p>
    </details>

    <details class="card">
        <summary style="cursor:pointer; font-size:20px; font-weight:800;">¿Qué pasa si se cancela un viaje?</summary>
        <p class="text-muted" style="line-height:1.7; margin-bottom:0;">Si el conductor o administración cancela un viaje, las reservas activas se cancelan y el importe se devuelve al saldo de la billetera del pasajero cuando corresponde.</p>
    </details>

    <div class="card" style="text-align:center; background:#e7f0ff;">
        <h3 style="margin-top:0;">¿Aún tenés problemas?</h3>
        <p class="text-muted">Nuestro equipo puede revisar tu caso desde soporte.</p>
        <a href="<?= BASE_URL ?>soporte.php" class="btn">Contactar soporte</a>
    </div>
</div>

</body>
</html>
