<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';

$errores = [];
$values = [
    'nombre' => '',
    'apellido' => '',
    'dni' => '',
    'telefono' => '',
    'email' => '',
];

function validar_dni_imagen(string $campo, array &$errores): ?string
{
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        $errores[] = 'Tenes que subir frente y dorso del DNI.';
        return null;
    }

    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'No se pudo subir una de las imagenes del DNI.';
        return null;
    }

    if ($_FILES[$campo]['size'] > 2 * 1024 * 1024) {
        $errores[] = 'Cada imagen del DNI debe pesar menos de 2MB.';
        return null;
    }

    $tmp = $_FILES[$campo]['tmp_name'];
    $info = @getimagesize($tmp);
    if ($info === false) {
        $errores[] = 'El archivo del DNI debe ser una imagen valida.';
        return null;
    }

    $permitidos = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    if (!isset($permitidos[$info[2]])) {
        $errores[] = 'Las imagenes del DNI solo pueden ser JPG, PNG o WEBP.';
        return null;
    }

    if ($info[0] < 300 || $info[1] < 180) {
        $errores[] = 'Las imagenes del DNI deben tener buena resolucion.';
        return null;
    }

    return 'data:' . $permitidos[$info[2]] . ';base64,' . base64_encode(file_get_contents($tmp));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $values['nombre'] = trim($_POST['nombre'] ?? '');
    $values['apellido'] = trim($_POST['apellido'] ?? '');
    $values['dni'] = trim($_POST['dni'] ?? '');
    $values['telefono'] = trim($_POST['telefono'] ?? '');
    $values['email'] = trim($_POST['email'] ?? '');
    $raw_password = $_POST['password'] ?? '';
    $password = password_hash($raw_password, PASSWORD_DEFAULT);

    if (strlen($values['nombre']) < 2 || strlen($values['nombre']) > 100) $errores[] = 'El nombre debe tener entre 2 y 100 caracteres.';
    if (strlen($values['apellido']) < 2 || strlen($values['apellido']) > 100) $errores[] = 'El apellido debe tener entre 2 y 100 caracteres.';
    if (!preg_match('/^[\p{L}\s]+$/u', $values['nombre'])) $errores[] = 'El nombre solo puede contener letras y espacios.';
    if (!preg_match('/^[\p{L}\s]+$/u', $values['apellido'])) $errores[] = 'El apellido solo puede contener letras y espacios.';
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 254) $errores[] = 'El correo electronico no es valido.';
    if (!preg_match('/^[0-9]{7,8}$/', $values['dni'])) $errores[] = 'El DNI debe tener 7 u 8 digitos numericos.';
    if (!preg_match('/^[0-9]{8,15}$/', $values['telefono'])) $errores[] = 'El telefono debe tener entre 8 y 15 digitos numericos.';
    if (strlen($raw_password) < 8 || strlen($raw_password) > 72) $errores[] = 'La contrasena debe tener entre 8 y 72 caracteres.';

    $dni_frente = validar_dni_imagen('dni_frente', $errores);
    $dni_dorso = validar_dni_imagen('dni_dorso', $errores);

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Usuarios (Nombre, Apellido, DNI, DniFrenteImagen, DniDorsoImagen, Correo, Telefono, Contraseña)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $values['nombre'],
                $values['apellido'],
                $values['dni'],
                $dni_frente,
                $dni_dorso,
                $values['email'],
                $values['telefono'],
                $password
            ]);

            $id_usuario = $pdo->lastInsertId();
            $stmt2 = $pdo->prepare('INSERT INTO Pasajeros (ID_usuario) VALUES (?)');
            $stmt2->execute([$id_usuario]);

            header('Location: ' . BASE_URL . 'login.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000 || strpos($e->getMessage(), '1062') !== false) {
                if (strpos($e->getMessage(), 'Correo') !== false || strpos($e->getMessage(), 'email') !== false) {
                    $errores[] = 'Este mail ya esta registrado.';
                } elseif (strpos($e->getMessage(), 'DNI') !== false) {
                    $errores[] = 'Este DNI ya esta registrado.';
                } else {
                    $errores[] = 'Ya existe una cuenta con alguno de esos datos.';
                }
            } else {
                $errores[] = 'Ocurrio un error al registrar el usuario.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Crear cuenta - MOVEON</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= filemtime(__DIR__ . '/main.css') ?>">
    <script src="<?= BASE_URL ?>main.js?v=<?= time() ?>" defer></script>
</head>
<body class="auth-body">
    <main class="auth-shell" style="width:min(100%, 620px);">
        <div class="auth-topbar">
            <a class="auth-brand" href="<?= BASE_URL ?>index.php">
                <img src="<?= BASE_URL ?>assets/moveon-logo.svg" alt="MOVEON">
                <span>MOVEON</span>
            </a>
            <a href="<?= BASE_URL ?>index.php">&larr; Volver</a>
        </div>

        <section class="auth-card">
            <h1>Crear cuenta</h1>
            <p class="page-subtitle">Registrate para reservar viajes y gestionar tu perfil.</p>

            <?php if (!empty($errores)): ?>
                <div class="alert-error">
                    <ul style="margin:0; padding-left:18px;">
                        <?php foreach ($errores as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <div class="auth-grid">
                    <div>
                        <label>Nombre</label>
                        <input type="text" name="nombre" placeholder="Ej: Juan" required autocomplete="given-name" minlength="2" maxlength="50" value="<?= htmlspecialchars($values['nombre']) ?>">
                    </div>

                    <div>
                        <label>Apellido</label>
                        <input type="text" name="apellido" placeholder="Ej: Perez" required autocomplete="family-name" minlength="2" maxlength="50" value="<?= htmlspecialchars($values['apellido']) ?>">
                    </div>

                    <div>
                        <label>DNI</label>
                        <input type="text" name="dni" placeholder="Ej: 12345678" required pattern="[0-9]{7,8}" title="Debe contener 7 u 8 numeros" autocomplete="off" minlength="7" maxlength="8" value="<?= htmlspecialchars($values['dni']) ?>">
                    </div>

                    <div>
                        <label>Telefono</label>
                        <input type="tel" name="telefono" placeholder="Ej: 3431234567" required pattern="[0-9]{8,15}" title="Ingrese un numero de telefono valido" autocomplete="tel" minlength="10" maxlength="15" value="<?= htmlspecialchars($values['telefono']) ?>">
                    </div>

                    <div class="full">
                        <label>Correo electronico</label>
                        <input type="email" name="email" placeholder="ejemplo@email.com" required autocomplete="email" minlength="5" maxlength="254" value="<?= htmlspecialchars($values['email']) ?>">
                    </div>

                    <div class="full">
                        <label>Contrasena</label>
                        <input type="password" name="password" placeholder="Minimo 8 caracteres" required autocomplete="new-password" minlength="8" maxlength="72">
                    </div>

                    <div>
                        <label>DNI frente</label>
                        <input type="file" name="dni_frente" accept="image/jpeg,image/png,image/webp" required>
                    </div>

                    <div>
                        <label>DNI dorso</label>
                        <input type="file" name="dni_dorso" accept="image/jpeg,image/png,image/webp" required>
                    </div>
                </div>

                <button type="submit">Registrarse</button>

                <p class="auth-footer">
                    Ya tenes cuenta? <a href="<?= BASE_URL ?>login.php">Inicia sesion</a>
                </p>
            </form>
        </section>
    </main>
</body>
</html>
