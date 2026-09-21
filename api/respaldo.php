<?php
/**
 * Copias de seguridad guardadas en el servidor — Spa Infinity
 *
 * El panel arma los datos con la sesion de un administrador y los manda aqui.
 * Este archivo les suma lo que no vive en la base de datos:
 *   - las fotos de las fichas y los consentimientos (Supabase Storage), que se
 *     bajan con la misma sesion del administrador;
 *   - los pedidos de gift cards (api/orders) y las fotos subidas desde el
 *     panel (images/subidas), que estan en este servidor.
 * Todo queda en un .zip FUERA de public_html: solo se baja pasando por aqui,
 * y aqui solo entra un administrador.
 *
 *   GET  ?accion=lista                          copias guardadas y configuracion
 *   POST ?accion=guardar&tipo=auto|manual|...   cuerpo: el JSON de datos
 *   POST ?accion=subir                          cuerpo: un .zip (o .json) bajado antes
 *   POST ?accion=config                         { cadaDias, guardar }
 *   GET  ?accion=bajar&nombre=...&formato=zip   el archivo completo
 *   GET  ?accion=bajar&nombre=...&formato=json  solo los datos
 *   POST ?accion=restaurar-archivos&nombre=...  vuelve a subir fotos y pedidos
 */
require_once __DIR__ . '/require-auth.php';
header('Cache-Control: no-store');
date_default_timezone_set('America/Santiago');
@set_time_limit(300);

$yo = require_panel_user(true);
$TOKEN = _bearer_token();

function dir_respaldos() {
    $dir = dirname(__DIR__, 4) . '/respaldos-spainfinity';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        $dir = __DIR__ . '/cache/respaldos';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}
function responder($code, $data) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$DIR = dir_respaldos();
$CFG = $DIR . '/config.json';
const BUCKETS = ['fichas', 'consentimientos', 'documentos'];   // documentos: PDF e imagenes de cada profesional
const PATRON_NOMBRE = '/^respaldo-(\d{4}-\d{2}-\d{2})-(\d{6})-([a-z-]+)\.(json\.gz|zip)$/';

function leer_config($CFG) {
    $c = is_file($CFG) ? json_decode((string)file_get_contents($CFG), true) : null;
    return array_merge(['cadaDias' => 30, 'guardar' => 6, 'ultimo' => null], is_array($c) ? $c : []);
}
function lista_copias($DIR) {
    $out = [];
    foreach (array_merge(glob($DIR . '/respaldo-*.json.gz') ?: [], glob($DIR . '/respaldo-*.zip') ?: []) as $f) {
        $n = basename($f);
        if (!preg_match(PATRON_NOMBRE, $n, $m)) continue;
        $meta = is_file($f . '.meta') ? json_decode((string)file_get_contents($f . '.meta'), true) : [];
        $out[] = [
            'nombre'    => $n,
            'fecha'     => $m[1] . ' ' . substr($m[2],0,2) . ':' . substr($m[2],2,2),
            'tipo'      => $m[3],
            'bytes'     => filesize($f),
            'registros' => $meta['registros'] ?? null,
            'archivos'  => $meta['archivos'] ?? null,
            'faltaron'  => $meta['faltaron'] ?? 0,
            'por'       => $meta['por'] ?? '',
            'conFotos'  => $m[4] === 'zip',
        ];
    }
    usort($out, fn($a, $b) => strcmp($b['nombre'], $a['nombre']));
    return $out;
}
function nombre_valido($DIR, $n) {
    $n = basename((string)$n);
    return (preg_match(PATRON_NOMBRE, $n) && is_file($DIR . '/' . $n)) ? $n : null;
}

/* ── archivos de Supabase Storage, con la sesion de quien pide ── */
function storage_bajar($bucket, $ruta, $token) {
    $ch = curl_init(supa_url() . '/storage/v1/object/authenticated/' . $bucket . '/' . implode('/', array_map('rawurlencode', explode('/', $ruta))));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_anon(), 'Authorization: Bearer ' . $token]]);
    $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $tipo = curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    return $code === 200 ? [$b, $tipo] : [null, null];
}
function storage_subir($bucket, $ruta, $contenido, $tipo, $token) {
    $ch = curl_init(supa_url() . '/storage/v1/object/' . $bucket . '/' . implode('/', array_map('rawurlencode', explode('/', $ruta))));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $contenido,
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_anon(), 'Authorization: Bearer ' . $token,
                               'Content-Type: ' . ($tipo ?: 'application/octet-stream'), 'x-upsert: true']]);
    curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return $code >= 200 && $code < 300;
}
/* rutas de archivos de Storage que aparecen en los datos (fotos de fichas, PDF de consentimientos) */
function rutas_storage($raw) {
    $rutas = [];
    if (preg_match_all('#/storage/v1/object/(?:public|sign|authenticated)/(' . implode('|', BUCKETS) . ')/([^"?\\\\\s]+)#', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) $rutas[$x[1] . '/' . rawurldecode($x[2])] = [$x[1], rawurldecode($x[2])];
    }
    return array_values($rutas);
}
function archivos_servidor() {
    $raiz = dirname(__DIR__);
    $out = [];
    foreach (glob(__DIR__ . '/orders/*.json') ?: [] as $f) $out['servidor/orders/' . basename($f)] = $f;
    foreach (glob($raiz . '/images/subidas/*') ?: [] as $f) if (is_file($f)) $out['servidor/subidas/' . basename($f)] = $f;
    return $out;
}

