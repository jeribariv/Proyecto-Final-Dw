<?php
/**
 * descargar_fotos.php
 * Descarga UNA sola vez las fotos reales de las motos de ejemplo desde Wikimedia Commons
 * (fotos con licencia libre) y las guarda en uploads/ con el nombre que usa la base de datos.
 * Uso: inicia sesión como admin y entra a Panel → Descargar fotos.
 */
require '../includes/conexion.php';
verificarAdmin();
set_time_limit(180);

// nombre de archivo => búsquedas a probar en Commons (en orden)
$fotos = [
    'bajaj-pulsar-ns200.jpg'  => ['Bajaj Pulsar NS200', 'Pulsar 200NS'],
    'bajaj-pulsar-rs200.jpg'  => ['Bajaj Pulsar RS200', 'Pulsar RS200'],
    'bajaj-boxer-ct100.jpg'   => ['Bajaj Boxer', 'Bajaj CT100'],
    'bajaj-dominar-400.jpg'   => ['Bajaj Dominar 400', 'Bajaj Dominar'],
    'honda-cb190r.jpg'        => ['Honda CB190R', 'Honda CBF190R', 'Honda CB190'],
    'honda-xr150l.jpg'        => ['Honda XR150L', 'Honda XR 150'],
    'honda-navi-110.jpg'      => ['Honda Navi'],
    'honda-cbr250r.jpg'       => ['Honda CBR250R', 'Honda CBR250'],
    'yamaha-mt15.jpg'         => ['Yamaha MT-15', 'Yamaha MT15'],
    'yamaha-r15.jpg'          => ['Yamaha YZF-R15', 'Yamaha R15'],
    'yamaha-xtz150.jpg'       => ['Yamaha XTZ150', 'Yamaha XTZ 150', 'Yamaha XTZ125'],
    'yamaha-nmax-155.jpg'     => ['Yamaha NMAX 155', 'Yamaha NMAX'],
    'suzuki-gixxer-sf250.jpg' => ['Suzuki Gixxer SF 250', 'Suzuki Gixxer 250', 'Suzuki Gixxer'],
    'tvs-apache-rtr200.jpg'   => ['TVS Apache RTR 200', 'TVS Apache RTR', 'TVS Apache'],
];

/** Descarga una URL y devuelve su contenido (o null si falla). */
function descargar(string $url): ?string
{
    $agente = 'MotosMonfort/1.0 (proyecto estudiantil IESTP Misioneros Monfortianos)';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => $agente,
        ]);
        $datos = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($datos !== false && $codigo === 200) ? $datos : null;
    }
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: $agente\r\n", 'timeout' => 30]]);
    $datos = @file_get_contents($url, false, $ctx);
    return $datos === false ? null : $datos;
}

/** Busca en Wikimedia Commons y devuelve la URL de la primera foto JPG encontrada. */
function buscarFoto(string $texto): ?array
{
    $api = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'       => 'query',
        'format'       => 'json',
        'generator'    => 'search',
        'gsrsearch'    => $texto . ' filetype:bitmap',
        'gsrnamespace' => 6,       // espacio de nombres "Archivo"
        'gsrlimit'     => 8,
        'prop'         => 'imageinfo',
        'iiprop'       => 'url|mime',
        'iiurlwidth'   => 1000,    // miniatura de 1000 px de ancho
    ]);
    $json = descargar($api);
    if (!$json) return null;
    $res = json_decode($json, true);
    $paginas = $res['query']['pages'] ?? [];
    usort($paginas, function ($a, $b) {
        return ($a['index'] ?? 0) <=> ($b['index'] ?? 0);
    });
    foreach ($paginas as $p) {
        $info = $p['imageinfo'][0] ?? null;
        if ($info && $info['mime'] === 'image/jpeg' && !empty($info['thumburl'])) {
            return ['url' => $info['thumburl'], 'pagina' => $info['descriptionurl'] ?? '', 'titulo' => $p['title']];
        }
    }
    return null;
}

$resultados = [];
$correr = isset($_POST['descargar']);
if ($correr) {
    foreach ($fotos as $archivo => $busquedas) {
        $ruta = UPLOADS_DIR . $archivo;
        if (is_file($ruta) && !isset($_POST['reemplazar'])) {
            $resultados[] = [$archivo, 'ya', 'Ya existía, no se tocó', ''];
            continue;
        }
        $hecho = false;
        foreach ($busquedas as $b) {
            $foto = buscarFoto($b);
            if ($foto && ($img = descargar($foto['url']))) {
                file_put_contents($ruta, $img);
                $resultados[] = [$archivo, 'ok', $foto['titulo'], $foto['pagina']];
                $hecho = true;
                break;
            }
        }
        if (!$hecho) {
            $resultados[] = [$archivo, 'no', 'No se encontró foto. Súbela a mano desde Editar.', ''];
        }
    }
}

$titulo = 'Descargar fotos';
include '../includes/header_admin.php';
?>
<h1 class="mb-3">Descargar fotos reales de las motos</h1>

<div class="panel p-4 mb-4">
    <p>Este script busca una foto real de cada moto de ejemplo en <strong>Wikimedia Commons</strong> (fotos con licencia libre)
       y la guarda en la carpeta <code>uploads/</code>. Necesitas tener internet.</p>
    <form method="post" class="d-flex flex-wrap align-items-center gap-3"><?= campoCsrf() ?>
        <button class="btn btn-amber" type="submit" name="descargar" value="1"><i class="bi bi-cloud-download me-1"></i>Descargar fotos</button>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="reemplazar" id="reemplazar">
            <label class="form-check-label" for="reemplazar">Reemplazar las fotos que ya existen</label>
        </div>
    </form>
    <?php if (!function_exists('curl_init') && !ini_get('allow_url_fopen')): ?>
        <div class="alert alert-warning mt-3 mb-0">Tu PHP no tiene <code>curl</code> ni <code>allow_url_fopen</code> activos. Actívalos en WampServer (PHP &gt; Extensiones de PHP &gt; php_curl).</div>
    <?php endif; ?>
</div>

<?php if ($correr): ?>
    <div class="panel">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Foto</th><th>Archivo</th><th>Resultado</th><th>Crédito / origen</th></tr></thead>
                <tbody>
                <?php foreach ($resultados as [$archivo, $estado, $detalle, $pagina]): ?>
                    <tr>
                        <td>
                            <?php if (is_file(UPLOADS_DIR . $archivo)): ?>
                                <img class="thumb" src="<?= e(fotoMoto($archivo)) ?>?v=<?= time() ?>" alt="">
                            <?php else: ?>
                                <div class="thumb sin-foto"><i class="bi bi-image"></i></div>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($archivo) ?></code></td>
                        <td>
                            <?php if ($estado === 'ok'): ?><span class="badge text-bg-success">Descargada</span>
                            <?php elseif ($estado === 'ya'): ?><span class="badge text-bg-secondary">Ya existía</span>
                            <?php else: ?><span class="badge text-bg-danger">No encontrada</span><?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($pagina): ?><a href="<?= e($pagina) ?>" target="_blank" rel="noopener"><?= e($detalle) ?></a>
                            <?php else: ?><?= e($detalle) ?><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3 d-flex gap-2">
        <a class="btn btn-petrol" href="../catalogo.php" target="_blank">Ver el catálogo</a>
        <a class="btn btn-outline-secondary" href="motos.php">Ir al inventario</a>
    </div>
    <p class="text-muted small mt-3">Si alguna foto no te gusta, cámbiala desde <strong>Inventario → Editar</strong>.</p>
<?php endif; ?>

<?php include '../includes/footer_admin.php'; ?>
