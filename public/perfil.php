<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$es_propio = true;

// Si se recibe un ID por GET, mostramos el perfil de otro usuario público.
if (isset($_GET['id']) && (int)$_GET['id'] !== $user_id) {
    $user_id = (int)$_GET['id'];
    $es_propio = false;
}

$modo_edicion = $es_propio && isset($_GET['editar']) && $_GET['editar'] === '1';

$stmt = $pdo->prepare("SELECT Nombre, Apellido, Correo, Telefono, FotoPerfil, Descripcion, Preferencias, Saldo FROM Usuarios WHERE ID_usuario = ?");
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
$resenas = [];
$reportes = [];
if ($conductor) {
    $c_id = $conductor['ID_conductor'];
    
    // Promedio (Uber style) - ejemplo: 4.8 / 5
    $stmt_prom = $pdo->prepare("SELECT AVG(Puntuacion) as avg_p FROM Calificaciones WHERE ID_conductor = ?");
    $stmt_prom->execute([$c_id]);
    $prom = $stmt_prom->fetch();
    if ($prom && $prom['avg_p']) {
        $promedio = number_format(floor($prom['avg_p'] * 10) / 10, 1, ',', '.');
    }
    
    // Extraer reseñas anónimas.
    $stmt_res = $pdo->prepare("SELECT Puntuacion, Comentario FROM Calificaciones WHERE ID_conductor = ? ORDER BY ID_calificacion DESC");
    $stmt_res->execute([$c_id]);
    $resenas = $stmt_res->fetchAll();

    // Extraer reportes anónimos (quejas).
    $stmt_rep = $pdo->prepare("SELECT Descripcion, Fecha, Hora FROM Reportes WHERE ID_conductor = ? ORDER BY ID_reporte DESC");
    $stmt_rep->execute([$c_id]);
    $reportes = $stmt_rep->fetchAll();
}

$mensaje = "";
$error = "";

// Actualización del perfil (solo si es el propio perfil).
if ($es_propio && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $desc = trim($_POST['descripcion']);
    $prefs = trim($_POST['preferencias']);
    $foto_perfil = $perfil['FotoPerfil'];

    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['foto_perfil']['tmp_name'];
        $tipoMime = mime_content_type($tmpName);
        if ($_FILES['foto_perfil']['size'] > 2 * 1024 * 1024) {
            $error = "La foto no puede superar los 2 MB.";
        } elseif (strpos($tipoMime, 'image/') === 0) {
            $base64 = base64_encode(file_get_contents($tmpName));
            $foto_perfil = "data:" . $tipoMime . ";base64," . $base64;
        } else {
            $error = "El archivo seleccionado no es una imagen válida.";
        }
    } elseif (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) {
        $error = "No se pudo subir la foto. Probá con una imagen más liviana.";
    }

    if (!$error) {
        $stmt_upd = $pdo->prepare("UPDATE Usuarios SET Descripcion = ?, Preferencias = ?, FotoPerfil = ? WHERE ID_usuario = ?");
        $stmt_upd->execute([$desc, $prefs, $foto_perfil, $user_id]);
        
        // Refresh
        $perfil['Descripcion'] = $desc;
        $perfil['Preferencias'] = $prefs;
        $perfil['FotoPerfil'] = $foto_perfil;
        $mensaje = "Perfil actualizado exitosamente.";
        $modo_edicion = false;
    }
}
?>

<?php require_once __DIR__ . '/header.php'; ?>

<div class="page-shell">
    <h1 class="page-title">Perfil de <?= htmlspecialchars($perfil['Nombre']) ?></h1>
    <p class="page-subtitle"><?= $es_propio ? 'Gestioná tu información, billetera y preferencias' : 'Información pública del usuario' ?></p>
