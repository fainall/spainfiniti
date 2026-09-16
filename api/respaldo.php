<?php
/**
 * Copias de seguridad guardadas en el servidor — Spa Infinity
 *
 * El panel arma la copia con la sesion de un administrador (asi puede leer
 * fichas, consentimientos y todo lo que la base protege) y la manda aqui.
 * Se guardan comprimidas FUERA de public_html: no hay forma de bajarlas
 * desde internet sin pasar por este archivo, que exige ser administrador.
 *
 *   GET  ?accion=lista                 copias guardadas y configuracion
 *   POST ?accion=guardar&tipo=auto     guarda la copia (cuerpo: el JSON)
 *   POST ?accion=config                { cadaDias, guardar }
 *   GET  ?accion=bajar&nombre=...      devuelve la copia (JSON)
 */
require_once __DIR__ . '/require-auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('America/Santiago');

$yo = require_panel_user(true);

/* /home/usuario/respaldos-spainfinity, al lado de domains/, fuera de la web */
function dir_respaldos() {
    $dir = dirname(__DIR__, 4) . '/respaldos-spainfinity';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        /* respaldo: dentro de api/cache, cerrado a la web */
        $dir = __DIR__ . '/cache/respaldos';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return $dir;
}
function responder($code, $data) { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }

$DIR = dir_respaldos();
$CFG = $DIR . '/config.json';
function leer_config($CFG) {
    $c = is_file($CFG) ? json_decode((string)file_get_contents($CFG), true) : null;
    return array_merge(['cadaDias' => 30, 'guardar' => 6, 'ultimo' => null], is_array($c) ? $c : []);
}
function lista_copias($DIR) {
    $out = [];
    foreach (glob($DIR . '/respaldo-*.json.gz') ?: [] as $f) {
        $n = basename($f);
        preg_match('/^respaldo-(\d{4}-\d{2}-\d{2})-(\d{6})-([a-z-]+)\.json\.gz$/', $n, $m);
        $meta = is_file($f . '.meta') ? json_decode((string)file_get_contents($f . '.meta'), true) : [];
        $out[] = [
            'nombre'    => $n,
            'fecha'     => $m ? ($m[1] . ' ' . substr($m[2],0,2) . ':' . substr($m[2],2,2)) : date('Y-m-d H:i', filemtime($f)),
            'tipo'      => $m[3] ?? 'manual',
            'bytes'     => filesize($f),
            'registros' => $meta['registros'] ?? null,
            'por'       => $meta['por'] ?? '',
        ];
    }
    usort($out, fn($a, $b) => strcmp($b['nombre'], $a['nombre']));
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

if ($accion === 'guardar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = preg_replace('/[^a-z-]/', '', (string)($_GET['tipo'] ?? 'manual')) ?: 'manual';
    if (!in_array($tipo, ['auto', 'manual', 'antes-de-restaurar'], true)) $tipo = 'manual';
    $raw = file_get_contents('php://input');
    $j = json_decode($raw, true);
    if (!is_array($j) || !isset($j['tablas']) || !is_array($j['tablas'])) responder(400, ['error' => 'La copia no tiene el formato esperado']);
    $registros = 0;
    foreach ($j['tablas'] as $filas) if (is_array($filas)) $registros += count($filas);

    $nombre = 'respaldo-' . date('Y-m-d-His') . '-' . $tipo . '.json.gz';
    if (file_put_contents($DIR . '/' . $nombre, gzencode($raw, 6)) === false) responder(500, ['error' => 'No se pudo guardar la copia en el servidor']);
    @chmod($DIR . '/' . $nombre, 0600);
    file_put_contents($DIR . '/' . $nombre . '.meta', json_encode(['registros' => $registros, 'por' => $yo['email'] ?? '']));

    $c = leer_config($CFG);
    if ($tipo !== 'antes-de-restaurar') { $c['ultimo'] = date('Y-m-d'); file_put_contents($CFG, json_encode($c)); }

    /* se conservan las ultimas N copias normales y las ultimas 3 de antes de restaurar */
    $copias = lista_copias($DIR);
    $normales = array_values(array_filter($copias, fn($x) => $x['tipo'] !== 'antes-de-restaurar'));
    $previas  = array_values(array_filter($copias, fn($x) => $x['tipo'] === 'antes-de-restaurar'));
    foreach (array_slice($normales, (int)$c['guardar']) as $x) { @unlink($DIR . '/' . $x['nombre']); @unlink($DIR . '/' . $x['nombre'] . '.meta'); }
    foreach (array_slice($previas, 3) as $x)                   { @unlink($DIR . '/' . $x['nombre']); @unlink($DIR . '/' . $x['nombre'] . '.meta'); }

    responder(200, ['ok' => true, 'nombre' => $nombre, 'registros' => $registros]);
}

if ($accion === 'bajar') {
    $n = basename((string)($_GET['nombre'] ?? ''));
    if (!preg_match('/^respaldo-[0-9a-z-]+\.json\.gz$/', $n) || !is_file($DIR . '/' . $n)) responder(404, ['error' => 'No encontré esa copia']);
    $raw = gzdecode((string)file_get_contents($DIR . '/' . $n));
    if ($raw === false) responder(500, ['error' => 'La copia está dañada']);
    echo $raw;
    exit;
}

responder(400, ['error' => 'Acción no válida']);
