<?php
/**
 * admin/marcas.php — BACKEND. CRUD de marcas en una sola página.
 */
require '../includes/conexion.php';
verificarAdmin();

$editar = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $accion = $_POST['accion'] ?? '';
        $nombre = trim($_POST['nombre_marca'] ?? '');
        $id     = (int)($_POST['id_marca'] ?? 0);

        if ($accion === 'crear' || $accion === 'actualizar') {
            if ($nombre === '' || mb_strlen($nombre) > 50) {
                flash('danger', 'El nombre de la marca es obligatorio (máximo 50 caracteres).');
            } elseif ($accion === 'crear') {
                $pdo->prepare('INSERT INTO marcas_moto (nombre_marca) VALUES (:n)')->execute([':n' => $nombre]);
                flash('success', 'Marca registrada.');
            } else {
                $pdo->prepare('UPDATE marcas_moto SET nombre_marca = :n WHERE id_marca = :id')->execute([':n' => $nombre, ':id' => $id]);
                flash('success', 'Marca actualizada.');
            }
        } elseif ($accion === 'eliminar') {
            // ON DELETE CASCADE borra las motos de la marca; antes se eliminan sus fotos del disco.
            $fotos = $pdo->prepare('SELECT foto FROM motos WHERE id_marca = :id AND foto IS NOT NULL');
            $fotos->execute([':id' => $id]);
            foreach ($fotos->fetchAll() as $f) {
                borrarFoto($f['foto']);
            }
            $pdo->prepare('DELETE FROM marcas_moto WHERE id_marca = :id')->execute([':id' => $id]);
            flash('success', 'Marca eliminada junto con sus motos.');
        }
        ir('admin/marcas.php');
    }

    if (isset($_GET['editar'])) {
        $s = $pdo->prepare('SELECT * FROM marcas_moto WHERE id_marca = :id');
        $s->execute([':id' => (int)$_GET['editar']]);
        $editar = $s->fetch() ?: null;
    }

    $marcas = $pdo->query(
        'SELECT ma.id_marca, ma.nombre_marca, COUNT(m.id) AS total
         FROM marcas_moto ma LEFT JOIN motos m ON m.id_marca = ma.id_marca
         GROUP BY ma.id_marca, ma.nombre_marca ORDER BY ma.nombre_marca'
    )->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error en el catálogo de marcas.', $ex);
}

$titulo = 'Marcas';
include '../includes/header_admin.php';
?>
<h1 class="mb-3">Marcas</h1>
<div class="row g-3">
    <div class="col-lg-4">
        <form method="post" class="panel p-3 needs-validation" novalidate><?= campoCsrf() ?>
            <h2 class="h4"><?= $editar ? 'Editar marca' : 'Nueva marca' ?></h2>
            <input type="hidden" name="accion" value="<?= $editar ? 'actualizar' : 'crear' ?>">
            <?php if ($editar): ?><input type="hidden" name="id_marca" value="<?= $editar['id_marca'] ?>"><?php endif; ?>
            <label class="form-label" for="nombre_marca">Nombre comercial</label>
            <input class="form-control mb-3" id="nombre_marca" name="nombre_marca" maxlength="50" required
                   value="<?= e($editar['nombre_marca'] ?? '') ?>">
            <div class="d-flex gap-2">
                <button class="btn btn-amber" type="submit"><?= $editar ? 'Guardar cambios' : 'Agregar marca' ?></button>
                <?php if ($editar): ?><a class="btn btn-outline-secondary" href="marcas.php">Cancelar</a><?php endif; ?>
            </div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="panel">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Marca</th><th class="text-end">Motos</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($marcas as $m): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($m['nombre_marca']) ?></td>
                            <td class="text-end"><?= (int)$m['total'] ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="marcas.php?editar=<?= $m['id_marca'] ?>" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                                <form class="d-inline" method="post"
                                      onsubmit="return confirm('Se eliminará la marca y sus <?= (int)$m['total'] ?> moto(s) asociada(s). ¿Continuar?');"><?= campoCsrf() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id_marca" value="<?= $m['id_marca'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="text-muted small mt-2">Al eliminar una marca se borran también sus motos (ON DELETE CASCADE).</p>
    </div>
</div>
<?php include '../includes/footer_admin.php'; ?>
