<?php
/**
 * admin/agregar.php — BACKEND. El administrador registra una moto del concesionario.
 * Se guarda directamente como 'aprobada' (no necesita revisión) y sin id_usuario.
 */
require '../includes/conexion.php';
verificarAdmin();

$errores = [];
$d = ['vin_chasis' => '', 'modelo' => '', 'tipo' => 'naked', 'cilindrada_cc' => '', 'precio' => '', 'id_marca' => '', 'descripcion' => ''];

try {
    $marcas = $pdo->query('SELECT id_marca, nombre_marca FROM marcas_moto ORDER BY nombre_marca')->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al cargar las marcas.', $ex);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($d as $campo => $_) {
        $d[$campo] = trim($_POST[$campo] ?? '');
    }
    $errores = validarMoto($d);

    $foto = null;
    if (!$errores) {
        [$foto, $errFoto] = subirFoto($_FILES['foto'] ?? ['error' => UPLOAD_ERR_NO_FILE]);
        if ($errFoto) $errores[] = $errFoto;
    }

    if (!$errores) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO motos (vin_chasis, modelo, tipo, cilindrada_cc, precio, id_marca, foto, descripcion, estado)
                 VALUES (:vin, :modelo, :tipo, :cc, :precio, :marca, :foto, :descripcion, 'aprobada')"
            );
            $stmt->execute([
                ':vin' => $d['vin_chasis'], ':modelo' => $d['modelo'], ':tipo' => $d['tipo'], ':cc' => (int)$d['cilindrada_cc'],
                ':precio' => number_format((float)$d['precio'], 2, '.', ''), ':marca' => (int)$d['id_marca'], ':foto' => $foto,
                ':descripcion' => $d['descripcion'] ?: null,
            ]);
            flash('success', 'Moto registrada y publicada en el catálogo.');
            ir('admin/motos.php');
        } catch (PDOException $ex) {
            borrarFoto($foto); // no dejar archivos huérfanos si falla el INSERT
            $errores[] = $ex->getCode() === '23000'
                ? 'Ya existe una moto con ese chasis (VIN). Revisa el número.'
                : 'No se pudo guardar el registro.';
        }
    }
}

$titulo = 'Nueva moto';
include '../includes/header_admin.php';
?>
<h1 class="mb-1">Nueva moto</h1>
<p class="text-muted">Las motos que registra un administrador se publican al instante en el catálogo.</p>
<div class="panel p-4">
    <?php foreach ($errores as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate><?= campoCsrf() ?>
        <?php include '../includes/form_moto.php'; ?>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-amber" type="submit"><i class="bi bi-save me-1"></i>Guardar moto</button>
            <a class="btn btn-outline-secondary" href="motos.php">Cancelar</a>
        </div>
    </form>
</div>
<?php include '../includes/footer_admin.php'; ?>
