<?php
/**
 * admin/usuarios.php — BACKEND. Gestión de usuarios (CRUD completo):
 * crear administradores o clientes, editar datos y contraseña, ver el detalle con sus motos,
 * cambiar el rol, eliminar, buscar y paginar. Un admin no puede quitarse su propio rol ni borrarse.
 */
require '../includes/conexion.php';
verificarAdmin();

$miId = (int)$_SESSION['usuario_id'];
$errores = [];
$porPagina = 8;

// Usuario que se está editando (?editar=ID)
$editarId = (int)($_GET['editar'] ?? $_POST['id'] ?? 0);
$editando = null;
if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT id, nombre, usuario, telefono, rol, creado_en FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $editarId]);
    $editando = $stmt->fetch() ?: null;
    if (!$editando) {
        flash('warning', 'El usuario no existe.');
        ir('admin/usuarios.php');
    }
}

$d = $editando
    ? ['nombre' => $editando['nombre'], 'usuario' => $editando['usuario'], 'telefono' => (string)$editando['telefono'], 'rol' => $editando['rol']]
    : ['nombre' => '', 'usuario' => '', 'telefono' => '', 'rol' => 'admin'];

/** Valida los datos del formulario de usuario. La contraseña es obligatoria solo al crear. */
function validarUsuario(array $d, string $password, bool $esNuevo): array
{
    $errores = [];
    if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 100)  $errores[] = 'Escribe el nombre (máximo 100 caracteres).';
    if (!filter_var($d['usuario'], FILTER_VALIDATE_EMAIL))      $errores[] = 'Escribe un correo válido.';
    if ($d['telefono'] !== '' && !preg_match('/^[0-9 +]{6,20}$/', $d['telefono'])) $errores[] = 'El celular solo puede tener números (6 a 20).';
    if (!in_array($d['rol'], ['admin', 'cliente'], true))       $errores[] = 'Rol no válido.';
    if (($esNuevo || $password !== '') && mb_strlen($password) < 6) $errores[] = 'La contraseña debe tener al menos 6 caracteres.';
    return $errores;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    try {
        if ($accion === 'crear' || $accion === 'actualizar') {
            foreach ($d as $campo => $_) {
                $d[$campo] = trim($_POST[$campo] ?? '');
            }
            if ($accion === 'actualizar' && $id === $miId) {
                $d['rol'] = 'admin';   // no se puede quitar su propio rol de admin
            }
            $password = $_POST['password'] ?? '';
            $errores = validarUsuario($d, $password, $accion === 'crear');

            if (!$errores) {
                try {
                    if ($accion === 'crear') {
                        $pdo->prepare(
                            'INSERT INTO usuarios (nombre, usuario, password, telefono, rol)
                             VALUES (:nombre, :usuario, :password, :telefono, :rol)'
                        )->execute([
                            ':nombre' => $d['nombre'], ':usuario' => $d['usuario'],
                            ':password' => password_hash($password, PASSWORD_DEFAULT),
                            ':telefono' => $d['telefono'] ?: null, ':rol' => $d['rol'],
                        ]);
                        flash('success', ($d['rol'] === 'admin' ? 'Administrador' : 'Cliente') . ' creado: ' . $d['usuario']);
                    } else {
                        $pdo->prepare(
                            'UPDATE usuarios SET nombre = :nombre, usuario = :usuario, telefono = :telefono, rol = :rol WHERE id = :id'
                        )->execute([
                            ':nombre' => $d['nombre'], ':usuario' => $d['usuario'],
                            ':telefono' => $d['telefono'] ?: null, ':rol' => $d['rol'], ':id' => $id,
                        ]);
                        if ($password !== '') {   // solo se cambia la contraseña si se escribió una nueva
                            $pdo->prepare('UPDATE usuarios SET password = :p WHERE id = :id')
                                ->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $id]);
                        }
                        flash('success', 'Usuario actualizado: ' . $d['usuario']);
                    }
                    ir('admin/usuarios.php');
                } catch (PDOException $ex) {
                    $errores[] = $ex->getCode() === '23000' ? 'Ese correo ya está registrado.' : 'No se pudo guardar el usuario.';
                }
            }
        } elseif ($id === $miId) {
            flash('warning', 'No puedes cambiar tu propio rol ni eliminar tu propia cuenta.');
            ir('admin/usuarios.php');
        } elseif ($accion === 'cambiar_rol') {
            $pdo->prepare("UPDATE usuarios SET rol = IF(rol = 'admin', 'cliente', 'admin') WHERE id = :id")->execute([':id' => $id]);
            flash('success', 'Rol actualizado.');
            ir('admin/usuarios.php');
        } elseif ($accion === 'eliminar') {
            // Sus motos se borran por ON DELETE CASCADE; antes se eliminan sus fotos del disco.
            $fotos = $pdo->prepare('SELECT foto FROM motos WHERE id_usuario = :id AND foto IS NOT NULL');
            $fotos->execute([':id' => $id]);
            foreach ($fotos->fetchAll() as $f) {
                borrarFoto($f['foto']);
            }
            $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute([':id' => $id]);
            flash('success', 'Usuario eliminado junto con sus publicaciones.');
            ir('admin/usuarios.php');
        }
    } catch (PDOException $ex) {
        errorBD('No se pudo completar la acción sobre el usuario.', $ex);
    }
}