$accion = $_GET['accion'] ?? '';

if ($accion === 'lista') {
    $c = leer_config($CFG);
    $proxima = ($c['cadaDias'] > 0 && $c['ultimo']) ? date('Y-m-d', strtotime($c['ultimo'] . ' +' . (int)$c['cadaDias'] . ' days')) : null;
    responder(200, ['config' => $c, 'toca' => ($c['cadaDias'] > 0) && (!$c['ultimo'] || date('Y-m-d') >= $proxima),
                    'proxima' => $proxima, 'copias' => lista_copias($DIR)]);
}

if ($accion === 'config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $c = leer_config($CFG);
    if (isset($in['cadaDias'])) $c['cadaDias'] = max(0, min(365, (int)$in['cadaDias']));
    if (isset($in['guardar']))  $c['guardar']  = max(1, min(24, (int)$in['guardar']));
    file_put_contents($CFG, json_encode($c));
    responder(200, ['ok' => true, 'config' => $c]);
}

function podar($DIR, $CFG) {
    $c = leer_config($CFG);
    $copias = lista_copias($DIR);
    $normales = array_values(array_filter($copias, fn($x) => !in_array($x['tipo'], ['antes-de-restaurar', 'subida'], true)));
    $previas  = array_values(array_filter($copias, fn($x) => $x['tipo'] === 'antes-de-restaurar'));
    $subidas  = array_values(array_filter($copias, fn($x) => $x['tipo'] === 'subida'));
    foreach (array_merge(array_slice($normales, (int)$c['guardar']), array_slice($previas, 3), array_slice($subidas, 3)) as $x) {
        @unlink($DIR . '/' . $x['nombre']); @unlink($DIR . '/' . $x['nombre'] . '.meta');
    }
}

if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = preg_replace('/[^a-z-]/', '', (string)($_GET['tipo'] ?? 'manual')) ?: 'manual';
    if (!in_array($tipo, ['auto', 'manual', 'antes-de-restaurar'], true)) $tipo = 'manual';
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (!is_array($j) || !isset($j['tablas']) || !is_array($j['tablas'])) responder(400, ['error' => 'La copia no tiene el formato esperado']);
    $registros = 0;
    foreach ($j['tablas'] as $filas) if (is_array($filas)) $registros += count($filas);

    $nombre = 'respaldo-' . date('Y-m-d-His') . '-' . $tipo . '.zip';
    $ruta = $DIR . '/' . $nombre;
    $zip = new ZipArchive();
    if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) responder(500, ['error' => 'No se pudo crear la copia en el servidor']);
    $zip->addFromString('respaldo.json', $raw);

    /* fotos y documentos de Storage */
    $archivos = 0; $faltaron = [];
    $tipos = [];
    foreach (rutas_storage($raw) as [$bucket, $r]) {
        [$contenido, $ct] = storage_bajar($bucket, $r, $TOKEN);
        if ($contenido === null) { $faltaron[] = $bucket . '/' . $r; continue; }
        $zip->addFromString('archivos/storage/' . $bucket . '/' . $r, $contenido);
        $tipos[$bucket . '/' . $r] = $ct;
        $archivos++;
    }
    /* pedidos de gift cards y fotos subidas desde el panel */
    foreach (archivos_servidor() as $dentro => $f) { $zip->addFile($f, 'archivos/' . $dentro); $archivos++; }
    $zip->addFromString('archivos/tipos.json', json_encode($tipos));
    $zip->addFromString('LEEME.txt', "Copia de seguridad de Spa Infinity\r\nHecha: " . date('d/m/Y H:i') . "\r\nPor: " . ($yo['email'] ?? '') . "\r\n\r\n"
        . "respaldo.json: todos los datos (agenda, clientes, fichas, ventas, caja...)\r\n"
        . "archivos/storage: fotos de fichas y consentimientos\r\narchivos/servidor: pedidos de gift cards y fotos subidas\r\n\r\n"
        . "Para restaurarla: Administracion > Configuracion > Copia de seguridad > Restaurar desde un archivo.\r\n"
        . "Contiene datos de salud de pacientes: guardala en un lugar seguro.\r\n");
    $zip->close();
    @chmod($ruta, 0600);

    file_put_contents($ruta . '.meta', json_encode(['registros' => $registros, 'archivos' => $archivos, 'faltaron' => count($faltaron), 'por' => $yo['email'] ?? '']));
    $c = leer_config($CFG);
    if ($tipo !== 'antes-de-restaurar') { $c['ultimo'] = date('Y-m-d'); file_put_contents($CFG, json_encode($c)); }
    podar($DIR, $CFG);
    responder(200, ['ok' => true, 'nombre' => $nombre, 'registros' => $registros, 'archivos' => $archivos, 'faltaron' => $faltaron]);
}

