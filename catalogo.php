<?php
/**
 * catalogo.php
 * Catálogo público de motos: portada, categorías, filtros y tarjetas con su ficha técnica.
 * FRONTEND. Solo muestra motos con estado 'aprobada' (del concesionario o publicadas por clientes).
 */
require 'includes/conexion.php';

$q     = trim($_GET['q'] ?? '');
$marca = (int)($_GET['marca'] ?? 0);
$tipo  = $_GET['tipo'] ?? '';
if (!isset(TIPOS_MOTO[$tipo])) $tipo = '';

// Opciones de orden (lista fija, no se concatena lo que escribe el usuario)
$ordenes = [
    'recientes'   => ['Más recientes',         'm.id DESC'],
    'precio_asc'  => ['Precio: menor a mayor', 'm.precio ASC'],
    'precio_desc' => ['Precio: mayor a menor', 'm.precio DESC'],
    'cc_desc'     => ['Mayor cilindrada',      'm.cilindrada_cc DESC'],
];
$porPagina = 12;
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$orden = $_GET['orden'] ?? 'recientes';
if (!isset($ordenes[$orden])) $orden = 'recientes';

// Filtros con sentencias preparadas (siempre solo motos aprobadas)
$where = ["m.estado = 'aprobada'"];
$params = [];
if ($q !== '') {
    $where[] = '(m.modelo LIKE :q1 OR ma.nombre_marca LIKE :q2)';
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
}
if ($marca > 0) {
    $where[] = 'm.id_marca = :marca';
    $params[':marca'] = $marca;
}
if ($tipo !== '') {
    $where[] = 'm.tipo = :tipo';
    $params[':tipo'] = $tipo;
}
$sqlWhere = 'WHERE ' . implode(' AND ', $where);

