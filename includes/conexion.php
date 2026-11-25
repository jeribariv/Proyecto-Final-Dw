<?php
/**
 * includes/conexion.php
 * Conexión PDO a db_motos, sesión, control de roles (admin / cliente) y funciones reutilizables.
 * Lo incluyen TODAS las páginas: las del frontend (raíz y cliente/) y las del backend (admin/).
 */
require __DIR__ . '/config.php';

// Cookie de sesión más segura: inaccesible desde JavaScript y con ID estricto
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Carpeta física donde se guardan las fotos
define('UPLOADS_DIR', dirname(__DIR__) . '/uploads/');

// Prefijo para los enlaces: '' si la página está en la raíz, '../' si está en admin/ o cliente/
define('RAIZ', realpath(dirname($_SERVER['SCRIPT_FILENAME'])) === realpath(dirname(__DIR__)) ? '' : '../');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    errorBD('No se pudo conectar a la base de datos. Revisa que MySQL esté encendido en WampServer y que hayas importado db/db_motos.sql.', $e);
}

/*
 * Si hay sesión, se vuelve a leer el usuario de la BD en cada página:
 * - si ya no existe (o la sesión es de una versión anterior sin rol), se cierra la sesión;
 * - si un admin le cambió el rol, el cambio se aplica al instante.
 */
if (isset($_SESSION['usuario_id'])) {
    $stmt = $pdo->prepare('SELECT nombre, rol FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => (int)$_SESSION['usuario_id']]);
    $yo = $stmt->fetch();
    if ($yo) {
        $_SESSION['nombre'] = $yo['nombre'];
        $_SESSION['rol']    = $yo['rol'];
    } else {
        $_SESSION = [];   // sesión inválida: se limpia y el usuario vuelve a ingresar
    }
}

/*
 * Protección CSRF: cada formulario POST lleva un token secreto de la sesión (campoCsrf()).
 * Si el token no coincide, la petición se rechaza aquí, antes de llegar a cualquier página.
 */
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('danger', 'El archivo enviado es demasiado grande. La foto debe pesar como máximo 2 MB.');
    } else {
        flash('danger', 'El formulario expiró o no es válido. Vuelve a intentarlo.');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

/** Categorías de moto (columna motos.tipo) y su nombre visible. */
const TIPOS_MOTO = [
    'naked'     => 'Naked',
    'deportiva' => 'Deportiva',
    'scooter'   => 'Scooter',
    'trail'     => 'Trail',
];

/** Estados de una moto y el color de su etiqueta (Bootstrap). */
const ESTADOS_MOTO = [
    'aprobada'  => ['Aprobada',  'success'],
    'pendiente' => ['Pendiente', 'warning'],
    'rechazada' => ['Rechazada', 'danger'],
];

/* ------------------------------------------------------------------
   Sesión y roles
   ------------------------------------------------------------------ */

function estaLogueado(): bool
{
    return isset($_SESSION['usuario_id'], $_SESSION['rol']);
}

function esAdmin(): bool
{
    return ($_SESSION['rol'] ?? '') === 'admin';
}

function esCliente(): bool
{
    return ($_SESSION['rol'] ?? '') === 'cliente';
}

/** Redirige a una ruta relativa a la raíz del proyecto. */
function ir(string $ruta): void
{
    header('Location: ' . RAIZ . $ruta);
    exit;
}

/** Protege las páginas del backend: solo administradores. */
function verificarAdmin(): void
{
    if (!estaLogueado()) {
        flash('warning', 'Inicia sesión para continuar.');
        ir('login.php');
    }
    if (!esAdmin()) {
        flash('danger', 'No tienes permiso para entrar al panel de administración.');
        ir('catalogo.php');
    }
}

/** Protege la zona de clientes: solo clientes. */
function verificarCliente(): void
{
    if (!estaLogueado()) {
        flash('warning', 'Inicia sesión o crea tu cuenta para publicar tu moto.');
        ir('login.php');
    }
    if (!esCliente()) {
        ir('admin/index.php'); // un admin no publica como cliente: va a su panel
    }
}

/** Guarda en la sesión los datos del usuario que acaba de ingresar. */
function iniciarSesion(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$u['id'];
    $_SESSION['usuario']    = $u['usuario'];
    $_SESSION['nombre']     = $u['nombre'];
    $_SESSION['rol']        = $u['rol'];
}

/* ------------------------------------------------------------------
   Utilidades
   ------------------------------------------------------------------ */

/** Nombre visible de una categoría. */
function nombreTipo(?string $tipo): string
{
    return TIPOS_MOTO[$tipo] ?? 'Naked';
}

/** Etiqueta HTML (badge) con el estado de una moto. */
function badgeEstado(string $estado): string
{
    [$texto, $color] = ESTADOS_MOTO[$estado] ?? ['—', 'secondary'];
    return '<span class="badge text-bg-' . $color . '">' . $texto . '</span>';
}

/** Ruta web de la foto de una moto, o null si no tiene foto o el archivo no está en uploads/. */
function fotoMoto(?string $foto): ?string
{
    if ($foto && is_file(UPLOADS_DIR . basename($foto))) {
        return RAIZ . 'uploads/' . rawurlencode($foto);
    }
    return null;
}

/** Campo oculto con el token CSRF. Va dentro de TODOS los formularios POST. */
function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . $_SESSION['csrf'] . '">';
}

