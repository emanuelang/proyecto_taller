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

/* Verificar viaje, cupo y datos para el pago */
$sql = "
    SELECT p.ID_publicacion,
           p.CiudadOrigen,
           p.CiudadDestino,
           p.Precio,
           c.ID_usuario,
           v.CantidadAsientos AS asientos,
           (
               SELECT COUNT(*)
               FROM Reservas r
               WHERE r.ID_publicacion = p.ID_publicacion
                 AND r.Estado = 'Completada'
           ) AS ocupados
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
    JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    WHERE p.ID_publicacion = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje no existe.");
}

if ($viaje['ocupados'] >= $viaje['asientos']) {
    die("No hay asientos disponibles en este viaje.");
}

/* Evitar reservar propio viaje */
if ($viaje['ID_usuario'] == $_SESSION['user_id']) {
    die("No podés reservar tu propio viaje.");
}

/* Evitar duplicado: si ya tiene una reserva Completada para este viaje */
$sql_dup = "
    SELECT COUNT(*)
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
    WHERE r.ID_publicacion = :viaje_id
      AND pas.ID_usuario   = :usuario_id
      AND r.Estado = 'Completada'
";
$stmt_dup = $pdo->prepare($sql_dup);
$stmt_dup->execute([':viaje_id' => $viaje_id, ':usuario_id' => $_SESSION['user_id']]);

if ($stmt_dup->fetchColumn() > 0) {
    die("Ya tenés una reserva confirmada para este viaje.");
}

/* 
 * IMPORTANTE: Ya no creamos la reserva "Pendiente" en la base de datos.
 * Vamos a pasar el ID de viaje y de usuario por la externa_reference a MP.
 * Solo guardaremos la reserva si el pago es exitoso.
 */
$external_reference = $viaje_id . '_' . $_SESSION['user_id'];

/* Generar la preferencia de Mercado Pago API REAL */
$mp_access_token = 'APP_USR-6088138919766842-033021-cb005d5c6385fb2d1bb62e1583b4989a-3302874491';

$preference_data = array(
    "items" => array(
        array(
            "title" => "Reserva de Asiento: " . $viaje['CiudadOrigen'] . " a " . $viaje['CiudadDestino'],
            "quantity" => 1,
            "currency_id" => "ARS",
            "unit_price" => (float)$viaje['Precio']
        )
    ),
    "back_urls" => array(
        "success" => BASE_URL . "reservas/mp_success.php",
        "failure" => BASE_URL . "reservas/mp_failure.php",
        "pending" => BASE_URL . "reservas/mp_pending.php"
    ),
    "external_reference" => $external_reference
);

$ch = curl_init('https://api.mercadopago.com/checkout/preferences');
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $mp_access_token,
    'Content-Type: application/json'
));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Permitir cURL en XAMPP

$response = curl_exec($ch);
$curl_error = curl_error($ch);
$mp_result = json_decode($response, true);
curl_close($ch);

if (isset($mp_result['sandbox_init_point'])) {
    $sandbox_url = $mp_result['sandbox_init_point'];
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="utf-8">
        <title>Pagar Reserva</title>
        <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
    </head>
    <body style="background-color: #f4f6f9; text-align: center; font-family: -apple-system, sans-serif;">
        <div style="max-width: 500px; margin: 80px auto; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <h2 style="color: #009ee3; margin-top:0;">Damos inicio a tu pago</h2>
            <p style="color: #555; margin-bottom: 30px; font-size: 1.1em;">Por seguridad, la pasarela de Mercado Pago se abrirá de forma independiente.</p>
            
            <a href="<?= $sandbox_url ?>" target="_blank" onclick="this.style.display='none'; document.getElementById('post-pago').style.display='block';" style="display:inline-block; padding:15px 30px; background-color:#009ee3; color:white; font-size:1.2em; text-decoration:none; border-radius:5px; font-weight:bold; box-shadow: 0 2px 5px rgba(0,158,227,0.4); margin-bottom:15px;">
                Pagar en Mercado Pago 🔒
            </a>
            
            <div id="post-pago" style="display:none; margin-top: 40px; padding-top: 25px; border-top: 1px solid #eee;">
                <h3 style="color:#28a745;">¿Completaste el pago exitosamente?</h3>
                <p style="color: #666; font-size:0.9em; margin-bottom:15px;">Suele tardar unos segundos en acreditarse en el entorno local. Si ya terminaste de pagar en la otra pestaña, DEBES confirmar aquí abajo para generar tu código de viaje:</p>
                <a href="<?= BASE_URL ?>reservas/mp_success.php?collection_status=approved&external_reference=<?= $external_reference ?>" class="btn" style="background-color:#28a745; border-color:#28a745; width:100%; box-sizing:border-box; font-size:1.1em; color: white; display: inline-block; padding: 15px; border-radius: 5px; text-decoration: none;">
                    ✅ Sí, ya pagué exitosamente
                </a>
            </div>
            
            <script>
                // Abrimos el popup e inmediatamente bloqueamos intentos infinitos
                setTimeout(function() {
                    const btn = document.querySelector('a[href="<?= $sandbox_url ?>"]');
                    if (btn && btn.style.display !== 'none') {
                        window.open('<?= $sandbox_url ?>', '_blank');
                        btn.style.display = 'none';
                        document.getElementById('post-pago').style.display='block';
                    }
                }, 1000);
            </script>
        </div>
    </body>
    </html>
    <?php
    exit;
} else {
    // Si falla MP, no hay reserva que borrar de la base de datos, solo mostrar error
    $request_body = json_encode($preference_data, JSON_UNESCAPED_UNICODE);
    die("Error inesperado al conectar con Mercado Pago (Sandbox).<br>Error cURL: " . $curl_error . "<br>Respuesta MP: " . print_r($mp_result, true) . "<br>JSON Enviado: " . $request_body);
}
