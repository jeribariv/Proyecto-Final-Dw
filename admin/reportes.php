<?php
/**
 * admin/reportes.php — BACKEND. Reportes del inventario publicado (solo motos aprobadas).
 */
require '../includes/conexion.php';
verificarAdmin();

$marca = (int)($_GET['marca'] ?? 0);
$ccMin = ($_GET['cc_min'] ?? '') !== '' ? (int)$_GET['cc_min'] : null;
$ccMax = ($_GET['cc_max'] ?? '') !== '' ? (int)$_GET['cc_max'] : null;

try {
    // Resumen por marca (LEFT JOIN + GROUP BY + COUNT + SUM)
    $resumen = $pdo->query(
        "SELECT ma.id_marca, ma.nombre_marca, COUNT(m.id) AS total_unidades,
                COALESCE(SUM(m.precio), 0.00) AS valor_inventario
         FROM marcas_moto ma LEFT JOIN motos m ON ma.id_marca = m.id_marca AND m.estado = 'aprobada'
         GROUP BY ma.id_marca, ma.nombre_marca ORDER BY ma.nombre_marca ASC"
    )->fetchAll();

    // Indicadores generales (COUNT, SUM, AVG)
    $kpi = $pdo->query("SELECT COUNT(*) AS unidades, COALESCE(SUM(precio),0) AS valor, COALESCE(AVG(precio),0) AS promedio FROM motos WHERE estado = 'aprobada'")->fetch();

    $marcas = $pdo->query('SELECT id_marca, nombre_marca FROM marcas_moto ORDER BY nombre_marca')->fetchAll();

    // Resumen por categoría (GROUP BY tipo)
    $porTipo = array_fill_keys(array_keys(TIPOS_MOTO), ['total' => 0, 'valor' => 0, 'foto' => null]);
    foreach ($pdo->query("SELECT tipo, COUNT(*) AS total, SUM(precio) AS valor, MIN(foto) AS foto FROM motos WHERE estado = 'aprobada' GROUP BY tipo") as $f) {
        if (isset($porTipo[$f['tipo']])) $porTipo[$f['tipo']] = ['total' => (int)$f['total'], 'valor' => (float)$f['valor'], 'foto' => $f['foto']];
    }

    // Control de inventario filtrado por marca y rango de cilindrada (INNER JOIN)
    $where = ["m.estado = 'aprobada'"]; $params = [];
    if ($marca > 0)      { $where[] = 'm.id_marca = :marca';         $params[':marca'] = $marca; }
    if ($ccMin !== null) { $where[] = 'm.cilindrada_cc >= :cc_min';  $params[':cc_min'] = $ccMin; }
    if ($ccMax !== null) { $where[] = 'm.cilindrada_cc <= :cc_max';  $params[':cc_max'] = $ccMax; }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT m.id, m.vin_chasis, ma.nombre_marca AS marca, m.modelo, m.cilindrada_cc, m.precio
         FROM motos m INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
         $sqlWhere ORDER BY m.cilindrada_cc ASC, m.modelo ASC"
    );
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
    $stmt->execute();
    $filtradas = $stmt->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al generar reportes.', $ex);
}

$titulo = 'Reportes';
include '../includes/header_admin.php';
?>
<h1 class="mb-3">Reportes del inventario</h1>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="panel kpi p-3"><div class="text-muted">Unidades en inventario</div><div class="num"><?= (int)$kpi['unidades'] ?></div></div></div>
    <div class="col-md-4"><div class="panel kpi p-3"><div class="text-muted">Valor total</div><div class="num">S/ <?= number_format((float)$kpi['valor'], 2) ?></div></div></div>
    <div class="col-md-4"><div class="panel kpi p-3"><div class="text-muted">Precio promedio</div><div class="num">S/ <?= number_format((float)$kpi['promedio'], 2) ?></div></div></div>
</div>

<h2 class="h3">Unidades por categoría</h2>
<div class="row g-3 mb-4">
    <?php foreach (TIPOS_MOTO as $clave => $nombre): ?>
        <div class="col-6 col-lg-3">
            <div class="panel p-3 h-100 d-flex align-items-center gap-3">
                <?php if (fotoMoto($porTipo[$clave]['foto'])): ?>
                    <img class="thumb" src="<?= e(fotoMoto($porTipo[$clave]['foto'])) ?>" alt="">
                <?php else: ?>
                    <div class="thumb sin-foto"><i class="bi bi-image"></i></div>
                <?php endif; ?>
                <div>
                    <div class="text-muted small"><?= e($nombre) ?></div>
                    <div class="fw-bold fs-4 lh-1"><?= $porTipo[$clave]['total'] ?></div>
                    <div class="small text-muted">S/ <?= number_format($porTipo[$clave]['valor'], 0) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="h3">Unidades y valor por marca</h2>
<div class="panel mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Marca</th><th class="text-end">Unidades</th><th style="width:30%">Participación</th><th class="text-end">Valor del inventario</th></tr></thead>
            <tbody>
            <?php foreach ($resumen as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['nombre_marca']) ?></td>
                    <td class="text-end"><?= (int)$r['total_unidades'] ?></td>
                    <td><?php $pct = $kpi['unidades'] ? round($r['total_unidades'] * 100 / $kpi['unidades']) : 0; ?>
                        <div class="d-flex align-items-center gap-2"><div class="barra flex-fill"><span style="width:<?= $pct ?>%"></span></div><small class="text-muted"><?= $pct ?>%</small></div>
                    </td>
                    <td class="text-end">S/ <?= number_format((float)$r['valor_inventario'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<h2 class="h3">Consulta por marca y cilindrada</h2>
<form class="panel p-3 mb-3 row g-2" method="get">
    <div class="col-md-4">
        <label class="form-label" for="marca">Marca</label>
        <select class="form-select" id="marca" name="marca">
            <option value="0">Todas</option>
            <?php foreach ($marcas as $m): ?>
                <option value="<?= $m['id_marca'] ?>" <?= $marca === (int)$m['id_marca'] ? 'selected' : '' ?>><?= e($m['nombre_marca']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="cc_min">Cilindrada desde (cc)</label>
        <input type="number" min="0" class="form-control" id="cc_min" name="cc_min" value="<?= $ccMin ?? '' ?>" placeholder="150">
    </div>
    <div class="col-md-3">
        <label class="form-label" for="cc_max">Hasta (cc)</label>
        <input type="number" min="0" class="form-control" id="cc_max" name="cc_max" value="<?= $ccMax ?? '' ?>" placeholder="250">
    </div>
    <div class="col-md-2 d-flex align-items-end gap-2">
        <button class="btn btn-petrol flex-fill" type="submit">Consultar</button>
        <a class="btn btn-outline-secondary" href="reportes.php" aria-label="Limpiar"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Chasis (VIN)</th><th>Marca</th><th>Modelo</th><th class="text-end">Cilindrada</th><th class="text-end">Precio</th></tr></thead>
            <tbody>
            <?php if (!$filtradas): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Ninguna moto cumple esos criterios.</td></tr>
            <?php endif; ?>
            <?php foreach ($filtradas as $m): ?>
                <tr>
                    <td class="vin"><?= e($m['vin_chasis']) ?></td>
                    <td><?= e($m['marca']) ?></td>
                    <td class="fw-semibold"><?= e($m['modelo']) ?></td>
                    <td class="text-end"><?= (int)$m['cilindrada_cc'] ?> cc</td>
                    <td class="text-end">S/ <?= number_format((float)$m['precio'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="text-muted small mt-2"><?= count($filtradas) ?> resultado(s).</p>
<?php include '../includes/footer_admin.php'; ?>
