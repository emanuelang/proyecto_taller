<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';
require_once __DIR__ . '/../core/account_lifecycle.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "perfil.php");
    exit;
}

require_csrf();
$user_id = (int)$_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    deactivate_user_account($pdo, $user_id, 'El usuario desactivo su cuenta.');
    $pdo->commit();

    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "login.php?inactive=1");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al desactivar el perfil: " . $e->getMessage());
    safe_error('No se pudo desactivar el perfil.');
}
