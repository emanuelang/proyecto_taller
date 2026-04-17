<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_propio = true;

// Si se recibe un ID por GET, mostramos el perfil de otro usuario (público)
if (isset($_GET['id']) && (int)$_GET['id'] !== $user_id) {
    $user_id = (int)$_GET['id'];
    $es_propio = false;
}

$stmt = $pdo->prepare("SELECT Nombre, Apellido, Correo, Telefono, FotoPerfil, Descripcion, Preferencias FROM Usuarios WHERE ID_usuario = ?");
$stmt->execute([$user_id]);
$perfil = $stmt->fetch();

if (!$perfil) {
    die("Usuario no encontrado.");
}

// Comprobar si el usuario es conductor para extraer sus calificaciones y porcentaje
$stmt_cond = $pdo->prepare("SELECT ID_conductor FROM Conductores WHERE ID_usuario = ? AND Estado = 'Aceptada'");
$stmt_cond->execute([$user_id]);
$conductor = $stmt_cond->fetch();

$promedio = null;
$reseñas = [];
if ($conductor) {
    $c_id = $conductor['ID_conductor'];
    
    // Promedio (Uber style) - ejemplo: 4.8 / 5
    $stmt_prom = $pdo->prepare("SELECT AVG(Puntuacion) as avg_p FROM Calificaciones WHERE ID_conductor = ?");
    $stmt_prom->execute([$c_id]);
    $prom = $stmt_prom->fetch();
    if ($prom && $prom['avg_p']) {
        $promedio = round($prom['avg_p'], 1);
    }
    
    // Extraer reseñas anónimas
    $stmt_res = $pdo->prepare("SELECT Puntuacion, Comentario FROM Calificaciones WHERE ID_conductor = ? ORDER BY ID_calificacion DESC");
    $stmt_res->execute([$c_id]);
    $reseñas = $stmt_res->fetchAll();
}

$mensaje = "";