/**
 * Muestra una página de error clara sin exponer datos internos (consultas, rutas, contraseñas).
 * El detalle técnico se guarda en el log de PHP; solo se muestra si MODO_DEBUG = true.
 */
function errorBD(string $mensaje, Throwable $ex): void
{
    error_log('[Motos Monfort] ' . $mensaje . ' | ' . $ex->getMessage());
    http_response_code(500);
    $detalle = MODO_DEBUG ? '<pre class="small text-start bg-light p-3 mt-3">' . e($ex->getMessage()) . '</pre>' : '';
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Error | Motos Monfort</title>'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>'
       . '<body class="bg-light d-flex align-items-center min-vh-100"><div class="container" style="max-width:560px">'
       . '<div class="card shadow-sm text-center p-4"><h1 class="h3">Algo salió mal</h1>'
       . '<p class="text-muted mb-3">' . e($mensaje) . '</p>' . $detalle
       . '<a class="btn btn-dark" href="javascript:history.back()">Volver</a></div></div></body></html>';
    exit;
}

/** Enlace de WhatsApp (wa.me) a un celular peruano con un mensaje ya escrito. */
function enlaceWhatsapp(string $telefono, string $mensaje): string
{
    $numero = preg_replace('/\D/', '', $telefono);   // solo dígitos
    if (strlen($numero) === 9) {
        $numero = '51' . $numero;                       // código de Perú
    }
    return 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje);
}

/** Escapa texto para imprimirlo en HTML. */
function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

/** Guarda un mensaje para mostrarlo en la siguiente página. */
function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

/**
 * Sube la foto de una moto a uploads/ con nombre único (uniqid).
 * Devuelve [nombreArchivo|null, error|null].
 */
function subirFoto(array $archivo): array
{
    if ($archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return [null, 'No se pudo subir la imagen. Inténtalo de nuevo.'];
    }
    if ($archivo['size'] > 2 * 1024 * 1024) {
        return [null, 'La imagen supera el máximo de 2 MB.'];
    }
    $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!isset($permitidos[$mime])) {
        return [null, 'Formato no válido. Usa JPG, PNG o WEBP.'];
    }
    if (!is_dir(UPLOADS_DIR)) {
        mkdir(UPLOADS_DIR, 0777, true);
    }
    $nombre = uniqid('moto_') . '.' . $permitidos[$mime];
    if (!move_uploaded_file($archivo['tmp_name'], UPLOADS_DIR . $nombre)) {
        return [null, 'No se pudo guardar la imagen en la carpeta uploads/.'];
    }
    return [$nombre, null];
}

/** Elimina físicamente una foto de uploads/ con unlink(). */
function borrarFoto(?string $nombre): void
{
    if ($nombre) {
        $ruta = UPLOADS_DIR . basename($nombre);
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }
}

/**
 * Valida los datos del formulario de una moto (se usa en admin y en cliente).
 * Devuelve la lista de errores.
 */
function validarMoto(array $d): array
{
    $errores = [];
    if ($d['vin_chasis'] === '' || mb_strlen($d['vin_chasis']) > 30)        $errores[] = 'El chasis (VIN) es obligatorio y admite hasta 30 caracteres.';
    if ($d['modelo'] === '' || mb_strlen($d['modelo']) > 100)                $errores[] = 'El modelo es obligatorio y admite hasta 100 caracteres.';
    if (!ctype_digit($d['cilindrada_cc']) || (int)$d['cilindrada_cc'] <= 0)  $errores[] = 'La cilindrada debe ser un número entero mayor que 0.';
    if (!is_numeric($d['precio']) || (float)$d['precio'] <= 0)               $errores[] = 'El precio debe ser un número mayor que 0.';
    if (!ctype_digit($d['id_marca']) || (int)$d['id_marca'] <= 0)            $errores[] = 'Selecciona una marca.';
    if (!isset(TIPOS_MOTO[$d['tipo']]))                                      $errores[] = 'Selecciona una categoría válida.';
    if (mb_strlen($d['descripcion'] ?? '') > 500)                            $errores[] = 'La descripción admite hasta 500 caracteres.';
    return $errores;
}
