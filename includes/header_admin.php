<?php
/**
 * includes/header_admin.php — Cabecera del BACKEND (panel de administración).
 * Barra lateral fija en escritorio y menú desplegable (offcanvas de Bootstrap) en celular.
 * Variable opcional: $titulo.
 */
$titulo = $titulo ?? 'Panel';
$actual = basename($_SERVER['PHP_SELF']);

// Número de solicitudes pendientes para el contador del menú
$pendientes = (int)$pdo->query("SELECT COUNT(*) FROM motos WHERE estado = 'pendiente'")->fetchColumn();

$menu = [
    'index.php'           => ['bi-speedometer',      'Dashboard'],
    'motos.php'           => ['bi-list-ul',          'Inventario'],
    'agregar.php'         => ['bi-plus-square',      'Nueva moto'],
    'solicitudes.php'     => ['bi-inbox',            'Solicitudes'],
    'marcas.php'          => ['bi-tags',             'Marcas'],
    'usuarios.php'        => ['bi-people',           'Usuarios'],
    'reportes.php'        => ['bi-bar-chart',        'Reportes'],
    'descargar_fotos.php' => ['bi-cloud-download',   'Descargar fotos'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | Admin · Motos Monfort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?= RAIZ ?>assets/css/estilos.css" rel="stylesheet">
    <link href="<?= RAIZ ?>assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin">
<div class="d-flex">
    <!-- ========== BARRA LATERAL ========== -->
    <aside class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarTitulo">
        <div class="offcanvas-header">
            <span class="offcanvas-title" id="sidebarTitulo">Menú</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0 w-100">
            <a class="marca-admin" href="index.php">
                <i class="bi bi-speedometer2"></i>
                <span>Motos Monfort<small>Panel de administración</small></span>
            </a>
            <nav class="nav flex-column px-2 py-3">
                <?php foreach ($menu as $archivo => [$icono, $texto]): ?>
                    <a class="nav-link <?= $actual === $archivo ? 'active' : '' ?>" href="<?= $archivo ?>">
                        <i class="bi <?= $icono ?>"></i><?= $texto ?>
                        <?php if ($archivo === 'solicitudes.php' && $pendientes > 0): ?>
                            <span class="badge rounded-pill text-bg-warning ms-auto"><?= $pendientes ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="mt-auto p-3 border-top border-secondary-subtle">
                <a class="btn btn-outline-light btn-sm w-100" href="<?= RAIZ ?>catalogo.php" target="_blank"><i class="bi bi-box-arrow-up-right me-1"></i>Ver sitio público</a>
            </div>
        </div>
    </aside>

    <!-- ========== CONTENIDO ========== -->
    <div class="contenido flex-fill">
        <header class="topbar d-flex align-items-center gap-3 px-3 px-lg-4">
            <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
                <i class="bi bi-list"></i>
            </button>
            <span class="fw-semibold text-muted d-none d-sm-inline"><?= e($titulo) ?></span>
            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="small"><i class="bi bi-person-circle me-1"></i><?= e($_SESSION['nombre']) ?> <span class="badge text-bg-dark">Admin</span></span>
                <a class="btn btn-sm btn-outline-danger" href="<?= RAIZ ?>logout.php"><i class="bi bi-box-arrow-right me-1"></i>Salir</a>
            </div>
        </header>
        <main class="p-3 p-lg-4">
            <?php include __DIR__ . '/alertas.php'; ?>
