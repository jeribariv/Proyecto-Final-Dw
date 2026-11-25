<?php
/**
 * includes/form_moto.php — Campos del formulario de una moto.
 * Lo usan admin/agregar.php, admin/editar.php y cliente/publicar.php.
 * Necesita: $d (datos), $marcas (lista de marcas).
 * Opcionales: $fotoActual (nombre de la foto actual), $mostrarEstado (true en el admin al editar).
 */
$fotoActual    = $fotoActual ?? null;
$mostrarEstado = $mostrarEstado ?? false;
?>
<div class="col-md-6">
    <label class="form-label" for="modelo">Modelo</label>
    <input class="form-control" id="modelo" name="modelo" maxlength="100" value="<?= e($d['modelo']) ?>" placeholder="Ej. Pulsar NS200" required>
    <div class="invalid-feedback">Escribe el modelo.</div>
</div>
<div class="col-md-6">
    <label class="form-label" for="vin_chasis">N.º de chasis (VIN)</label>
    <input class="form-control" id="vin_chasis" name="vin_chasis" maxlength="30" value="<?= e($d['vin_chasis']) ?>" required>
    <div class="invalid-feedback">Escribe el número de chasis.</div>
</div>
<div class="col-md-3">
    <label class="form-label" for="id_marca">Marca</label>
    <select class="form-select" id="id_marca" name="id_marca" required>
        <option value="">Selecciona…</option>
        <?php foreach ($marcas as $m): ?>
            <option value="<?= $m['id_marca'] ?>" <?= (string)$m['id_marca'] === (string)$d['id_marca'] ? 'selected' : '' ?>><?= e($m['nombre_marca']) ?></option>
        <?php endforeach; ?>
    </select>
    <div class="invalid-feedback">Selecciona la marca.</div>
</div>
<div class="col-md-3">
    <label class="form-label" for="tipo">Categoría</label>
    <select class="form-select" id="tipo" name="tipo" required>
        <?php foreach (TIPOS_MOTO as $clave => $nombre): ?>
            <option value="<?= $clave ?>" <?= $clave === $d['tipo'] ? 'selected' : '' ?>><?= e($nombre) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-3">
    <label class="form-label" for="cilindrada_cc">Cilindrada (cc)</label>
    <input type="number" class="form-control" id="cilindrada_cc" name="cilindrada_cc" min="1" step="1" value="<?= e($d['cilindrada_cc']) ?>" required>
    <div class="invalid-feedback">Número entero mayor que 0.</div>
</div>
<div class="col-md-3">
    <label class="form-label" for="precio">Precio (S/)</label>
    <input type="number" class="form-control" id="precio" name="precio" min="0.01" step="0.01" value="<?= e($d['precio']) ?>" required>
    <div class="invalid-feedback">Precio mayor que 0.</div>
</div>

<?php if ($mostrarEstado): ?>
    <div class="col-md-4">
        <label class="form-label" for="estado">Estado en el catálogo</label>
        <select class="form-select" id="estado" name="estado">
            <?php foreach (ESTADOS_MOTO as $clave => [$texto]): ?>
                <option value="<?= $clave ?>" <?= $clave === $d['estado'] ? 'selected' : '' ?>><?= $texto ?></option>
            <?php endforeach; ?>
        </select>
        <div class="form-text">Solo las motos aprobadas se ven en el catálogo.</div>
    </div>
<?php endif; ?>

<div class="col-12">
    <label class="form-label" for="descripcion">Descripción <span class="text-muted">(opcional · año, kilometraje, estado…)</span></label>
    <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="500"><?= e($d['descripcion']) ?></textarea>
</div>

<div class="col-12">
    <label class="form-label" for="foto">Foto <span class="text-muted">(JPG, PNG o WEBP · máx. 2 MB)</span></label>
    <div class="d-flex align-items-center gap-3">
        <?php if (fotoMoto($fotoActual)): ?>
            <img class="preview-foto" src="<?= e(fotoMoto($fotoActual)) ?>" alt="Foto actual">
        <?php endif; ?>
        <div class="flex-fill">
            <input type="file" class="form-control" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
            <div class="form-text">
                <?= $fotoActual ? 'Si no eliges otra foto, se conserva la actual.' : 'Usa una foto real de la moto, de costado y con buena luz.' ?>
            </div>
        </div>
    </div>
</div>
