<?php
session_start();
require_once __DIR__ . '/../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Manual de Usuario - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= time() ?>">
    <style>
        .manual-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            font-family: inherit;
        }
        .manual-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #ccc;
        }
        .manual-section:last-child {
            border-bottom: none;
        }
        h2 {
            color: var(--primary);
            margin-top: 0;
        }
        h3 {
            color: #333;
            margin-bottom: 10px;
        }
        .faq-item {
            background-color: #f8fafc;
            border-left: 4px solid var(--primary);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 4px 4px 0;
        }
        .faq-item h4 {
            margin: 0 0 5px 0;
            color: #1e293b;
        }
        .faq-item p {
            margin: 0;
            color: #475569;
            font-size: 0.95em;
            line-height: 1.5;
        }
        .top-bar {
            background: var(--primary);
            padding: 15px 20px;
            color: white;
            display: flex;
            align-items: center;
        }
        .top-bar a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }
        .top-bar a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body style="background-color: #f1f5f9; margin: 0;">

<div class="top-bar">
    <a href="<?= BASE_URL ?>index.php">← Volver al inicio</a>
    <h3 style="margin: 0 auto; color: white;">Centro de Ayuda y Manual</h3>
</div>

<div class="manual-container">
    <div class="manual-section">
        <h2>👋 Bienvenido al Manual de Usuario</h2>
        <p>Aquí encontrarás todas las respuestas necesarias para sacar el mayor provecho de nuestra plataforma de Carpooling. Aprende a viajar cómodo o a publicar tus propios viajes.</p>
    </div>

    <div class="manual-section">
        <h3>1. ¿Cómo registrarse en la plataforma?</h3>
        <p>Hacerse una cuenta es gratis y rápido. Sigue estos pasos:</p>
        <ol>
            <li>Haz clic en <strong>"Registrarse"</strong> en el menú de inicio.</li>
            <li>Completa el formulario con tu Nombre, Apellido, DNI y Correo electrónico.</li>
            <li>Elige una contraseña segura.</li>
            <li>¡Listo! Tu cuenta como <strong>Pasajero</strong> se creará al instante y ya podrás empezar a buscar viajes.</li>
        </ol>
    </div>

    <div class="manual-section">
        <h3>2. ¿Cómo reservar un viaje?</h3>
        <p>Encontrar un viaje hacia tu destino es muy sencillo:</p>
        <ol>
            <li>Ve a la página de <strong>Inicio</strong>. Utiliza el buscador para ingresar tu ciudad de salida y tu destino.</li>
            <li>Verás una lista de tarjetas con los viajes disponibles. Haz clic en <strong>"Ver Detalle"</strong> en el viaje que te interese.</li>
            <li>Revisa los datos del conductor, la cantidad de asientos libres y el vehículo.</li>
            <li>Si estás de acuerdo, presiona <strong>"Comenzar Reserva"</strong> y confirma la operación.</li>
        </ol>
        <p>💡 <em>Tip: Puedes cancelar tu reserva desde la sección "Mis Reservas" de tu panel lateral, siempre y cuando el viaje no haya sido marcado como completado.</em></p>
    </div>

    <div class="manual-section">
        <h3>3. ¿Cómo pagar mi reserva?</h3>
        <p>Para asegurar tu asiento, nuestra plataforma utiliza un proceso de simulación de pagos integrada tras la reserva. El saldo real se suele acomodar cara a cara, pero si necesitas utilizar dinero electrónico, la plataforma te acercará el Alias o CBU del Conductor una vez completada y aceptada la reserva.</p>
    </div>

    <div class="manual-section">
        <h3>4. ¿Cómo ganar dinero compartiendo el auto? (Convertirme en Conductor)</h3>
        <p>Si tienes vehículo y quieres compartir los gastos de tu trayecto, puedes registrarte como conductor:</p>
        <ol>
            <li>Abre el menú lateral izquierdo (☰) y selecciona <strong>"Convertirme en conductor"</strong>.</li>
            <li>Deberás preparar tu documentación obligatoria: <em>Póliza de Seguro, Licencia de Conducir, Cédula del Auto (papeles), tu cuenta para cobrar, y fotos tuyas y del auto de diversos ángulos</em>.</li>
            <li>Llena el formulario por completo y haz clic en <strong>"Enviar solicitud de revisión"</strong>.</li>
            <li>Un Administrador revisará tus antecedentes para mantener segura a la comunidad. Cuando seas aceptado, ¡la opción del menú cambiará a "Panel Conductor" y podrás crear viajes!</li>
        </ol>
    </div>

    <div class="manual-section">
        <h2>Preguntas Frecuentes (FAQs)</h2>

        <div class="faq-item">
            <h4>¿Qué pasa si el conductor cancela el viaje?</h4>
            <p>Si el viaje es dado de baja, tus reservas para ese trayecto pasarán a estado "Rechazada/Cancelada". Te recomendamos siempre chequear de vez en cuando desde "Mis Reservas".</p>
        </div>

        <div class="faq-item">
            <h4>¿Puedo viajar con mascotas?</h4>
            <p>Esto depende de la voluntad de cada conductor. Te aconsejamos fijarte en los detalles y pedir confirmación mediante mensajería si el conductor lo permite.</p>
        </div>

        <div class="faq-item">
            <h4>¿Son confiables los conductores?</h4>
            <p>Sí. Nuestro equipo de Administración revisa uno por uno el carnet, rostro, patente y seguro de cada persona antes de darle el rol de Conductor. ¡Tu seguridad es nuestra prioridad!</p>
        </div>
    </div>
</div>

</body>
</html>
