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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

require_csrf();

$viaje_id = (int)($_POST['id'] ?? 0);
if ($viaje_id <= 0) {
    safe_error('Viaje no especificado.');
}

$tipo_pasaje = ($_POST['tipo_pasaje'] ?? 'propio') === 'tercero' ? 'tercero' : 'propio';
$stmt_usuario_responsable = $pdo->prepare("SELECT Nombre, Apellido, DNI, Correo, Telefono FROM Usuarios WHERE ID_usuario = ?");
$stmt_usuario_responsable->execute([$_SESSION['user_id']]);
$usuario_responsable = $stmt_usuario_responsable->fetch(PDO::FETCH_ASSOC);

if (!$usuario_responsable) {
    safe_error('No se pudo cargar tu usuario.');
}

if ($tipo_pasaje === 'tercero') {
    $pasajero_data = [
        'nombre' => trim($_POST['pasajero_nombre'] ?? ''),
        'apellido' => trim($_POST['pasajero_apellido'] ?? ''),
        'dni' => trim($_POST['pasajero_dni'] ?? ''),
        'telefono' => trim($_POST['pasajero_telefono'] ?? ''),
        'correo' => trim($_POST['pasajero_correo'] ?? ''),
    ];

    if (strlen($pasajero_data['nombre']) < 2 || strlen($pasajero_data['nombre']) > 100 || !preg_match('/^[\p{L}\s]+$/u', $pasajero_data['nombre'])) {
        safe_error('El nombre del pasajero no es valido.');
    }
    if (strlen($pasajero_data['apellido']) < 2 || strlen($pasajero_data['apellido']) > 100 || !preg_match('/^[\p{L}\s]+$/u', $pasajero_data['apellido'])) {
        safe_error('El apellido del pasajero no es valido.');
    }
    if (!preg_match('/^[0-9]{7,8}$/', $pasajero_data['dni'])) {
        safe_error('El DNI del pasajero debe tener 7 u 8 digitos numericos.');
    }
    if (!preg_match('/^[0-9]{8,15}$/', $pasajero_data['telefono'])) {
        safe_error('El telefono del pasajero debe tener entre 8 y 15 digitos numericos.');
    }
    if ($pasajero_data['correo'] !== '' && (!filter_var($pasajero_data['correo'], FILTER_VALIDATE_EMAIL) || strlen($pasajero_data['correo']) > 150)) {
        safe_error('El correo del pasajero no es valido.');
    }
} else {
    $pasajero_data = [
        'nombre' => $usuario_responsable['Nombre'],
        'apellido' => $usuario_responsable['Apellido'],
        'dni' => $usuario_responsable['DNI'],
        'telefono' => $usuario_responsable['Telefono'],
        'correo' => $usuario_responsable['Correo'],
    ];
}

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
    JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
    JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
    WHERE p.ID_publicacion = :viaje_id
      AND p.Estado = 'Activa'
      AND p.HoraSalida >= NOW()
      AND c.Estado = 'Aceptada'
      AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW())
      AND u_cond.estado = 'activo'
      AND (u_cond.BaneadoHasta IS NULL OR u_cond.BaneadoHasta <= NOW())
      AND v.Estado = 'Aceptado'
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    safe_error("El viaje no existe o ya no esta disponible.");
}

if ($viaje['ocupados'] >= $viaje['asientos']) {
    safe_error("No hay asientos disponibles en este viaje.");
}

/* Evitar reservar propio viaje */
if ($viaje['ID_usuario'] == $_SESSION['user_id']) {
    safe_error("No podes reservar tu propio viaje.");
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
    safe_error("Ya tenes una reserva confirmada para este viaje.");
}