// Actualización del perfil (solo si es el propio perfil)
if ($es_propio && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $desc = trim($_POST['descripcion']);
    $prefs = trim($_POST['preferencias']);
    $foto_perfil = $perfil['FotoPerfil'];

    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['foto_perfil']['tmp_name'];
        $tipoMime = mime_content_type($tmpName);
        if (strpos($tipoMime, 'image/') === 0) {
            $base64 = base64_encode(file_get_contents($tmpName));
            $foto_perfil = "data:" . $tipoMime . ";base64," . $base64;
        }
    }

    $stmt_upd = $pdo->prepare("UPDATE Usuarios SET Descripcion = ?, Preferencias = ?, FotoPerfil = ? WHERE ID_usuario = ?");
    $stmt_upd->execute([$desc, $prefs, $foto_perfil, $user_id]);
    
    // Refresh
    $perfil['Descripcion'] = $desc;
    $perfil['Preferencias'] = $prefs;
    $perfil['FotoPerfil'] = $foto_perfil;
    $mensaje = "Perfil actualizado exitosamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Perfil - <?= htmlspecialchars($perfil['Nombre']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css?v=<?= time() ?>">
    <style>
        .perfil-container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        .col-izq {
            flex: 1;
            min-width: 250px;
            text-align: center;
        }
        .col-der {
            flex: 2;
            min-width: 300px;
        }
        .foto-perfil {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--border-color);
            margin: 0 auto 15px auto;
            background-color: #f1f5f9;
        }
        .puntaje {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        .puntaje span {
            color: #fbbf24; /* Estrella dorada */
        }
        .resena-caja {
            background: #F8FAFC;
            border-left: 4px solid #CBD5E1;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
    </style>
</head>
<body style="background-color: #f1f5f9;">

<div class="nav-menu">
    <h2>Perfil de <?= htmlspecialchars($perfil['Nombre']) ?></h2>
    <a href="<?= BASE_URL ?>index.php" style="margin-left: auto;">← Volver al inicio</a>
</div>

<?php if ($mensaje): ?>
    <div style="max-width:800px; margin: 0 auto; background-color: #d4edda; color: #155724; padding: 15px; border-radius: 6px; text-align: center;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div class="perfil-container">
    <div class="col-izq">
        <?php if ($perfil['FotoPerfil']): ?>
            <img src="<?= $perfil['FotoPerfil'] ?>" class="foto-perfil" alt="Foto">
        <?php else: ?>
            <div class="foto-perfil" style="display:flex; align-items:center; justify-content:center; color:#94A3B8; font-size: 3em;">👤</div>
        <?php endif; ?>
        
        <h2 style="margin: 0;"><?= htmlspecialchars($perfil['Nombre'] . ' ' . $perfil['Apellido']) ?></h2>
        
        <?php if ($conductor): ?>
            <p style="color: var(--success); font-weight: bold; margin: 5px 0;">Conductor Verificado ✅</p>
            
            <?php if ($promedio): ?>
                <div class="puntaje">
                    <?= $promedio ?> <span>★</span>
                </div>
            <?php else: ?>
                <p style="color: #64748B;">Nuevo / Sin calificaciones</p>
            <?php endif; ?>
        <?php else: ?>
            <p style="color: #64748B; font-weight: bold; margin: 5px 0;">Pasajero</p>
        <?php endif; ?>
    </div>
    
    <div class="col-der">
        <?php if ($es_propio): ?>
            <form method="POST" enctype="multipart/form-data" style="padding:0; border:none; box-shadow:none; max-width:100%;">
                
                <h3 style="margin-top:0;">Mi Información</h3>
                <label>Foto de Perfil:</label><br>
                <input type="file" name="foto_perfil" accept="image/*"><br><br>

                <label>Sobre Mí (Descripción general):</label><br>
                <textarea name="descripcion" rows="3" placeholder="Ej: Me llamo Juan, soy estudiante y viajo seguido a la capital..." style="width:100%;"><?= htmlspecialchars($perfil['Descripcion'] ?? '') ?></textarea><br><br>

                <label>Mis Preferencias de Viaje:</label><br>
                <textarea name="preferencias" rows="3" placeholder="Ej: Me gusta viajar escuchando música tranquila, no tolero el cigarrillo, me gusta charlar." style="width:100%;"><?= htmlspecialchars($perfil['Preferencias'] ?? '') ?></textarea><br><br>
                
                <button type="submit" class="btn" style="background-color: var(--primary);">Guardar Perfil</button>
            </form>
        <?php else: ?>
            <h3 style="margin-top:0;">Sobre Mí</h3>
            <p style="white-space: pre-line; color: #334155;"><?= htmlspecialchars($perfil['Descripcion'] ?? 'Este usuario aún no ha escrito nada sobre sí mismo.') ?></p>
            
            <h3 style="margin-top: 25px;">Preferencias de Viaje</h3>
            <p style="white-space: pre-line; color: #334155;">
                <?= htmlspecialchars($perfil['Preferencias'] ?? 'Sin preferencias especificadas.') ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if ($conductor): ?>
    <div style="max-width: 800px; margin: 0 auto 40px auto;">
        <h3>Reseñas como conductor</h3>
        <?php if (empty($reseñas)): ?>
            <p style="color: #64748B;">Todavía no hay reseñas anónimas sobre este conductor.</p>
        <?php else: ?>
            <?php foreach ($reseñas as $res): ?>
                <div class="resena-caja">
                    <div style="color: #fbbf24; font-size: 1.2em; font-weight:bold; margin-bottom: 5px;">
                        <?= str_repeat('★', $res['Puntuacion']) ?><?= str_repeat('☆', 5 - $res['Puntuacion']) ?>
                    </div>
                    <p style="margin:0; color:#334155; font-style:italic;">
                        "<?= htmlspecialchars($res['Comentario'] ?? 'Sin comentario') ?>"
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
