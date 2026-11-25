<?php
/**
 * includes/header_front.php — Cabecera del FRONTEND (catálogo público y zona de clientes).
 * Variables opcionales: $titulo, $sinContenedor (true = la página maneja su propio ancho).
 */
$titulo = $titulo ?? 'Motos Monfort';
$sinContenedor = $sinContenedor ?? false;
$actual = basename(dirname($_SERVER['PHP_SELF'])) . '/' . basename($_SERVER['PHP_SELF']);
function navActivo(string $ruta, string $actual): string { return substr($actual, -strlen($ruta)) === $ruta ? 'active' : ''; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | Motos Monfort</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=Barlow:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="<?= RAIZ ?>assets/css/estilos.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand fs-3" href="<?= RAIZ ?>catalogo.php"><i class="bi bi-speedometer2 text-warning me-2"></i>Motos Monfort</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= navActivo('/catalogo.php', $actual) ?>" href="<?= RAIZ ?>catalogo.php"><i class="bi bi-grid-3x3-gap me-1"></i>Catálogo</a></li>
                <?php if (esCliente()): ?>
                    <li class="nav-item"><a class="nav-link <?= navActivo('cliente/publicar.php', $actual) ?>" href="<?= RAIZ ?>cliente/publicar.php"><i class="bi bi-plus-circle me-1"></i>Publicar mi moto</a></li>
                    <li class="nav-item"><a class="nav-link <?= navActivo('cliente/index.php', $actual) ?>" href="<?= RAIZ ?>cliente/index.php"><i class="bi bi-person-badge me-1"></i>Mis motos</a></li>
                <?php elseif (!estaLogueado()): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= RAIZ ?>registro.php"><i class="bi bi-tag me-1"></i>Vende tu moto</a></li>
                <?php endif; ?>
            </ul>

            <?php if (esAdmin()): ?>
                <a class="btn btn-amber btn-sm me-2" href="<?= RAIZ ?>admin/index.php"><i class="bi bi-speedometer me-1"></i>Panel admin</a>
                <a class="btn btn-outline-light btn-sm" href="<?= RAIZ ?>logout.php">Salir</a>
            <?php elseif (esCliente()): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['nombre']) ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= RAIZ ?>cliente/index.php"><i class="bi bi-person-badge me-2"></i>Mis motos</a></li>
                        <li><a class="dropdown-item" href="<?= RAIZ ?>cliente/publicar.php"><i class="bi bi-plus-circle me-2"></i>Publicar mi moto</a></li>
                        <li><a class="dropdown-item" href="<?= RAIZ ?>cliente/perfil.php"><i class="bi bi-person-gear me-2"></i>Mi perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= RAIZ ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a class="btn btn-outline-light btn-sm me-2" href="<?= RAIZ ?>login.php">Ingresar</a>
                <a class="btn btn-amber btn-sm" href="<?= RAIZ ?>registro.php">Crear cuenta</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<main class="<?= $sinContenedor ? '' : 'container py-4' ?>">
    <div class="<?= $sinContenedor ? 'container pt-3' : '' ?>">
        <?php include __DIR__ . '/alertas.php'; ?>
    </div>
