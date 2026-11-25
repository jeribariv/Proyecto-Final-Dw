<?php
/**
 * admin/index.php — BACKEND. Dashboard: resumen general, solicitudes pendientes y últimos movimientos.
 */
require '../includes/conexion.php';
verificarAdmin();

try {
    // Indicadores (COUNT / SUM con condiciones)
    $kpi = $pdo->query(
        "SELECT
            SUM(estado = 'aprobada')                                    AS aprobadas,
            SUM(estado = 'pendiente')                                   AS pendientes,
            SUM(estado = 'rechazada')                                   AS rechazadas,
            COALESCE(SUM(CASE WHEN estado = 'aprobada' THEN precio END), 0) AS valor
         FROM motos"
    )->fetch();
    $usuarios = $pdo->query(
        "SELECT SUM(rol = 'cliente') AS clientes, SUM(rol = 'admin') AS admins FROM usuarios"
    )->fetch();

    // Últimas solicitudes pendientes de clientes
    $solicitudes = $pdo->query(
        "SELECT m.id, m.modelo, m.precio, m.foto, m.creado_en, ma.nombre_marca AS marca, u.nombre AS cliente
         FROM motos m
         INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
         INNER JOIN usuarios u ON m.id_usuario = u.id
         WHERE m.estado = 'pendiente'
         ORDER BY m.creado_en ASC
         LIMIT 5"
    )->fetchAll();

    // Últimas motos registradas
    $ultimas = $pdo->query(
        "SELECT m.id, m.modelo, m.precio, m.foto, m.estado, ma.nombre_marca AS marca
         FROM motos m INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
         ORDER BY m.id DESC LIMIT 5"
    )->fetchAll();

    // Últimos clientes registrados
    $clientes = $pdo->query(
        "SELECT nombre, usuario, creado_en FROM usuarios WHERE rol = 'cliente' ORDER BY id DESC LIMIT 5"
    )->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al cargar el dashboard.', $ex);
}

$titulo = 'Dashboard';
include '../includes/header_admin.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="mb-0">Hola, <?= e($_SESSION['nombre']) ?></h1>
        <div class="text-muted">Resumen del sistema al <?= date('d/m/Y') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="usuarios.php"><i class="bi bi-person-plus me-1"></i>Añadir admin</a>
        <a class="btn btn-amber" href="agregar.php"><i class="bi bi-plus-lg me-1"></i>Nueva moto</a>
    </div>
</div>

<!-- Indicadores -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <a class="stat" href="motos.php?estado=aprobada">
            <div class="icono ic-petrol"><i class="bi bi-bicycle"></i></div>
            <div><div class="num"><?= (int)$kpi['aprobadas'] ?></div><div class="lbl">Motos en catálogo</div></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="stat" href="solicitudes.php">
            <div class="icono ic-amber"><i class="bi bi-inbox"></i></div>
            <div><div class="num"><?= (int)$kpi['pendientes'] ?></div><div class="lbl">Solicitudes pendientes</div></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="stat" href="usuarios.php?rol=cliente">
            <div class="icono ic-green"><i class="bi bi-people"></i></div>
            <div><div class="num"><?= (int)$usuarios['clientes'] ?></div><div class="lbl">Clientes · <?= (int)$usuarios['admins'] ?> admin(s)</div></div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a class="stat" href="reportes.php">
            <div class="icono ic-ink"><i class="bi bi-cash-stack"></i></div>
            <div><div class="num">S/ <?= number_format((float)$kpi['valor'] / 1000, 1) ?>k</div><div class="lbl">Valor del catálogo</div></div>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Solicitudes pendientes -->
    <div class="col-xl-7">
        <div class="panel h-100">
            <div class="panel-titulo">
                <h2><i class="bi bi-inbox me-2"></i>Solicitudes de clientes</h2>
                <a class="small" href="solicitudes.php">Ver todas</a>
            </div>
            <?php if (!$solicitudes): ?>
                <div class="text-center text-muted py-5"><i class="bi bi-check2-all fs-1"></i><div>No hay solicitudes pendientes.</div></div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($solicitudes as $s): ?>
                        <li class="list-group-item d-flex align-items-center gap-3 py-3">
                            <?php if (fotoMoto($s['foto'])): ?>
                                <img class="thumb" src="<?= e(fotoMoto($s['foto'])) ?>" alt="">
                            <?php else: ?>
                                <div class="thumb sin-foto"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                            <div class="flex-fill">
                                <div class="fw-semibold"><?= e($s['marca'] . ' ' . $s['modelo']) ?></div>
                                <div class="small text-muted"><?= e($s['cliente']) ?> · S/ <?= number_format((float)$s['precio'], 2) ?> · <?= date('d/m/Y', strtotime($s['creado_en'])) ?></div>
                            </div>
                            <form method="post" action="solicitudes.php" class="d-flex gap-1"><?= campoCsrf() ?>
                                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                <input type="hidden" name="volver" value="index.php">
                                <button class="btn btn-sm btn-success" name="accion" value="aprobar" title="Aprobar"><i class="bi bi-check-lg"></i></button>
                                <a class="btn btn-sm btn-outline-secondary" href="solicitudes.php#s<?= $s['id'] ?>" title="Revisar">Revisar</a>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clientes recientes -->
    <div class="col-xl-5">
        <div class="panel h-100">
            <div class="panel-titulo">
                <h2><i class="bi bi-people me-2"></i>Clientes nuevos</h2>
                <a class="small" href="usuarios.php">Gestionar</a>
            </div>
            <ul class="list-group list-group-flush">
                <?php foreach ($clientes as $c): ?>
                    <li class="list-group-item d-flex align-items-center gap-3 py-3">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($c['nombre'], 0, 1))) ?></span>
                        <div class="flex-fill">
                            <div class="fw-semibold"><?= e($c['nombre']) ?></div>
                            <div class="small text-muted"><?= e($c['usuario']) ?></div>
                        </div>
                        <span class="small text-muted"><?= date('d/m/Y', strtotime($c['creado_en'])) ?></span>
                    </li>
                <?php endforeach; ?>
                <?php if (!$clientes): ?><li class="list-group-item text-muted py-4 text-center">Aún no hay clientes.</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Últimas motos -->
    <div class="col-12">
        <div class="panel">
            <div class="panel-titulo">
                <h2><i class="bi bi-clock-history me-2"></i>Últimas motos registradas</h2>
                <a class="small" href="motos.php">Ir al inventario</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Foto</th><th>Moto</th><th>Estado</th><th class="text-end">Precio</th><th class="text-end"></th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimas as $m): ?>
                        <tr>
                            <td>
                                <?php if (fotoMoto($m['foto'])): ?>
                                    <img class="thumb" src="<?= e(fotoMoto($m['foto'])) ?>" alt="">
                                <?php else: ?>
                                    <div class="thumb sin-foto"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?= e($m['marca'] . ' ' . $m['modelo']) ?></td>
                            <td><?= badgeEstado($m['estado']) ?></td>
                            <td class="text-end">S/ <?= number_format((float)$m['precio'], 2) ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="editar.php?id=<?= $m['id'] ?>"><i class="bi bi-pencil-square"></i></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer_admin.php'; ?>
