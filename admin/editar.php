<?php
/**
 * admin/editar.php — BACKEND. Edita cualquier moto (del concesionario o de un cliente) y su estado.
 */
require '../includes/conexion.php';
verificarAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$errores = [];

try {
    $stmt = $pdo->prepare(
        'SELECT m.*, u.nombre AS vendedor, u.telefono
         FROM motos m LEFT JOIN usuarios u ON m.id_usuario = u.id
         WHERE m.id = :id'
    );
    $stmt->execute([':id' => $id]);
    $moto = $stmt->fetch();
    if (!$moto) {
        flash('warning', 'La moto solicitada no existe.');
        ir('admin/motos.php');
    }
    $marcas = $pdo->query('SELECT id_marca, nombre_marca FROM marcas_moto ORDER BY nombre_marca')->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al cargar la moto.', $ex);
}

$d = [
    'vin_chasis' => $moto['vin_chasis'], 'modelo' => $moto['modelo'], 'tipo' => $moto['tipo'],
    'cilindrada_cc' => (string)$moto['cilindrada_cc'], 'precio' => $moto['precio'], 'id_marca' => (string)$moto['id_marca'],
    'descripcion' => (string)$moto['descripcion'], 'estado' => $moto['estado'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($d as $campo => $_) {
        $d[$campo] = trim($_POST[$campo] ?? '');
    }
    $errores = validarMoto($d);
    if (!isset(ESTADOS_MOTO[$d['estado']])) $errores[] = 'Selecciona un estado válido.';

    $fotoNueva = null;
    if (!$errores) {
        [$fotoNueva, $errFoto] = subirFoto($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if ($errFoto) $errores[] = $errFoto;
    }

    if (!$errores) {
        $fotoFinal = $fotoNueva ?? $moto['foto'];   // conserva la foto actual si no se sube otra
        try {
            $stmt = $pdo->prepare(
                'UPDATE motos SET vin_chasis = :vin, modelo = :modelo, tipo = :tipo, cilindrada_cc = :cc, precio = :precio,
                        id_marca = :marca, foto = :foto, descripcion = :descripcion, estado = :estado
                 WHERE id = :id'
            );
            $stmt->execute([
                ':vin' => $d['vin_chasis'], ':modelo' => $d['modelo'], ':tipo' => $d['tipo'], ':cc' => (int)$d['cilindrada_cc'],
                ':precio' => number_format((float)$d['precio'], 2, '.', ''), ':marca' => (int)$d['id_marca'],
                ':foto' => $fotoFinal, ':descripcion' => $d['descripcion'] ?: null, ':estado' => $d['estado'], ':id' => $id,
            ]);
            if ($fotoNueva) {
                borrarFoto($moto['foto']);   // unlink() de la imagen anterior
            }
            flash('success', 'Moto actualizada correctamente.');
            ir('admin/motos.php');
        } catch (PDOException $ex) {
            borrarFoto($fotoNueva);
            $errores[] = $ex->getCode() === '23000'
                ? 'Ya existe otra moto con ese chasis (VIN).'
                : 'No se pudo actualizar el registro.';
        }
    }
}

$titulo = 'Editar moto';
$fotoActual = $moto['foto'];
$mostrarEstado = true;
include '../includes/header_admin.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="mb-0">Editar moto</h1>
    <?= badgeEstado($moto['estado']) ?>
</div>

<?php if ($moto['vendedor']): ?>
    <div class="alert alert-info d-flex align-items-center gap-2">
        <i class="bi bi-person-circle fs-4"></i>
        <div>Publicada por el cliente <strong><?= e($moto['vendedor']) ?></strong><?= $moto['telefono'] ? ' · ' . e($moto['telefono']) : '' ?></div>
    </div>
<?php endif; ?>

<div class="panel p-4">
    <?php foreach ($errores as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate><?= campoCsrf() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <?php include '../includes/form_moto.php'; ?>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-amber" type="submit"><i class="bi bi-save me-1"></i>Guardar cambios</button>
            <a class="btn btn-outline-secondary" href="motos.php">Cancelar</a>
        </div>
    </form>
</div>
<?php include '../includes/footer_admin.php'; ?>