try {
    // Datos de la portada
    $stats = $pdo->query("SELECT COUNT(*) AS modelos, COUNT(DISTINCT id_marca) AS marcas,
                                 COALESCE(MIN(precio), 0) AS desde, COALESCE(MAX(cilindrada_cc), 1) AS max_cc
                          FROM motos WHERE estado = 'aprobada'")->fetch();

    // Moto destacada de la portada: la más cara
    $destacada = $pdo->query("SELECT m.modelo, m.precio, m.foto, ma.nombre_marca AS marca
                              FROM motos m INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
                              WHERE m.estado = 'aprobada'
                              ORDER BY m.precio DESC LIMIT 1")->fetch();

    // Cantidad de motos por categoría
    $categorias = [];
    foreach ($pdo->query("SELECT tipo, COUNT(*) AS total FROM motos WHERE estado = 'aprobada' GROUP BY tipo") as $fila) {
        $categorias[$fila['tipo']] = $fila;
    }
    // Foto de ejemplo por categoría: la primera moto de ese tipo que tenga su foto en uploads/
    $fotoTipo = [];
    foreach ($pdo->query("SELECT tipo, foto FROM motos WHERE estado = 'aprobada' ORDER BY id") as $fila) {
        if (!isset($fotoTipo[$fila['tipo']]) && fotoMoto($fila['foto'])) {
            $fotoTipo[$fila['tipo']] = fotoMoto($fila['foto']);
        }
    }

    // Marcas que tienen motos
    $marcas = $pdo->query("SELECT ma.id_marca, ma.nombre_marca, COUNT(m.id) AS total
                           FROM marcas_moto ma INNER JOIN motos m ON m.id_marca = ma.id_marca AND m.estado = 'aprobada'
                           GROUP BY ma.id_marca, ma.nombre_marca
                           ORDER BY ma.nombre_marca")->fetchAll();

    // Total de resultados para la paginación
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM motos m INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca $sqlWhere");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min($pagina, $totalPaginas);

    // Listado del catálogo (página actual)
    // LEFT JOIN con usuarios: si la publicó un cliente, se muestran sus datos de contacto
    $stmt = $pdo->prepare("SELECT m.id, m.vin_chasis, m.modelo, m.tipo, m.cilindrada_cc, m.precio, m.foto, m.descripcion,
                                  ma.nombre_marca AS marca, u.nombre AS vendedor, u.telefono, u.rol AS rol_vendedor
                           FROM motos m
                           INNER JOIN marcas_moto ma ON m.id_marca = ma.id_marca
                           LEFT JOIN usuarios u ON m.id_usuario = u.id
                           $sqlWhere
                           ORDER BY {$ordenes[$orden][1]}, m.id DESC
                           LIMIT :limite OFFSET :offset");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, $k === ':marca' ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limite', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
    $stmt->execute();
    $motos = $stmt->fetchAll();
} catch (PDOException $ex) {
    errorBD('Error al cargar el catálogo.', $ex);
}

/** URL del catálogo manteniendo los filtros actuales y cambiando los que se indiquen. */
function urlCatalogo(array $cambios = []): string
{
    global $q, $marca, $tipo, $orden;
    $datos = array_merge(['q' => $q, 'marca' => $marca, 'tipo' => $tipo, 'orden' => $orden], $cambios);
    $datos = array_filter($datos, function ($v) {
        return $v !== '' && $v !== 0 && $v !== 'recientes';
    });
    if (($datos['pagina'] ?? 1) === 1) unset($datos['pagina']);
    return 'catalogo.php' . ($datos ? '?' . http_build_query($datos) : '') . '#motos';
}

$hayFiltros = $q !== '' || $marca > 0 || $tipo !== '';

$titulo = 'Catálogo';
$sinContenedor = true;
include 'includes/header_front.php';
?>

<!-- ========== PORTADA ========== -->
<section class="hero">
    <div class="container py-5">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <div class="eyebrow mb-2">Catálogo 2026</div>
                <h1 class="mb-3">Encuentra la moto <span>que va contigo</span></h1>
                <p class="lead mb-4">Naked, deportivas, scooters y trail de las mejores marcas. Revisa la ficha técnica de cada unidad y compara precios.</p>
                <div class="hero-stats mb-4">
                    <div><div class="num"><?= (int)$stats['modelos'] ?></div><div class="lbl">Modelos</div></div>
                    <div><div class="num"><?= (int)$stats['marcas'] ?></div><div class="lbl">Marcas</div></div>
                    <div><div class="num">S/ <?= number_format((float)$stats['desde'], 0) ?></div><div class="lbl">Desde</div></div>
                </div>
                <a href="#motos" class="btn btn-amber btn-lg"><i class="bi bi-arrow-down-circle me-2"></i>Ver motos</a>
            </div>
            <?php if ($destacada && fotoMoto($destacada['foto'])): ?>
                <div class="col-lg-6">
                    <div class="destacada">
                        <img src="<?= e(fotoMoto($destacada['foto'])) ?>" alt="<?= e($destacada['marca'] . ' ' . $destacada['modelo']) ?>">
                        <div class="destacada-info">
                            <span class="badge text-bg-warning mb-1">Destacada</span>
                            <div class="fw-bold fs-5"><?= e($destacada['marca'] . ' ' . $destacada['modelo']) ?></div>
                            <div>S/ <?= number_format((float)$destacada['precio'], 0) ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<div class="hero-raya"></div>

<div class="container py-4">

    <!-- ========== CATEGORÍAS ========== -->
    <h2 class="h3 mb-3">Explora por categoría</h2>
    <div class="row g-3 mb-4">
        <?php foreach (TIPOS_MOTO as $clave => $nombre):
            $cat = $categorias[$clave] ?? ['total' => 0];
            $fotoCat = $fotoTipo[$clave] ?? null;
        ?>
            <div class="col-6 col-lg-3">
                <a class="cat-card <?= $tipo === $clave ? 'activa' : '' ?>" href="<?= e(urlCatalogo(['tipo' => $tipo === $clave ? '' : $clave])) ?>">
                    <?php if ($fotoCat): ?>
                        <img src="<?= e($fotoCat) ?>" alt="">
                    <?php else: ?>
                        <div class="sin-foto"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <div class="nombre"><?= e($nombre) ?></div>
                        <div class="cuenta"><?= (int)$cat['total'] ?> modelo(s)</div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- ========== FILTROS ========== -->
    <section id="motos" class="panel p-3 mb-3">
        <form method="get" action="catalogo.php#motos" class="row g-2 mb-3">
            <input type="hidden" name="marca" value="<?= $marca ?>">
            <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
            <div class="col-md-6">
                <input type="search" name="q" class="form-control" placeholder="Buscar por modelo o marca…" value="<?= e($q) ?>" aria-label="Buscar">
            </div>
            <div class="col-md-4">
                <select name="orden" class="form-select" aria-label="Ordenar">
                    <?php foreach ($ordenes as $clave => $op): ?>
                        <option value="<?= $clave ?>" <?= $orden === $clave ? 'selected' : '' ?>><?= e($op[0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-petrol" type="submit"><i class="bi bi-search me-1"></i>Buscar</button>
            </div>
        </form>
        <div class="chips">
            <a class="chip <?= $marca === 0 ? 'activa' : '' ?>" href="<?= e(urlCatalogo(['marca' => 0])) ?>">Todas las marcas</a>
            <?php foreach ($marcas as $m): ?>
                <a class="chip <?= $marca === (int)$m['id_marca'] ? 'activa' : '' ?>" href="<?= e(urlCatalogo(['marca' => (int)$m['id_marca']])) ?>">
                    <?= e($m['nombre_marca']) ?> <span class="n"><?= (int)$m['total'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="d-flex justify-content-between mb-3">
        <div class="text-muted"><strong class="text-dark"><?= $total ?></strong> moto(s) <?= $tipo ? '· ' . e(nombreTipo($tipo)) : '' ?></div>
        <?php if ($hayFiltros): ?><a class="small" href="catalogo.php#motos"><i class="bi bi-x-circle me-1"></i>Quitar filtros</a><?php endif; ?>
    </div>

    <!-- ========== TARJETAS ========== -->
    <?php if (!$motos): ?>
        <div class="panel text-center py-5">
            <i class="bi bi-search fs-1 text-muted"></i>
            <h3 class="mt-2">No encontramos motos con esos filtros</h3>
            <a class="btn btn-amber mt-2" href="catalogo.php#motos">Ver todo el catálogo</a>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($motos as $m): $foto = fotoMoto($m['foto']); ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <article class="moto-card">
                    <div class="foto">
                        <?php if ($foto): ?>
                            <img src="<?= e($foto) ?>" alt="<?= e($m['marca'] . ' ' . $m['modelo']) ?>">
                        <?php else: ?>
                            <div class="sin-foto"><i class="bi bi-image"></i></div>
                        <?php endif; ?>
                        <span class="badge badge-tipo tag"><?= e(nombreTipo($m['tipo'])) ?></span>
                        <span class="cc"><?= (int)$m['cilindrada_cc'] ?> cc</span>
                        <?php if ($m['rol_vendedor'] === 'cliente'): ?>
                            <span class="de-cliente"><i class="bi bi-person-fill me-1"></i>De cliente</span>
                        <?php endif; ?>
                    </div>
                    <div class="cuerpo">
                        <div class="marca"><?= e($m['marca']) ?></div>
                        <h3 class="modelo"><?= e($m['modelo']) ?></h3>
                        <div class="precio-wrap">
                            <div class="precio"><small>Precio</small>S/ <?= number_format((float)$m['precio'], 0) ?></div>
                            <button type="button" class="btn btn-sm btn-amber" data-bs-toggle="modal" data-bs-target="#ficha<?= $m['id'] ?>">
                                Ver ficha <i class="bi bi-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Ficha técnica (ventana modal de Bootstrap) -->
            <div class="modal fade ficha" id="ficha<?= $m['id'] ?>" tabindex="-1" aria-labelledby="tituloFicha<?= $m['id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="row g-0">
                            <div class="col-lg-7 ficha-foto">
                                <?php if ($foto): ?>
                                    <img src="<?= e($foto) ?>" alt="<?= e($m['marca'] . ' ' . $m['modelo']) ?>">
                                <?php else: ?>
                                    <div class="sin-foto"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-lg-5 p-4 d-flex flex-column">
                                <div class="d-flex justify-content-between">
                                    <span class="badge badge-tipo"><?= e(nombreTipo($m['tipo'])) ?></span>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="marca-ficha mt-3"><?= e($m['marca']) ?></div>
                                <h2 class="ficha-modelo mb-3" id="tituloFicha<?= $m['id'] ?>"><?= e($m['modelo']) ?></h2>

                                <h3 class="h6 text-uppercase text-muted">Ficha técnica</h3>
                                <table class="table table-sm mb-3">
                                    <tr><th class="text-muted fw-normal">Marca</th><td class="fw-semibold"><?= e($m['marca']) ?></td></tr>
                                    <tr><th class="text-muted fw-normal">Categoría</th><td class="fw-semibold"><?= e(nombreTipo($m['tipo'])) ?></td></tr>
                                    <tr><th class="text-muted fw-normal">Cilindrada</th><td class="fw-semibold"><?= (int)$m['cilindrada_cc'] ?> cc</td></tr>
                                    <tr><th class="text-muted fw-normal">Chasis (VIN)</th><td class="vin"><?= e($m['vin_chasis']) ?></td></tr>
                                </table>

                                <?php if ($m['descripcion']): ?>
                                    <p class="mb-3"><?= nl2br(e($m['descripcion'])) ?></p>
                                <?php endif; ?>

                                <div class="vendedor mb-3">
                                    <?php if ($m['rol_vendedor'] === 'cliente'): ?>
                                        <div class="small text-muted">Publicado por un cliente</div>
                                        <div class="fw-semibold"><i class="bi bi-person-circle me-1"></i><?= e($m['vendedor']) ?></div>
                                        <?php if ($m['telefono']): ?>
                                            <div><i class="bi bi-telephone me-1"></i><?= e($m['telefono']) ?></div>
                                            <a class="btn btn-sm btn-success mt-2" target="_blank" rel="noopener"
                                               href="<?= e(enlaceWhatsapp($m['telefono'], 'Hola, vi tu ' . $m['marca'] . ' ' . $m['modelo'] . ' en Motos Monfort. ¿Sigue disponible?')) ?>">
                                                <i class="bi bi-whatsapp me-1"></i>Escribir por WhatsApp
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="small text-muted">Vendido por</div>
                                        <div class="fw-semibold"><i class="bi bi-shop me-1"></i>Motos Monfort · Concesionario oficial</div>
                                    <?php endif; ?>
                                </div>

                                <div class="small text-muted mb-1">Cilindrada comparada con la moto más potente</div>
                                <div class="progress mb-4" role="progressbar" aria-label="Nivel de cilindrada">
                                    <div class="progress-bar bg-warning" style="width: <?= round($m['cilindrada_cc'] * 100 / $stats['max_cc']) ?>%"></div>
                                </div>

                                <div class="mt-auto d-flex justify-content-between align-items-end">
                                    <div>
                                        <div class="small text-muted">Precio de lista</div>
                                        <div class="ficha-precio">S/ <?= number_format((float)$m['precio'], 2) ?></div>
                                    </div>
                                    <?php if (esAdmin()): ?>
                                        <a class="btn btn-outline-secondary" href="admin/editar.php?id=<?= $m['id'] ?>"><i class="bi bi-pencil-square me-1"></i>Editar</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <nav class="d-flex justify-content-center mt-4" aria-label="Paginación del catálogo">
            <ul class="pagination">
                <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(urlCatalogo(['pagina' => $pagina - 1])) ?>">Anterior</a></li>
                <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                    <li class="page-item <?= $p === $pagina ? 'active' : '' ?>"><a class="page-link" href="<?= e(urlCatalogo(['pagina' => $p])) ?>"><?= $p ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(urlCatalogo(['pagina' => $pagina + 1])) ?>">Siguiente</a></li>
            </ul>
        </nav>
    <?php endif; ?>

    <!-- ========== VENDE TU MOTO ========== -->
    <?php if (!esAdmin()): ?>
        <section class="vende mt-5">
            <i class="bi bi-tags icono-fondo" aria-hidden="true"></i>
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <h2 class="mb-2">¿Quieres vender tu moto?</h2>
                    <p class="mb-0 fs-5">Publícala gratis. Un administrador la revisa y aparece en este catálogo.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a class="btn btn-dark btn-lg" href="<?= esCliente() ? 'cliente/publicar.php' : 'registro.php' ?>">
                        <i class="bi bi-plus-circle me-2"></i>Publicar mi moto
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php include 'includes/footer_front.php'; ?>
