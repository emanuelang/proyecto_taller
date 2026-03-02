<?php
session_start();
require_once __DIR__ . '/../core/storage.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];

        $stmt2 = $pdo->prepare("SELECT id FROM conductores WHERE usuario_id = ?");
        $stmt2->execute([$user['id']]);
        $cond = $stmt2->fetch();

        if ($cond) {
            $_SESSION['is_conductor'] = true;
            $_SESSION['conductor_id'] = $cond['id'];
        } else {
            $_SESSION['is_conductor'] = false;
            unset($_SESSION['conductor_id']);
        }

        header('Location: ' . BASE_URL . 'index.php');
        exit;
    } else {
        $error = 'Credenciales incorrectas';
    }
}
?>
<h2>Login</h2>
<?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <input name="email" type="email" required placeholder="Email"><br>
    <input name="password" type="password" required placeholder="Contraseña"><br>
    <button>Ingresar</button>
</form>
