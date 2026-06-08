<?php
// Detectar si estamos en HTTPS (incluso si estamos detrás de proxies como ngrok)
$isSecure = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $isSecure = true;
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
    $isSecure = true;
}

$protocol = $isSecure ? 'https://' : 'http://';
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

// Determinamos la ubicación base del proyecto de manera dinámica.
// Esto permite que el proyecto corra desde cualquier carpeta y a la vez
// apuntando sus rutas base (assets, etc) correctamente a /public/
$docRoot = str_replace('\\', '/', isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '');
$dirPath = str_replace('\\', '/', dirname(__DIR__) . '/public');
$baseDir = str_replace($docRoot, '', $dirPath);
$baseDir = rtrim($baseDir, '/') . '/';

// Si corremos scripts desde CLI sin web server, podemos tener un fallback seguro
if (empty($docRoot) || empty($_SERVER['HTTP_HOST'])) {
    $baseDir = '/' . basename(dirname(__DIR__)) . '/public/';
}

define('BASE_URL', $protocol . $host . $baseDir);

if (!defined('SESSION_TIMEOUT_SECONDS')) {
    define('SESSION_TIMEOUT_SECONDS', 30 * 60);
}

// Pagos online pausados para esta version del proyecto.
// El codigo de Mercado Pago y billetera queda disponible para una version futura.
if (!defined('PAYMENTS_ENABLED')) {
    define('PAYMENTS_ENABLED', false);
}

if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $isLogout = substr($scriptName, -11) === '/logout.php';
    $now = time();

    if (!$isLogout && isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > SESSION_TIMEOUT_SECONDS) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', $now - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();

        $loginUrl = strpos($scriptName, '/admin/') !== false
            ? BASE_URL . 'admin/login.php?timeout=1'
            : BASE_URL . 'login.php?timeout=1';

        header('Location: ' . $loginUrl);
        exit;
    }

    $_SESSION['last_activity'] = $now;
}
