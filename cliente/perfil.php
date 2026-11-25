<?php
/**
 * cliente/perfil.php — FRONTEND. El cliente edita sus datos y cambia su contraseña.
 * Todas las consultas usan el id de la sesión: nadie puede editar el perfil de otra persona.
 */
require '../includes/conexion.php';
verificarCliente();

$miId = (int)$_SESSION['usuario_id'];
$erroresDatos = [];
$erroresClave = [];

try {
    $stmt = $pdo->prepare('SELECT nombre, usuario, telefono, creado_en FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $miId]);
    $yo = $stmt->fetch();
} catch (PDOException $ex) {
    errorBD('Error al cargar tu perfil.', $ex);
}

$d = ['nombre' => $yo['nombre'], 'usuario' => $yo['usuario'], 'telefono' => (string)$yo['telefono']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        foreach ($d as $campo => $_) {
            $d[$campo] = trim($_POST[$campo] ?? '');
        }
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 100)  $erroresDatos[] = 'Escribe tu nombre (máximo 100 caracteres).';
        if (!filter_var($d['usuario'], FILTER_VALIDATE_EMAIL))      $erroresDatos[] = 'Escribe un correo válido.';
        if ($d['telefono'] !== '' && !preg_match('/^[0-9 +]{6,20}$/', $d['telefono'])) $erroresDatos[] = 'El celular solo puede tener números (6 a 20).';

        if (!$erroresDatos) {
            try {
                $pdo->prepare('UPDATE usuarios SET nombre = :nombre, usuario = :usuario, telefono = :telefono WHERE id = :id')
                    ->execute([':nombre' => $d['nombre'], ':usuario' => $d['usuario'], ':telefono' => $d['telefono'] ?: null, ':id' => $miId]);
                $_SESSION['usuario'] = $d['usuario'];
                flash('success', 'Tus datos se guardaron correctamente.');
                ir('cliente/perfil.php');
            } catch (PDOException $ex) {
                $erroresDatos[] = $ex->getCode() === '23000' ? 'Ese correo ya lo usa otra cuenta.' : 'No se pudieron guardar tus datos.';
            }
        }
    } elseif ($accion === 'clave') {
        $actual = $_POST['actual'] ?? '';
        $nueva  = $_POST['nueva'] ?? '';
        $nueva2 = $_POST['nueva2'] ?? '';

        $stmt = $pdo->prepare('SELECT password FROM usuarios WHERE id = :id');
        $stmt->execute([':id' => $miId]);
        if (!password_verify($actual, (string)$stmt->fetchColumn())) $erroresClave[] = 'La contraseña actual no es correcta.';
        if (mb_strlen($nueva) < 6)                                    $erroresClave[] = 'La nueva contraseña debe tener al menos 6 caracteres.';
        if ($nueva !== $nueva2)                                       $erroresClave[] = 'Las contraseñas nuevas no coinciden.';

        if (!$erroresClave) {
            $pdo->prepare('UPDATE usuarios SET password = :p WHERE id = :id')
                ->execute([':p' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $miId]);
            flash('success', 'Contraseña actualizada.');
            ir('cliente/perfil.php');
        }
    }
}

$titulo = 'Mi perfil';
include '../includes/header_front.php';
?>
<div class="d-flex align-items-center gap-3 mb-4">
    <span class="avatar avatar-lg"><?= e(mb_strtoupper(mb_substr($yo['nombre'], 0, 1))) ?></span>
    <div>
        <h1 class="mb-0">Mi perfil</h1>
        <div class="text-muted">Cliente desde el <?= date('d/m/Y', strtotime($yo['creado_en'])) ?></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" class="panel p-4 needs-validation" novalidate><?= campoCsrf() ?>
            <input type="hidden" name="accion" value="datos">
            <h2 class="h4 mb-3"><i class="bi bi-person-lines-fill me-2"></i>Mis datos</h2>
            <?php foreach ($erroresDatos as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
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
                    <label class="form-label" for="telefono">Celular</label>
                    <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="20" pattern="[0-9 +]{6,20}" value="<?= e($d['telefono']) ?>">
                    <div class="invalid-feedback">Solo números (6 a 20).</div>
                    <div class="form-text">Se muestra en el catálogo para que te contacten.</div>
                </div>
                <div class="col-12">
                    <button class="btn btn-amber" type="submit"><i class="bi bi-save me-1"></i>Guardar datos</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-5">
        <form method="post" class="panel p-4 needs-validation" novalidate><?= campoCsrf() ?>
            <input type="hidden" name="accion" value="clave">
            <h2 class="h4 mb-3"><i class="bi bi-shield-lock me-2"></i>Cambiar contraseña</h2>
            <?php foreach ($erroresClave as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
            <div class="mb-3">
                <label class="form-label" for="actual">Contraseña actual</label>
                <input type="password" class="form-control" id="actual" name="actual" required autocomplete="current-password">
                <div class="invalid-feedback">Escribe tu contraseña actual.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="nueva">Nueva contraseña</label>
                <input type="password" class="form-control" id="nueva" name="nueva" minlength="6" required autocomplete="new-password">
                <div class="invalid-feedback">Mínimo 6 caracteres.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="nueva2">Repite la nueva contraseña</label>
                <input type="password" class="form-control" id="nueva2" name="nueva2" minlength="6" required data-igual-a="nueva" autocomplete="new-password">
                <div class="invalid-feedback">Las contraseñas deben coincidir.</div>
            </div>
            <button class="btn btn-petrol" type="submit"><i class="bi bi-key me-1"></i>Cambiar contraseña</button>
        </form>
    </div>
</div>
<?php include '../includes/footer_front.php'; ?>