if (!PAYMENTS_ENABLED) {
    try {
        $pdo->beginTransaction();

        $stmt_lock = $pdo->prepare("
            SELECT v.CantidadAsientos AS total,
                   (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') AS ocupados
            FROM Publicaciones p
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
            JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
            JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
            WHERE p.ID_publicacion = ?
              AND p.Estado = 'Activa'
              AND p.HoraSalida >= NOW()
              AND c.Estado = 'Aceptada'
              AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW())
              AND u_cond.estado = 'activo'
              AND (u_cond.BaneadoHasta IS NULL OR u_cond.BaneadoHasta <= NOW())
              AND v.Estado = 'Aceptado'
            FOR UPDATE
        ");
        $stmt_lock->execute([$viaje_id]);
        $check = $stmt_lock->fetch(PDO::FETCH_ASSOC);

        if (!$check || $check['ocupados'] >= $check['total']) {
            $pdo->rollBack();
            safe_error("Lo sentimos, los asientos se agotaron justo ahora. Intenta con otro viaje.");
        }

        $stmt_dup_lock = $pdo->prepare("
            SELECT COUNT(*)
            FROM Reservas r
            JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
            JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
            WHERE r.ID_publicacion = ?
              AND pas.ID_usuario = ?
              AND r.Estado = 'Completada'
        ");
        $stmt_dup_lock->execute([$viaje_id, $_SESSION['user_id']]);
        if ($stmt_dup_lock->fetchColumn() > 0) {
            $pdo->rollBack();
            safe_error("Ya tenes una reserva confirmada para este viaje.");
        }

        $stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
        $stmt_pasajero->execute([$_SESSION['user_id']]);
        $pasajero = $stmt_pasajero->fetch();

        if (!$pasajero) {
            $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)")->execute([$_SESSION['user_id']]);
            $pasajero_id = $pdo->lastInsertId();
        } else {
            $pasajero_id = $pasajero['ID_pasajero'];
        }

        $codigo_acceso = "CA-" . strtoupper(bin2hex(random_bytes(4)));

        $stmt_res = $pdo->prepare("
            INSERT INTO Reservas
                (ID_publicacion, Estado, FechaReserva, CodigoAcceso, TipoPasaje, PasajeroNombre, PasajeroApellido, PasajeroDNI, PasajeroTelefono, PasajeroCorreo, ID_usuario_responsable)
            VALUES
                (?, 'Completada', NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_res->execute([
            $viaje_id,
            $codigo_acceso,
            $tipo_pasaje,
            $pasajero_data['nombre'],
            $pasajero_data['apellido'],
            $pasajero_data['dni'],
            $pasajero_data['telefono'],
            $pasajero_data['correo'] !== '' ? $pasajero_data['correo'] : null,
            $_SESSION['user_id']
        ]);
        $reserva_id = $pdo->lastInsertId();

        $pdo->prepare("INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)")->execute([$pasajero_id, $reserva_id]);

        $pdo->commit();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <title>Reserva confirmada - MOVEON</title>
            <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
        </head>
        <body>
            <div class="page-shell">
                <div class="card" style="max-width:560px; margin:60px auto; text-align:center;">
                    <div class="badge badge-success" style="display:inline-flex; margin-bottom:16px;">Reserva confirmada</div>
                    <h1 class="page-title" style="font-size:32px;">Tu asiento ya está reservado</h1>
                    <p class="page-subtitle" style="font-size:18px; margin-bottom:24px;">
                        No se realizo ningun cobro online. Coordina el pago del viaje con el conductor segun lo acordado.
                    </p>

                    <div class="info-tile" style="text-align:center; margin:22px 0;">
                        <span>Código de validación</span>
                        <strong style="font-size:30px; letter-spacing:2px; color:var(--primary);"><?= htmlspecialchars($codigo_acceso) ?></strong>
                        <p class="text-muted" style="margin:10px 0 0;">Mostráselo al conductor al momento de abordar.</p>
                    </div>

                    <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="btn">Ver mis reservas</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creando reserva sin pago online: " . $e->getMessage());
        safe_error("No se pudo completar la reserva.");
    }
}

/* Verificar Saldo del Usuario */
$stmt_saldo = $pdo->prepare("SELECT Saldo FROM Usuarios WHERE ID_usuario = ?");
$stmt_saldo->execute([$_SESSION['user_id']]);
$saldo_usuario = (float)$stmt_saldo->fetchColumn();

if ($saldo_usuario >= $viaje['Precio']) {
    // Pago automático con Saldo
    try {
        $pdo->beginTransaction();
        
        // --- PREVENCIÓN DE RACE CONDITION ---
        // Bloqueamos la fila de la publicación para que nadie más pueda consultar el cupo simultáneamente
        $stmt_lock = $pdo->prepare("
            SELECT v.CantidadAsientos AS total,
                   (SELECT COUNT(*) FROM Reservas r WHERE r.ID_publicacion = p.ID_publicacion AND r.Estado = 'Completada') AS ocupados
            FROM Publicaciones p
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
            JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
            JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
            WHERE p.ID_publicacion = ?
              AND p.Estado = 'Activa'
              AND p.HoraSalida >= NOW()
              AND c.Estado = 'Aceptada'
              AND (c.BaneadoHasta IS NULL OR c.BaneadoHasta <= NOW())
              AND u_cond.estado = 'activo'
              AND (u_cond.BaneadoHasta IS NULL OR u_cond.BaneadoHasta <= NOW())
              AND v.Estado = 'Aceptado'
            FOR UPDATE
        ");
        $stmt_lock->execute([$viaje_id]);
        $check = $stmt_lock->fetch(PDO::FETCH_ASSOC);

        if (!$check || $check['ocupados'] >= $check['total']) {
            $pdo->rollBack();
            safe_error("Lo sentimos, los asientos se agotaron justo ahora. Intenta con otro viaje.");
        }
        // ------------------------------------

        $stmt_dup_lock = $pdo->prepare("
            SELECT COUNT(*)
            FROM Reservas r
            JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
            JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
            WHERE r.ID_publicacion = ?
              AND pas.ID_usuario = ?
              AND r.Estado = 'Completada'
        ");
        $stmt_dup_lock->execute([$viaje_id, $_SESSION['user_id']]);
        if ($stmt_dup_lock->fetchColumn() > 0) {
            $pdo->rollBack();
            safe_error("Ya tenes una reserva confirmada para este viaje.");
        }

        // 1. Descontar saldo
        $stmt_desc = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo - ? WHERE ID_usuario = ? AND Saldo >= ?");
        $stmt_desc->execute([$viaje['Precio'], $_SESSION['user_id'], $viaje['Precio']]);
        if ($stmt_desc->rowCount() !== 1) {
            $pdo->rollBack();
            safe_error("Saldo insuficiente para completar la reserva.");
        }
        
        // 2. Obtener o crear ID Pasajero
        $stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
        $stmt_pasajero->execute([$_SESSION['user_id']]);
        $pasajero = $stmt_pasajero->fetch();
        
        if (!$pasajero) {
            $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)")->execute([$_SESSION['user_id']]);
            $pasajero_id = $pdo->lastInsertId();
        } else {
            $pasajero_id = $pasajero['ID_pasajero'];
        }
        
        // 3. Generar código de acceso único
        $codigo_acceso = "CA-" . strtoupper(bin2hex(random_bytes(4)));
        
        // 4. Crear Reserva Completada
        $stmt_res = $pdo->prepare("
            INSERT INTO Reservas
                (ID_publicacion, Estado, FechaReserva, CodigoAcceso, TipoPasaje, PasajeroNombre, PasajeroApellido, PasajeroDNI, PasajeroTelefono, PasajeroCorreo, ID_usuario_responsable)
            VALUES
                (?, 'Completada', NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_res->execute([
            $viaje_id,
            $codigo_acceso,
            $tipo_pasaje,
            $pasajero_data['nombre'],
            $pasajero_data['apellido'],
            $pasajero_data['dni'],
            $pasajero_data['telefono'],
            $pasajero_data['correo'] !== '' ? $pasajero_data['correo'] : null,
            $_SESSION['user_id']
        ]);
        $reserva_id = $pdo->lastInsertId();
        
        // 5. Vincular Pasajero a Reserva
        $pdo->prepare("INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)")->execute([$pasajero_id, $reserva_id]);
        
        // 6. Registrar pago
        $stmt_pago = $pdo->prepare("INSERT INTO Pagos (Monto, Estado, ID_reserva) VALUES (?, 'Completado', ?)");
        $stmt_pago->execute([$viaje['Precio'], $reserva_id]);
        
        $pdo->commit();

        
        // Mostrar éxito
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="utf-8">
            <title>Pago Exitoso con Saldo - Carpooling</title>
            <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
        </head>
        <body>
            <div style="max-width: 500px; margin: 50px auto; padding: 30px; border: 1px solid #c3e6cb; border-radius: 8px; background-color: #d4edda; text-align: center;">
                <h2 style="color: #155724;">¡Reserva y Pago Exitosos!</h2>
                <p style="color: #155724; font-size: 1.1em;">Tu lugar ha sido asegurado usando el saldo de tu billetera virtual.</p>
                
                <div style="background-color: white; padding: 20px; border-radius: 6px; margin: 20px 0;">
                    <p style="margin:0; color:#6c757d;">Tu código secreto de acceso al auto es:</p>
                    <h1 style="margin: 10px 0; color: #007bff; letter-spacing: 2px;"><?= htmlspecialchars($codigo_acceso) ?></h1>
                    <p style="margin:0; font-size: 0.9em; color:#6c757d;">Muéstraselo al conductor antes de iniciar el viaje.</p>
                </div>
                
                <p>Monto descontado: <strong>$<?= number_format($viaje['Precio'], 2, ',', '.') ?></strong></p>
                <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="btn" style="display:inline-block; margin-top:20px;">Ver mis reservas activas</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error pagando con saldo: " . $e->getMessage());
        safe_error("No se pudo completar la reserva con saldo.");
    }
}

/* 
 * IMPORTANTE: Ya no creamos la reserva "Pendiente" en la base de datos.
 * Vamos a pasar el ID de viaje y de usuario por la externa_reference a MP.
 * Solo guardaremos la reserva si el pago es exitoso.
 */
$external_reference = bin2hex(random_bytes(24));
$_SESSION['pending_reserva'][$external_reference] = [
    'viaje_id' => $viaje_id,
    'user_id' => (int)$_SESSION['user_id'],
    'tipo_pasaje' => $tipo_pasaje,
    'pasajero' => $pasajero_data,
    'created_at' => time(),
];

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
        "success" => app_public_url("reservas/mp_success.php?external_reference=" . urlencode($external_reference)),
        "failure" => app_public_url("reservas/mp_failure.php?external_reference=" . urlencode($external_reference)),
        "pending" => app_public_url("reservas/mp_pending.php?external_reference=" . urlencode($external_reference))
    ),
    "external_reference" => $external_reference
);

if (stripos(app_public_url(), 'https://') === 0) {
    $preference_data["auto_return"] = "approved";
}

$mp_result = mp_create_preference($preference_data);
$sandbox_url = $mp_result['ok'] ? mp_checkout_url($mp_result['data']) : '';

if ($sandbox_url !== '') {
    header("Location: " . $sandbox_url);
    exit;
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
            
            <a href="<?= $sandbox_url ?>" onclick="this.style.display='none'; document.getElementById('post-pago').style.display='block';" style="display:inline-block; padding:15px 30px; background-color:#009ee3; color:white; font-size:1.2em; text-decoration:none; border-radius:5px; font-weight:bold; box-shadow: 0 2px 5px rgba(0,158,227,0.4); margin-bottom:15px;">
                Pagar en Mercado Pago 🔒
            </a>
            
            <div id="post-pago" style="display:none; margin-top: 40px; padding-top: 25px; border-top: 1px solid #eee;">
                <h3 style="color:#28a745;">¿Completaste el pago exitosamente?</h3>
                <?php if (mp_local_test_mode()): ?>
                    <p style="color: #666; font-size:0.9em; margin-bottom:15px;">Modo prueba local habilitado. Usalo solo si Mercado Pago no puede redirigir al entorno local.</p>
                    <a href="<?= BASE_URL ?>reservas/mp_success.php?collection_status=approved&external_reference=<?= htmlspecialchars($external_reference) ?>&local_test=1" class="btn" style="background-color:#28a745; border-color:#28a745; width:100%; box-sizing:border-box; font-size:1.1em; color: white; display: inline-block; padding: 15px; border-radius: 5px; text-decoration: none;">
                        Confirmar reserva en modo prueba local
                    </a>
                <?php else: ?>
                    <p style="color: #666; font-size:0.9em; margin-bottom:15px;">Cuando Mercado Pago apruebe la operacion, volveras automaticamente a MOVEON y se generara tu codigo de viaje.</p>
                <?php endif; ?>
            </div>
            
            <script>
                // Abrimos el popup e inmediatamente bloqueamos intentos infinitos
                setTimeout(function() {
                    const btn = document.querySelector('a[href="<?= $sandbox_url ?>"]');
                    if (btn && btn.style.display !== 'none') {
                        window.location.href = '<?= $sandbox_url ?>';
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
    unset($_SESSION['pending_reserva'][$external_reference]);
    error_log("Error Mercado Pago reserva. Error: " . ($mp_result['error'] ?? '') . " status=" . ($mp_result['status'] ?? 0));
    safe_error("No se pudo conectar con Mercado Pago. Intenta nuevamente en unos minutos.");
}
