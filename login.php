<?php
/**
 * login.php — Inicio de sesión único para administradores y clientes.
 * Según el rol guardado en la tabla usuarios, envía al backend (admin/) o al frontend (cliente/).
 */
require 'includes/conexion.php';

if (estaLogueado()) {
    ir(esAdmin() ? 'admin/index.php' : 'cliente/index.php');
}

$error = '';
$usuario = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario  = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($usuario === '' || $password === '') {
        $error = 'Ingresa tu correo y tu contraseña.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, nombre, usuario, password, rol FROM usuarios WHERE usuario = :usuario LIMIT 1');
            $stmt->execute([':usuario' => $usuario]);
            $fila = $stmt->fetch();

            if ($fila && password_verify($password, $fila['password'])) {
                iniciarSesion($fila);
                flash('success', '¡Hola, ' . $fila['nombre'] . '!');
                ir($fila['rol'] === 'admin' ? 'admin/index.php' : 'cliente/index.php');
            }
            $error = 'Correo o contraseña incorrectos.';
        } catch (PDOException $ex) {
            $error = 'No se pudo validar el acceso. Intenta más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar | Motos Monfort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/estilos.css" rel="stylesheet">
</head>
<body class="auth">
<div class="container-fluid">
    <div class="row min-vh-100">
        <div class="col-lg-6 lado d-flex flex-column justify-content-center p-5">
            <a href="catalogo.php" class="text-white text-decoration-none fs-4 mb-5 display-font"><i class="bi bi-speedometer2 text-warning me-2"></i>Motos Monfort</a>
            <div class="raya mb-4"></div>
            <h1>Compra, vende y administra motos en un solo lugar</h1>
            <ul class="list-unstyled mt-4 fs-5">
                <li><i class="bi bi-check-circle-fill"></i>Clientes: publica tu moto y sigue su aprobación.</li>
                <li><i class="bi bi-check-circle-fill"></i>Administradores: controla inventario, marcas y usuarios.</li>
            </ul>
            <a href="catalogo.php" class="btn btn-outline-light mt-3 align-self-start"><i class="bi bi-grid-3x3-gap me-2"></i>Ver catálogo</a>
        </div>
        <div class="col-lg-6 form-wrap d-flex align-items-center justify-content-center p-4">
            <form method="post" class="w-100 needs-validation" style="max-width:400px" novalidate><?= campoCsrf() ?>
                <h2 class="mb-1">Ingresar</h2>
                <p class="text-muted mb-4">Usa tu cuenta de cliente o de administrador.</p>

                <?php include 'includes/alertas.php'; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="usuario" class="form-label">Correo</label>
                    <input type="email" class="form-control form-control-lg" id="usuario" name="usuario"
                           value="<?= e($usuario) ?>" placeholder="tucorreo@ejemplo.com" required autofocus>
                    <div class="invalid-feedback">Escribe tu correo.</div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                    <div class="invalid-feedback">Escribe tu contraseña.</div>
                </div>
                <button class="btn btn-amber btn-lg w-100" type="submit">Ingresar</button>
                <p class="text-center mt-3">¿No tienes cuenta? <a href="registro.php">Regístrate como cliente</a></p>

                <div class="cuentas-demo p-3 mt-4">
                    <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Cuentas de prueba (contraseña: <code>password</code>)</div>
                    <div>Admin: <code>admin@motos.pe</code></div>
                    <div>Cliente: <code>cliente@motos.pe</code></div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
