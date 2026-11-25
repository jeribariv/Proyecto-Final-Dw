<?php
/**
 * cliente/publicar.php — FRONTEND. El cliente publica su moto.
 * Se guarda con estado 'pendiente' y su id_usuario; un administrador la aprueba en admin/solicitudes.php.
 */
require '../includes/conexion.php';
verificarCliente();

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
                "INSERT INTO motos (vin_chasis, modelo, tipo, cilindrada_cc, precio, id_marca, foto, descripcion, estado, id_usuario)
                 VALUES (:vin, :modelo, :tipo, :cc, :precio, :marca, :foto, :descripcion, 'pendiente', :usuario)"
            );
            $stmt->execute([
                ':vin' => $d['vin_chasis'], ':modelo' => $d['modelo'], ':tipo' => $d['tipo'], ':cc' => (int)$d['cilindrada_cc'],
                ':precio' => number_format((float)$d['precio'], 2, '.', ''), ':marca' => (int)$d['id_marca'], ':foto' => $foto,
                ':descripcion' => $d['descripcion'] ?: null, ':usuario' => $_SESSION['usuario_id'],
            ]);
            flash('success', '¡Listo! Tu moto fue enviada. Aparecerá en el catálogo cuando un administrador la apruebe.');
            ir('cliente/index.php');
        } catch (PDOException $ex) {
            borrarFoto($foto);
            $errores[] = $ex->getCode() === '23000'
                ? 'Ya existe una moto registrada con ese número de chasis.'
                : 'No se pudo enviar tu publicación.';
        }
    }
}

$titulo = 'Publicar mi moto';
include '../includes/header_front.php';
?>
<div class="row g-4">
    <div class="col-lg-8">
        <h1 class="mb-1">Publicar mi moto</h1>
        <p class="text-muted">Completa los datos. Tu publicación quedará <strong>pendiente</strong> hasta que un administrador la revise.</p>
        <div class="panel p-4">
            <?php foreach ($errores as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3 needs-validation" novalidate><?= campoCsrf() ?>
                <?php include '../includes/form_moto.php'; ?>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-amber" type="submit"><i class="bi bi-send me-1"></i>Enviar publicación</button>
                    <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="pasos d-grid gap-3 mt-lg-5">
            <div class="paso"><div class="num">1</div><h3 class="h5 mb-1">Envías tu moto</h3><p class="mb-0 text-muted">Con foto, precio y una descripción clara.</p></div>
            <div class="paso"><div class="num">2</div><h3 class="h5 mb-1">La revisamos</h3><p class="mb-0 text-muted">Un administrador verifica los datos.</p></div>
            <div class="paso"><div class="num">3</div><h3 class="h5 mb-1">Sale en el catálogo</h3><p class="mb-0 text-muted">Los interesados verán tu nombre y celular.</p></div>
        </div>
    </div>
</div>
<?php include '../includes/footer_front.php'; ?>
