<?php
/**
 * includes/config.php — Configuración del sistema (credenciales de la base de datos).
 * Es el único archivo que debes cambiar si tu MySQL usa otro usuario o contraseña.
 * La carpeta includes/ está bloqueada para el navegador (ver includes/.htaccess).
 */
const DB_HOST = 'localhost';
const DB_NAME = 'db_motos';
const DB_USER = 'root';   // Usuario por defecto de WampServer
const DB_PASS = '';       // Contraseña vacía por defecto en WampServer

// true = muestra el detalle técnico de los errores (solo mientras desarrollas).
// false = el usuario ve un mensaje claro y el detalle se guarda en el log de PHP.
const MODO_DEBUG = false;