// Búsqueda, filtro por rol y paginación
$q      = trim($_GET['q'] ?? '');
$rol    = $_GET['rol'] ?? '';
if (!in_array($rol, ['admin', 'cliente'], true)) $rol = '';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

try {
    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(u.nombre LIKE :q1 OR u.usuario LIKE :q2 OR u.telefono LIKE :q3)';
        $params[':q1'] = $params[':q2'] = $params[':q3'] = "%$q%";
    }
    if ($rol !== '') {
        $where[] = 'u.rol = :rol';
        $params[':rol'] = $rol;
    }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);

    $stmt = $pdo->prepare(
        "SELECT u.id, u.nombre, u.usuario, u.telefono, u.rol, u.creado_en, COUNT(m.id) AS motos
         FROM usuarios u LEFT JOIN motos m ON m.id_usuario = u.id
         $sqlWhere
         GROUP BY u.id, u.nombre, u.usuario, u.telefono, u.rol, u.creado_en
         ORDER BY u.rol, u.nombre
         LIMIT :limite OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $lista = $stmt->fetchAll();

    $totales = $pdo->query("SELECT SUM(rol = 'admin') AS admins, SUM(rol = 'cliente') AS clientes FROM usuarios")->fetch();

    // Detalle: motos del usuario que se está editando
    $motosUsuario = [];
    if ($editando) {
        $s = $pdo->prepare(
            'SELECT m.id, m.modelo, m.precio, m.estado, m.foto, ma.nombre_marca AS marca
             FROM motos m INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
             WHERE m.id_usuario = :id ORDER BY m.id DESC'
        );
        $s->execute([':id' => $editando['id']]);
        $motosUsuario = $s->fetchAll();
    }
} catch (PDOException $ex) {
    errorBD('Error al cargar los usuarios.', $ex);
}

/** URL de la lista conservando búsqueda, rol y página. */
function urlUsuarios(array $cambios = []): string
{
    global $q, $rol, $pagina;
    $datos = array_merge(['q' => $q, 'rol' => $rol, 'pagina' => $pagina], $cambios);
    $datos = array_filter($datos, function ($v) { return $v !== '' && $v !== null; });
    if (($datos['pagina'] ?? 1) === 1) unset($datos['pagina']);
    return 'usuarios.php' . ($datos ? '?' . http_build_query($datos) : '');
}

$esYoEditando = $editando && (int)$editando['id'] === $miId;

$titulo = 'Usuarios';
include '../includes/header_admin.php';
?>
<h1 class="mb-1">Usuarios</h1>
<p class="text-muted">Añade administradores, edita cuentas y revisa las publicaciones de cada cliente.</p>

