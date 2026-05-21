<?php
session_start();
require_once __DIR__ . '/../config/app.php';

$timeout = isset($_GET['timeout']) && $_GET['timeout'] === '1';

session_destroy();
header('Location: ' . BASE_URL . ($timeout ? 'login.php?timeout=1' : 'index.php'));
exit;