/* una copia bajada antes y subida de vuelta desde el computador */
if ($accion === 'subir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $base = 'respaldo-' . date('Y-m-d-His') . '-subida';
    if (substr($raw, 0, 2) === "PK") {
        $tmp = $DIR . '/' . $base . '.zip';
        file_put_contents($tmp, $raw);
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true || $zip->locateName('respaldo.json') === false) { @unlink($tmp); responder(400, ['error' => 'Ese .zip no es una copia de seguridad de Spa Infinity']); }
        $j = json_decode((string)$zip->getFromName('respaldo.json'), true);
        $n = 0; for ($i = 0; $i < $zip->numFiles; $i++) if (strpos($zip->getNameIndex($i), 'archivos/') === 0 && substr($zip->getNameIndex($i), -1) !== '/') $n++;
        $zip->close();
        @chmod($tmp, 0600);
        $nombre = $base . '.zip';
    } else {
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['tablas'])) responder(400, ['error' => 'Ese archivo no es una copia de seguridad válida']);
        $nombre = $base . '.json.gz';
        file_put_contents($DIR . '/' . $nombre, gzencode($raw, 6));
        @chmod($DIR . '/' . $nombre, 0600);
        $n = 0;
    }
    $registros = 0;
    foreach (($j['tablas'] ?? []) as $filas) if (is_array($filas)) $registros += count($filas);
    file_put_contents($DIR . '/' . $nombre . '.meta', json_encode(['registros' => $registros, 'archivos' => $n, 'por' => $yo['email'] ?? '']));
    podar($DIR, $CFG);
    responder(200, ['ok' => true, 'nombre' => $nombre]);
}

if ($accion === 'bajar') {
    $n = nombre_valido($DIR, $_GET['nombre'] ?? '');
    if (!$n) responder(404, ['error' => 'No encontré esa copia']);
    $f = $DIR . '/' . $n;
    $formato = $_GET['formato'] ?? 'json';
    if (substr($n, -4) === '.zip') {
        if ($formato === 'zip') {
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($f));
            header('Content-Disposition: attachment; filename="' . $n . '"');
            readfile($f);
            exit;
        }
        $zip = new ZipArchive();
        if ($zip->open($f) !== true) responder(500, ['error' => 'La copia está dañada']);
        $raw = $zip->getFromName('respaldo.json'); $zip->close();
    } else {
        $raw = gzdecode((string)file_get_contents($f));
    }
    if ($raw === false || $raw === null) responder(500, ['error' => 'La copia está dañada']);
    header('Content-Type: application/json; charset=utf-8');
    echo $raw;
    exit;
}

if ($accion === 'restaurar-archivos' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = nombre_valido($DIR, $_GET['nombre'] ?? '');
    if (!$n) responder(404, ['error' => 'No encontré esa copia']);
    if (substr($n, -4) !== '.zip') responder(200, ['ok' => true, 'fotos' => 0, 'servidor' => 0, 'nota' => 'Esta copia es antigua y no trae fotos']);
    $zip = new ZipArchive();
    if ($zip->open($DIR . '/' . $n) !== true) responder(500, ['error' => 'La copia está dañada']);
    $tipos = json_decode((string)$zip->getFromName('archivos/tipos.json'), true) ?: [];
    $fotos = 0; $servidor = 0; $errores = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $dentro = $zip->getNameIndex($i);
        if (substr($dentro, -1) === '/') continue;
        if (strpos($dentro, 'archivos/storage/') === 0) {
            $resto = substr($dentro, strlen('archivos/storage/'));
            [$bucket, $r] = array_pad(explode('/', $resto, 2), 2, '');
            if (!in_array($bucket, BUCKETS, true) || $r === '' || strpos($r, '..') !== false) continue;
            if (storage_subir($bucket, $r, $zip->getFromIndex($i), $tipos[$bucket . '/' . $r] ?? '', $TOKEN)) $fotos++;
            else $errores[] = $resto;
        } elseif (preg_match('#^archivos/servidor/(orders|subidas)/([^/]+)$#', $dentro, $m)) {
            $nom = basename($m[2]);
            if ($nom === '' || $nom[0] === '.' || preg_match('/\.(php|phtml|phar|htaccess)$/i', $nom)) continue;
            $destino = $m[1] === 'orders' ? __DIR__ . '/orders/' . $nom : dirname(__DIR__) . '/images/subidas/' . $nom;
            if (!is_dir(dirname($destino))) @mkdir(dirname($destino), 0755, true);
            /* un pedido que ya existe no se pisa: podria tener cambios posteriores */
            if (!is_file($destino) && file_put_contents($destino, $zip->getFromIndex($i)) !== false) $servidor++;
        }
    }
    $zip->close();
    responder(200, ['ok' => !$errores, 'fotos' => $fotos, 'servidor' => $servidor, 'errores' => $errores]);
}

responder(400, ['error' => 'Acción no válida']);
