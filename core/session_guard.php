<?php

function logout_inactive_session(string $redirect_url): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ' . $redirect_url);
    exit;
}

function enforce_active_user_session(PDO $pdo): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $stmt = $pdo->prepare("SELECT estado, BaneadoHasta FROM Usuarios WHERE ID_usuario = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || ($user['estado'] ?? '') !== 'activo') {
        logout_inactive_session(BASE_URL . 'login.php?inactive=1');
    }

    if (!empty($user['BaneadoHasta']) && strtotime($user['BaneadoHasta']) > time()) {
        logout_inactive_session(BASE_URL . 'login.php?banned=1');
    }
}

function sync_conductor_session(PDO $pdo): void
{
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $stmt = $pdo->prepare("SELECT ID_conductor, BaneadoHasta FROM Conductores WHERE ID_usuario = ? AND Estado = 'Aceptada'");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $conductor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conductor || (!empty($conductor['BaneadoHasta']) && strtotime($conductor['BaneadoHasta']) > time())) {
        $_SESSION['is_conductor'] = false;
        unset($_SESSION['conductor_id']);
        return;
    }

    $_SESSION['is_conductor'] = true;
    $_SESSION['conductor_id'] = (int)$conductor['ID_conductor'];
}

function require_active_session(PDO $pdo): void
{
    enforce_active_user_session($pdo);
    sync_conductor_session($pdo);
}
