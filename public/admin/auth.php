<?php
session_start();

// Redirigir al login si no hay sesión iniciada o si el flag is_admin es falso o no existe
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}