<?php if ($mensaje): ?>
    <div style="max-width:800px; margin: 0 auto; background-color: #d4edda; color: #155724; padding: 15px; border-radius: 6px; text-align: center;">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="max-width:800px; margin: 0 auto 16px; background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 6px; text-align: center;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="perfil-container">
    <div class="col-izq">
        <?php if ($perfil['FotoPerfil']): ?>
            <img src="<?= htmlspecialchars($perfil['FotoPerfil']) ?>" class="foto-perfil" alt="Foto" id="profilePreview">
        <?php else: ?>
            <div class="foto-perfil" id="profilePlaceholder" style="display:flex; align-items:center; justify-content:center; color:#94A3B8; font-size: 3em; font-weight:800;"><?= htmlspecialchars(strtoupper(substr($perfil['Nombre'], 0, 1))) ?></div>
        <?php endif; ?>
        
        <h2 style="margin: 0;"><?= htmlspecialchars($perfil['Nombre'] . ' ' . $perfil['Apellido']) ?></h2>
        
        <?php if ($conductor): ?>
            <p style="color: var(--success); font-weight: bold; margin: 5px 0;">Conductor verificado</p>
            
            <?php if ($promedio): ?>
                <div class="puntaje">
                    <?= $promedio ?> <span>&starf;</span>
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
            <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 20px; margin-bottom: 30px; text-align: center;">
                <h3 style="margin-top:0; color: #166534;">Mi Billetera</h3>
                <div style="font-size: 2.5em; font-weight: bold; color: #15803d; margin: 10px 0;">
                    $<?= number_format($perfil['Saldo'] ?? 0, 2, ',', '.') ?>
                </div>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 15px;">
                    <a href="ingresar_dinero.php" class="btn" style="background-color: #22c55e; color: white; border: none; text-decoration: none; padding: 10px 20px; border-radius: 5px;">Ingresar Dinero</a>
                    <a href="retirar_dinero.php" class="btn" style="background-color: #3b82f6; color: white; border: none; text-decoration: none; padding: 10px 20px; border-radius: 5px;">Retirar Dinero</a>
                </div>
            </div>

            <?php if ($modo_edicion): ?>
                <form id="perfilEditForm" method="POST" enctype="multipart/form-data" style="padding:0; border:none; box-shadow:none; max-width:100%;">
                    <?= csrf_field() ?>
                    <h3 style="margin-top:0;">Editar perfil</h3>
                    <label>Foto de Perfil:</label><br>
                    <input type="file" name="foto_perfil" accept="image/*" id="foto_perfil_input"><br>
                    <small class="text-muted">Al guardar, esta foto se verá en tu perfil y en tus viajes publicados.</small><br><br>

                    <label>Sobre mí (descripción general):</label><br>
                    <textarea name="descripcion" rows="3" placeholder="Ej: Me llamo Juan, soy estudiante y viajo seguido a la capital..." style="width:100%;" minlength="20" maxlength="500"><?= htmlspecialchars($perfil['Descripcion'] ?? '') ?></textarea><br><br>

                    <label>Mis preferencias de viaje:</label><br>
                    <textarea name="preferencias" rows="3" placeholder="Ej: Me gusta viajar escuchando música tranquila, no tolero el cigarrillo, me gusta charlar." style="width:100%;" minlength="10" maxlength="300"><?= htmlspecialchars($perfil['Preferencias'] ?? '') ?></textarea><br><br>

                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        <button type="submit" class="btn" style="background-color: var(--primary);" onclick="return confirm('¿Querés guardar los cambios del perfil?');">Guardar cambios</button>
                        <a href="<?= BASE_URL ?>perfil.php" class="btn btn-outline" id="discardProfileChanges">Descartar cambios</a>
                    </div>
                </form>
            <?php else: ?>
                <div>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:18px; flex-wrap:wrap;">
                        <h3 style="margin:0;">Mi información</h3>
                        <a href="<?= BASE_URL ?>perfil.php?editar=1" class="btn btn-outline">Editar perfil</a>
                    </div>

                    <div class="info-grid">
                        <div class="info-tile">
                            <span>Correo electrónico</span>
                            <strong><?= htmlspecialchars($perfil['Correo'] ?? 'Sin correo') ?></strong>
                        </div>
                        <div class="info-tile">
                            <span>Teléfono</span>
                            <strong><?= htmlspecialchars($perfil['Telefono'] ?: 'Sin teléfono') ?></strong>
                        </div>
                    </div>

                    <div class="info-tile" style="margin-top:20px;">
                        <span>Sobre mí</span>
                        <p style="white-space:pre-line; margin:10px 0 0; color:#334155;"><?= htmlspecialchars($perfil['Descripcion'] ?: 'Todavía no escribiste una descripción.') ?></p>
                    </div>

                    <div class="info-tile" style="margin-top:20px;">
                        <span>Preferencias de viaje</span>
                        <p style="white-space:pre-line; margin:10px 0 0; color:#334155;"><?= htmlspecialchars($perfil['Preferencias'] ?: 'Todavía no cargaste preferencias de viaje.') ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                <h3 style="color: #ef4444; margin-top: 0;">Zona de Riesgo</h3>
                <p style="color: #64748b;">Eliminar tu cuenta borrará permanentemente todos tus datos, vehículos, viajes creados, reservas y reseñas. Esta acción no se puede deshacer.</p>
                <form method="POST" action="eliminar_perfil.php" style="padding:0; border:none; box-shadow:none;" onsubmit="return confirm('¿Estás seguro que deseas eliminar tu perfil de forma permanente?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn" style="background-color: #ef4444; color: white; width: 100%;">Eliminar mi Perfil</button>
                </form>
            </div>
        <?php else: ?>
            <h3 style="margin-top:0;">Sobre mí</h3>
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
        <?php if (empty($resenas)): ?>
            <p style="color: #64748B;">Todavía no hay reseñas anónimas sobre este conductor.</p>
        <?php else: ?>
            <?php foreach ($resenas as $res): ?>
                <div class="resena-caja">
                    <div style="color: #fbbf24; font-size: 1.2em; font-weight:bold; margin-bottom: 5px;">
                        <?= str_repeat('&starf;', (int)$res['Puntuacion']) ?><?= str_repeat('&star;', 5 - (int)$res['Puntuacion']) ?>
                    </div>
                    <p style="margin:0; color:#334155; font-style:italic;">
                        "<?= htmlspecialchars($res['Comentario'] ?? 'Sin comentario') ?>"
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h3 style="margin-top: 40px; color: #ef4444;">Reportes y Quejas</h3>
        <?php if (empty($reportes)): ?>
            <p style="color: #64748B;">Este conductor no tiene reportes negativos.</p>
        <?php else: ?>
            <?php foreach ($reportes as $rep): ?>
                <div class="resena-caja" style="border-left-color: #ef4444; background: #fef2f2;">
                    <div style="color: #ef4444; font-size: 0.9em; font-weight:bold; margin-bottom: 5px;">
                        Reporte anónimo del <?= date('d/m/Y', strtotime($rep['Fecha'])) ?>
                    </div>
                    <p style="margin:0; color:#334155; font-style:italic;">
                        "<?= nl2br(htmlspecialchars($rep['Descripcion'])) ?>"
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

