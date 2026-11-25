<?php
/**
 * admin/motos.php — BACKEND. Inventario completo (todas las motos y todos los estados)
 * con búsqueda, filtros y paginación LIMIT/OFFSET.
 */
require '../includes/conexion.php';
verificarAdmin();

$porPagina = 8;
$q      = trim($_GET['q'] ?? '');
$marca  = (int)($_GET['marca'] ?? 0);
$estado = $_GET['estado'] ?? '';
if (!isset(ESTADOS_MOTO[$estado])) $estado = '';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));

// Filtros dinámicos con sentencias preparadas
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(m.modelo LIKE :q1 OR m.vin_chasis LIKE :q2)';
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
}
if ($marca > 0) {
    $where[] = 'm.id_marca = :marca';
    $params[':marca'] = $marca;
}
if ($estado !== '') {
    $where[] = 'm.estado = :estado';
    $params[':estado'] = $estado;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM motos m $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;   // OFFSET = (página - 1) × registros por página

    $stmt = $pdo->prepare(
        "SELECT m.id, m.vin_chasis, ma.nombre_marca AS marca, m.modelo, m.tipo, m.cilindrada_cc, m.precio, m.foto,
                m.estado, u.nombre AS vendedor
         FROM motos m
         INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
         LEFT JOIN usuarios u ON m.id_usuario = u.id
         $sqlWhere
         ORDER BY m.id DESC
         LIMIT :limite OFFSET :offset"
    );
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':marca' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $motos = $stmt->fetchAll();

    $marcas = $pdo->query('SELECT id_marca, nombre_marca FROM marcas_moto ORDER BY nombre_marca')->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al consultar el inventario.', $ex);
}

function urlPagina(int $p, string $q, int $marca, string $estado): string
{
    return 'motos.php?' . http_build_query(['q' => $q, 'marca' => $marca ?: '', 'estado' => $estado, 'pagina' => $p]);
}

$titulo = 'Inventario';
include '../includes/header_admin.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="mb-0">Inventario de motos</h1>
        <div class="text-muted"><?= $total ?> unidad<?= $total === 1 ? '' : 'es' ?> encontrada<?= $total === 1 ? '' : 's' ?></div>
    </div>
    <a href="agregar.php" class="btn btn-amber"><i class="bi bi-plus-lg me-1"></i>Nueva moto</a>
</div>

<form class="panel p-3 mb-3 row g-2" method="get">
    <div class="col-md-4">
        <label class="visually-hidden" for="q">Buscar</label>
        <input type="search" id="q" name="q" class="form-control" placeholder="Buscar por modelo o chasis" value="<?= e($q) ?>">
    </div>
    <div class="col-md-3">
        <label class="visually-hidden" for="marca">Marca</label>
        <select id="marca" name="marca" class="form-select">
            <option value="0">Todas las marcas</option>
            <?php foreach ($marcas as $m): ?>
                <option value="<?= $m['id_marca'] ?>" <?= $marca === (int)$m['id_marca'] ? 'selected' : '' ?>><?= e($m['nombre_marca']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="visually-hidden" for="estado">Estado</label>
        <select id="estado" name="estado" class="form-select">
            <option value="">Todos los estados</option>
            <?php foreach (ESTADOS_MOTO as $clave => [$texto]): ?>
                <option value="<?= $clave ?>" <?= $estado === $clave ? 'selected' : '' ?>><?= $texto ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-2 d-grid d-md-flex gap-2">
        <button class="btn btn-petrol flex-fill" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="motos.php" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
    </div>
</form>

<div class="panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Foto</th><th>Chasis (VIN)</th><th>Marca</th><th>Modelo</th><th>Categoría</th><th>Estado</th>
                    <th class="text-end">Cilindrada</th><th class="text-end">Precio</th><th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$motos): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">No hay motos con esos filtros. <a href="agregar.php">Registra una nueva</a>.</td></tr>
            <?php endif; ?>
            <?php foreach ($motos as $m): ?>
                <tr>
                    <td>
                        <?php if (fotoMoto($m['foto'])): ?>
                            <img class="thumb" src="<?= e(fotoMoto($m['foto'])) ?>" alt="Foto de <?= e($m['modelo']) ?>">
                        <?php else: ?>
                            <div class="thumb sin-foto"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                    </td>
                    <td class="vin"><?= e($m['vin_chasis']) ?></td>
                    <td><?= e($m['marca']) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e($m['modelo']) ?></div>
                        <div class="small text-muted"><?= $m['vendedor'] ? '<i class="bi bi-person"></i> Cliente: ' . e($m['vendedor']) : '<i class="bi bi-shop"></i> Concesionario' ?></div>
                    </td>
                    <td><span class="badge badge-tipo"><?= e(nombreTipo($m['tipo'])) ?></span></td>
                    <td><?= badgeEstado($m['estado']) ?></td>
                    <td class="text-end"><?= (int)$m['cilindrada_cc'] ?> cc</td>
                    <td class="text-end">S/ <?= number_format((float)$m['precio'], 2) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="editar.php?id=<?= $m['id'] ?>" title="Editar" aria-label="Editar"><i class="bi bi-pencil-square"></i></a>
                        <form class="d-inline" method="post" action="eliminar.php"
                              onsubmit="return confirm('¿Eliminar la moto <?= e(addslashes($m['modelo'])) ?>? También se borrará su foto.');"><?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                            <input type="hidden" name="volver" value="motos.php">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Eliminar" aria-label="Eliminar"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Paginación">
    <span class="text-muted small">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
    <ul class="pagination mb-0">
        <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(urlPagina($pagina - 1, $q, $marca, $estado)) ?>">Anterior</a>
        </li>
        <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e(urlPagina($pagina + 1, $q, $marca, $estado)) ?>">Siguiente</a>
        </li>
    </ul>
</nav>
<?php include '../includes/footer_admin.php'; ?>
