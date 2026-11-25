<?php
/**
 * admin/solicitudes.php — BACKEND. Revisión de las motos que publican los clientes.
 * Aprobar: estado = 'aprobada' (aparece en el catálogo).
 * Rechazar: estado = 'rechazada' + motivo en la columna observacion (el cliente lo ve en "Mis motos").
 */
require '../includes/conexion.php';
verificarAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $motivo = trim($_POST['motivo'] ?? '');
    $volver = ($_POST['volver'] ?? '') === 'index.php' ? 'index.php' : 'solicitudes.php';

    try {
        if ($accion === 'aprobar') {
            $stmt = $pdo->prepare("UPDATE motos SET estado = 'aprobada', observacion = NULL WHERE id = :id");
            $stmt->execute([':id' => $id]);
            flash('success', 'Moto aprobada: ya aparece en el catálogo.');
        } elseif ($accion === 'rechazar') {
            $stmt = $pdo->prepare("UPDATE motos SET estado = 'rechazada', observacion = :motivo WHERE id = :id");
            $stmt->execute([':motivo' => mb_substr($motivo, 0, 255) ?: null, ':id' => $id]);
            flash('warning', 'Moto rechazada. El cliente verá el motivo en su cuenta.');
        }
    } catch (PDOException $ex) {
        flash('danger', 'No se pudo actualizar la solicitud.');
    }
    ir('admin/' . $volver);
}

try {
    $solicitudes = $pdo->query(
        "SELECT m.*, ma.nombre_marca AS marca, u.nombre AS cliente, u.usuario AS correo, u.telefono
         FROM motos m
         INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
         INNER JOIN usuarios u ON m.id_usuario = u.id
         WHERE m.estado = 'pendiente'
         ORDER BY m.creado_en ASC"
    )->fetchAll();

    $rechazadas = (int)$pdo->query("SELECT COUNT(*) FROM motos WHERE estado = 'rechazada'")->fetchColumn();
} catch (PDOException $ex) {
    errorBD('Error al cargar las solicitudes.', $ex);
}

$titulo = 'Solicitudes';
include '../includes/header_admin.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="mb-0">Solicitudes de clientes</h1>
        <div class="text-muted"><?= count($solicitudes) ?> moto(s) esperando revisión</div>
    </div>
    <a class="btn btn-outline-secondary" href="motos.php?estado=rechazada"><i class="bi bi-x-circle me-1"></i>Ver rechazadas (<?= $rechazadas ?>)</a>
</div>

<?php if (!$solicitudes): ?>
    <div class="panel text-center py-5">
        <i class="bi bi-check2-all fs-1 text-success"></i>
        <h2 class="h4 mt-2">¡Todo al día!</h2>
        <p class="text-muted mb-0">No hay publicaciones de clientes pendientes de revisión.</p>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php foreach ($solicitudes as $s): $foto = fotoMoto($s['foto']); ?>
        <div class="col-md-6 col-xl-4" id="s<?= $s['id'] ?>">
            <article class="solicitud">
                <div class="foto">
                    <?php if ($foto): ?>
                        <img src="<?= e($foto) ?>" alt="<?= e($s['marca'] . ' ' . $s['modelo']) ?>">
                    <?php else: ?>
                        <div class="sin-foto"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                </div>
                <div class="p-3 flex-fill d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase small fw-semibold" style="color:var(--petrol)"><?= e($s['marca']) ?></div>
                            <h2 class="h4 mb-0"><?= e($s['modelo']) ?></h2>
                        </div>
                        <div class="fs-5 fw-bold text-nowrap">S/ <?= number_format((float)$s['precio'], 2) ?></div>
                    </div>
                    <div class="small text-muted my-2">
                        <?= e(nombreTipo($s['tipo'])) ?> · <?= (int)$s['cilindrada_cc'] ?> cc · VIN <span class="vin"><?= e($s['vin_chasis']) ?></span>
                    </div>
                    <?php if ($s['descripcion']): ?><p class="small mb-2"><?= nl2br(e($s['descripcion'])) ?></p><?php endif; ?>

                    <div class="vendedor small mb-3">
                        <div class="fw-semibold"><i class="bi bi-person-circle me-1"></i><?= e($s['cliente']) ?></div>
                        <div><?= e($s['correo']) ?><?= $s['telefono'] ? ' · ' . e($s['telefono']) : '' ?></div>
                        <div class="text-muted">Enviada el <?= date('d/m/Y H:i', strtotime($s['creado_en'])) ?></div>
                    </div>

                    <div class="mt-auto">
                        <form method="post" class="mb-2"><?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn btn-success w-100" name="accion" value="aprobar"><i class="bi bi-check-lg me-1"></i>Aprobar y publicar</button>
                        </form>
                        <form method="post" class="input-group input-group-sm"><?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <input type="text" class="form-control" name="motivo" maxlength="255" placeholder="Motivo del rechazo (opcional)" aria-label="Motivo del rechazo">
                            <button class="btn btn-outline-danger" name="accion" value="rechazar"><i class="bi bi-x-lg me-1"></i>Rechazar</button>
                        </form>
                        <div class="d-flex justify-content-between mt-2 small">
                            <a href="editar.php?id=<?= $s['id'] ?>"><i class="bi bi-pencil-square me-1"></i>Corregir datos</a>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    <?php endforeach; ?>
</div>
<?php include '../includes/footer_admin.php'; ?>
