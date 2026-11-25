<?php
/**
 * cliente/index.php — FRONTEND. "Mis motos": publicaciones del cliente con su estado.
 */
require '../includes/conexion.php';
verificarCliente();

try {
    $stmt = $pdo->prepare(
        'SELECT m.id, m.modelo, m.tipo, m.cilindrada_cc, m.precio, m.foto, m.estado, m.observacion, m.creado_en,
                ma.nombre_marca AS marca
         FROM motos m
         INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
         WHERE m.id_usuario = :usuario
         ORDER BY m.id DESC'
    );
    $stmt->execute([':usuario' => $_SESSION['usuario_id']]);
    $motos = $stmt->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al cargar tus motos.', $ex);
}

// Conteo por estado
$conteo = ['aprobada' => 0, 'pendiente' => 0, 'rechazada' => 0];
foreach ($motos as $m) {
    $conteo[$m['estado']]++;
}

$titulo = 'Mis motos';
include '../includes/header_front.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="mb-0">Mis motos</h1>
        <div class="text-muted">Hola, <?= e($_SESSION['nombre']) ?>. Aquí ves el estado de tus publicaciones.</div>
    </div>
    <a class="btn btn-amber" href="publicar.php"><i class="bi bi-plus-circle me-1"></i>Publicar otra moto</a>
</div>

<div class="row g-3 mb-4">
    <?php foreach (ESTADOS_MOTO as $clave => [$texto, $color]): ?>
        <div class="col-4">
            <div class="panel p-3 text-center border-top border-4 border-<?= $color ?>">
                <div class="fs-2 fw-bold lh-1"><?= $conteo[$clave] ?></div>
                <div class="text-muted small"><?= $texto ?><?= $conteo[$clave] === 1 ? '' : 's' ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (!$motos): ?>
    <div class="panel text-center py-5">
        <i class="bi bi-bicycle fs-1 text-muted"></i>
        <h2 class="h4 mt-2">Aún no publicas ninguna moto</h2>
        <p class="text-muted">Publícala gratis y aparecerá en el catálogo cuando la aprobemos.</p>
        <a class="btn btn-amber" href="publicar.php">Publicar mi primera moto</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($motos as $m): $foto = fotoMoto($m['foto']); ?>
        <div class="col-sm-6 col-lg-4">
            <article class="mi-moto">
                <div class="foto">
                    <?php if ($foto): ?>
                        <img src="<?= e($foto) ?>" alt="<?= e($m['marca'] . ' ' . $m['modelo']) ?>">
                    <?php else: ?>
                        <div class="sin-foto"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                    <span class="estado"><?= badgeEstado($m['estado']) ?></span>
                </div>
                <div class="p-3 d-flex flex-column flex-fill">
                    <div class="text-uppercase small fw-semibold" style="color:var(--petrol)"><?= e($m['marca']) ?></div>
                    <h2 class="h4 mb-1"><?= e($m['modelo']) ?></h2>
                    <div class="text-muted small mb-2"><?= e(nombreTipo($m['tipo'])) ?> · <?= (int)$m['cilindrada_cc'] ?> cc · Enviada el <?= date('d/m/Y', strtotime($m['creado_en'])) ?></div>
                    <div class="fs-4 fw-bold mb-2">S/ <?= number_format((float)$m['precio'], 2) ?></div>

                    <?php if ($m['estado'] === 'pendiente'): ?>
                        <div class="alert alert-warning py-2 small mb-3"><i class="bi bi-hourglass-split me-1"></i>En revisión por un administrador.</div>
                    <?php elseif ($m['estado'] === 'rechazada'): ?>
                        <div class="alert alert-danger py-2 small mb-3">
                            <i class="bi bi-x-circle me-1"></i>Rechazada<?= $m['observacion'] ? ': ' . e($m['observacion']) : '.' ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success py-2 small mb-3"><i class="bi bi-check-circle me-1"></i>Visible en el catálogo.</div>
                    <?php endif; ?>

                    <form class="mt-auto" method="post" action="eliminar.php"
                          onsubmit="return confirm('¿Retirar la publicación de <?= e(addslashes($m['modelo'])) ?>?');"><?= campoCsrf() ?>
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger w-100" type="submit"><i class="bi bi-trash me-1"></i>Retirar publicación</button>
                    </form>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<?php include '../includes/footer_front.php'; ?>
