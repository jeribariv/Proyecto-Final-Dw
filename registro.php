<?php
/**
 * registro.php — Registro de clientes (frontend).
 * Toda cuenta creada aquí tiene rol 'cliente'. Los administradores se crean desde admin/usuarios.php.
 */
require 'includes/conexion.php';

if (estaLogueado()) {
    ir(esAdmin() ? 'admin/index.php' : 'cliente/index.php');
}

$errores = [];
$d = ['nombre' => '', 'usuario' => '', 'telefono' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($d as $campo => $_) {
        $d[$campo] = trim($_POST[$campo] ?? '');
    }
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 100)          $errores[] = 'Escribe tu nombre (máximo 100 caracteres).';
    if (!filter_var($d['usuario'], FILTER_VALIDATE_EMAIL))              $errores[] = 'Escribe un correo válido.';
    if ($d['telefono'] !== '' && !preg_match('/^[0-9 +]{6,20}$/', $d['telefono'])) $errores[] = 'El teléfono solo puede tener números (6 a 20).';
    if (mb_strlen($password) < 6)                                       $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    if ($password !== $password2)                                       $errores[] = 'Las contraseñas no coinciden.';

    if (!$errores) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nombre, usuario, password, telefono, rol)
                 VALUES (:nombre, :usuario, :password, :telefono, 'cliente')"
            );
            $stmt->execute([
                ':nombre'   => $d['nombre'],
                ':usuario'  => $d['usuario'],
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':telefono' => $d['telefono'] ?: null,
            ]);
            iniciarSesion([
                'id' => $pdo->lastInsertId(), 'nombre' => $d['nombre'], 'usuario' => $d['usuario'], 'rol' => 'cliente',
            ]);
            flash('success', '¡Cuenta creada! Ya puedes publicar tu moto.');
            ir('cliente/publicar.php');
        } catch (PDOException $ex) {
            $errores[] = $ex->getCode() === '23000' ? 'Ese correo ya está registrado. Inicia sesión.' : 'No se pudo crear la cuenta.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | Motos Monfort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="assets/css/estilos.css" rel="stylesheet">
</head>
<body class="auth">
<div class="container-fluid">
    <div class="row min-vh-100">
        <div class="col-lg-5 lado d-flex flex-column justify-content-center p-5">
            <a href="catalogo.php" class="text-white text-decoration-none fs-4 mb-5 display-font"><i class="bi bi-speedometer2 text-warning me-2"></i>Motos Monfort</a>
            <div class="raya mb-4"></div>
            <h1>Vende tu moto con nosotros</h1>
            <ul class="list-unstyled mt-4 fs-5">
                <li><i class="bi bi-1-circle-fill"></i>Crea tu cuenta gratis.</li>
                <li><i class="bi bi-2-circle-fill"></i>Publica tu moto con foto y precio.</li>
                <li><i class="bi bi-3-circle-fill"></i>Un administrador la revisa y la muestra en el catálogo.</li>
            </ul>
        </div>
        <div class="col-lg-7 form-wrap d-flex align-items-center justify-content-center p-4">
            <form method="post" class="w-100 needs-validation" style="max-width:520px" novalidate><?= campoCsrf() ?>
                <h2 class="mb-1">Crear cuenta de cliente</h2>
                <p class="text-muted mb-4">¿Ya tienes una? <a href="login.php">Ingresa aquí</a>.</p>

                <?php foreach ($errores as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>

                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="nombre">Nombre completo</label>
                        <input class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e($d['nombre']) ?>" required>
                        <div class="invalid-feedback">Escribe tu nombre.</div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="usuario">Correo</label>
                        <input type="email" class="form-control" id="usuario" name="usuario" value="<?= e($d['usuario']) ?>" required>
                        <div class="invalid-feedback">Escribe un correo válido.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="telefono">Celular <span class="text-muted">(opcional)</span></label>
                        <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="20" value="<?= e($d['telefono']) ?>" placeholder="9XXXXXXXX" pattern="[0-9 +]{6,20}">
                        <div class="invalid-feedback">Solo números (6 a 20).</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6" required autocomplete="new-password">
                        <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password2">Repite la contraseña</label>
                        <input type="password" class="form-control" id="password2" name="password2" minlength="6" required data-igual-a="password" autocomplete="new-password">
                        <div class="invalid-feedback">Las contraseñas deben coincidir.</div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-amber btn-lg w-100" type="submit">Crear mi cuenta</button>
                    </div>
                </div>
                <p class="text-center mt-3 mb-0"><a href="catalogo.php" class="small">← Volver al catálogo</a></p>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