</div>
<script>
document.getElementById('foto_perfil_input')?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    if (!file || !file.type.startsWith('image/')) return;

    const src = URL.createObjectURL(file);
    let preview = document.getElementById('profilePreview');
    const placeholder = document.getElementById('profilePlaceholder');

    if (!preview) {
        preview = document.createElement('img');
        preview.id = 'profilePreview';
        preview.className = 'foto-perfil';
        preview.alt = 'Foto';
        placeholder?.replaceWith(preview);
    }

    preview.src = src;
});

const perfilEditForm = document.getElementById('perfilEditForm');
if (perfilEditForm) {
    let profileDirty = false;
    let profileSubmitting = false;
    const discardLink = document.getElementById('discardProfileChanges');

    perfilEditForm.querySelectorAll('input, textarea, select').forEach((field) => {
        field.addEventListener('input', () => {
            profileDirty = true;
        });
        field.addEventListener('change', () => {
            profileDirty = true;
        });
    });

    perfilEditForm.addEventListener('submit', () => {
        profileSubmitting = true;
    });

    discardLink?.addEventListener('click', (event) => {
        if (profileDirty && !confirm('Tenés cambios sin guardar. ¿Querés descartarlos?')) {
            event.preventDefault();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (profileDirty && !profileSubmitting) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}
</script>
</body>
</html>