<div class="row g-4">
    <!-- Formulario: crear / editar -->
    <div class="col-xl-4">
        <form method="post" class="panel p-4 needs-validation" novalidate><?= campoCsrf() ?>
            <h2 class="h4 mb-3">
                <?php if ($editando): ?>
                    <i class="bi bi-pencil-square me-2"></i>Editar usuario
                <?php else: ?>
                    <i class="bi bi-person-plus me-2"></i>Nuevo usuario
                <?php endif; ?>
            </h2>
            <?php foreach ($errores as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
            <input type="hidden" name="accion" value="<?= $editando ? 'actualizar' : 'crear' ?>">
            <?php if ($editando): ?><input type="hidden" name="id" value="<?= $editando['id'] ?>"><?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="nombre">Nombre completo</label>
                <input class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e($d['nombre']) ?>" required>
                <div class="invalid-feedback">Escribe el nombre.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="usuario">Correo</label>
                <input type="email" class="form-control" id="usuario" name="usuario" value="<?= e($d['usuario']) ?>" required>
                <div class="invalid-feedback">Escribe un correo válido.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="telefono">Celular <span class="text-muted">(opcional)</span></label>
                <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="20" pattern="[0-9 +]{6,20}" value="<?= e($d['telefono']) ?>">
                <div class="invalid-feedback">Solo números (6 a 20).</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?= $editando ? 'Nueva contraseña' : 'Contraseña' ?></label>
                <input type="password" class="form-control" id="password" name="password" minlength="6" <?= $editando ? '' : 'required' ?> autocomplete="new-password">
                <div class="invalid-feedback">Mínimo 6 caracteres.</div>
                <div class="form-text"><?= $editando ? 'Déjala vacía para no cambiarla.' : 'Mínimo 6 caracteres. Se guarda cifrada (password_hash).' ?></div>
            </div>
            <div class="mb-4">
                <span class="form-label d-block">Rol</span>
                <?php if ($esYoEditando): ?>
                    <input type="hidden" name="rol" value="admin">
                    <div class="form-text mt-0"><i class="bi bi-lock me-1"></i>Eres administrador. No puedes quitarte tu propio rol.</div>
                <?php else: ?>
                    <div class="btn-group w-100" role="group" aria-label="Rol">
                        <input type="radio" class="btn-check" name="rol" id="rolAdmin" value="admin" <?= $d['rol'] === 'admin' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-dark" for="rolAdmin"><i class="bi bi-shield-lock me-1"></i>Administrador</label>
                        <input type="radio" class="btn-check" name="rol" id="rolCliente" value="cliente" <?= $d['rol'] === 'cliente' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-dark" for="rolCliente"><i class="bi bi-person me-1"></i>Cliente</label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-amber flex-fill" type="submit" data-cargando="Guardando…">
                    <i class="bi bi-save me-1"></i><?= $editando ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>
                <?php if ($editando): ?><a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a><?php endif; ?>
            </div>
        </form>

        <?php if ($editando): ?>
            <!-- Detalle del usuario: sus motos -->
            <div class="panel mt-4">
                <div class="panel-titulo">
                    <h2><i class="bi bi-bicycle me-2"></i>Motos publicadas</h2>
                    <span class="badge text-bg-secondary"><?= count($motosUsuario) ?></span>
                </div>
                <?php if (!$motosUsuario): ?>
                    <div class="text-muted text-center py-4">Este usuario no tiene motos publicadas.</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($motosUsuario as $m): ?>
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <?php if (fotoMoto($m['foto'])): ?>
                                    <img class="thumb" src="<?= e(fotoMoto($m['foto'])) ?>" alt="">
                                <?php else: ?>
                                    <div class="thumb sin-foto"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                                <div class="flex-fill">
                                    <div class="fw-semibold small"><?= e($m['marca'] . ' ' . $m['modelo']) ?></div>
                                    <div class="small">S/ <?= number_format((float)$m['precio'], 2) ?> · <?= badgeEstado($m['estado']) ?></div>
                                </div>
                                <a class="btn btn-sm btn-outline-primary" href="editar.php?id=<?= $m['id'] ?>" aria-label="Editar moto"><i class="bi bi-pencil-square"></i></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="small text-muted px-3 py-2 border-top">Registrado el <?= date('d/m/Y H:i', strtotime($editando['creado_en'])) ?></div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Lista de usuarios -->
    <div class="col-xl-8">
        <div class="panel">
            <div class="panel-titulo flex-wrap gap-2">
                <h2>Cuentas registradas</h2>
                <div class="chips">
                    <a class="chip <?= $rol === '' ? 'activa' : '' ?>" href="<?= e(urlUsuarios(['rol' => '', 'pagina' => 1])) ?>">Todos</a>
                    <a class="chip <?= $rol === 'admin' ? 'activa' : '' ?>" href="<?= e(urlUsuarios(['rol' => 'admin', 'pagina' => 1])) ?>">Admins <span class="n"><?= (int)$totales['admins'] ?></span></a>
                    <a class="chip <?= $rol === 'cliente' ? 'activa' : '' ?>" href="<?= e(urlUsuarios(['rol' => 'cliente', 'pagina' => 1])) ?>">Clientes <span class="n"><?= (int)$totales['clientes'] ?></span></a>
                </div>
            </div>
            <form method="get" class="d-flex gap-2 px-3 pt-3">
                <?php if ($rol): ?><input type="hidden" name="rol" value="<?= e($rol) ?>"><?php endif; ?>
                <input type="search" name="q" class="form-control" placeholder="Buscar por nombre, correo o celular" value="<?= e($q) ?>" aria-label="Buscar usuarios">
                <button class="btn btn-petrol" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Buscar</span></button>
                <?php if ($q !== ''): ?><a class="btn btn-outline-secondary" href="<?= e(urlUsuarios(['q' => '', 'pagina' => 1])) ?>" aria-label="Limpiar búsqueda"><i class="bi bi-x-lg"></i></a><?php endif; ?>
            </form>
            <div class="table-responsive mt-3">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Usuario</th><th>Celular</th><th>Rol</th><th class="text-end">Motos</th><th>Desde</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php if (!$lista): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No se encontraron usuarios.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($lista as $u): $esYo = (int)$u['id'] === $miId; ?>
                        <tr class="<?= $editando && (int)$editando['id'] === (int)$u['id'] ? 'table-warning' : '' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar <?= $u['rol'] === 'admin' ? 'admin' : '' ?>"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
                                    <div>
                                        <div class="fw-semibold"><?= e($u['nombre']) ?><?= $esYo ? ' <span class="badge text-bg-light border">Tú</span>' : '' ?></div>
                                        <div class="small text-muted"><?= e($u['usuario']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($u['telefono'] ?? '—') ?></td>
                            <td>
                                <?php if ($u['rol'] === 'admin'): ?>
                                    <span class="badge text-bg-dark"><i class="bi bi-shield-lock me-1"></i>Admin</span>
                                <?php else: ?>
                                    <span class="badge text-bg-info"><i class="bi bi-person me-1"></i>Cliente</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= (int)$u['motos'] ?></td>
                            <td class="small text-muted"><?= date('d/m/Y', strtotime($u['creado_en'])) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(urlUsuarios(['editar' => (int)$u['id']])) ?>" title="Ver y editar" aria-label="Ver y editar"><i class="bi bi-pencil-square"></i></a>
                                <?php if (!$esYo): ?>
                                    <form class="d-inline" method="post"><?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="cambiar_rol">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="<?= $u['rol'] === 'admin' ? 'Quitar admin' : 'Hacer admin' ?>">
                                            <i class="bi <?= $u['rol'] === 'admin' ? 'bi-shield-x' : 'bi-shield-plus' ?>"></i><span class="visually-hidden"><?= $u['rol'] === 'admin' ? 'Quitar admin' : 'Hacer admin' ?></span>
                                        </button>
                                    </form>
                                    <form class="d-inline" method="post"
                                          onsubmit="return confirm('¿Eliminar a <?= e(addslashes($u['nombre'])) ?>? También se borrarán sus <?= (int)$u['motos'] ?> moto(s) publicadas.');"><?= campoCsrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Paginación de usuarios">
                <span class="text-muted small"><?= $total ?> usuario(s) · Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <ul class="pagination mb-0">
                    <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(urlUsuarios(['pagina' => $pagina - 1])) ?>">Anterior</a></li>
                    <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(urlUsuarios(['pagina' => $pagina + 1])) ?>">Siguiente</a></li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer_admin.php'; ?>
