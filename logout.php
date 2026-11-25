<?php
// logout.php — Cierra la sesión de cualquier usuario (admin o cliente) y vuelve al catálogo.
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: catalogo.php');
exit;
