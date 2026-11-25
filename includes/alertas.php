<?php
// Muestra el mensaje flash guardado en la sesión (si hay uno) y lo borra.
if (!empty($_SESSION['flash'])):
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
?>
    <div class="alert alert-<?= e($f['tipo']) ?> alert-dismissible fade show" role="alert">
        <?= e($f['texto']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>
