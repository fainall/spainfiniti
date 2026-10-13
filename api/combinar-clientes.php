<?php
/**
 * Combinar dos fichas de cliente duplicadas — Spa Infinity (pedido de Luis)
 *
 *   POST {accion:'previa',   queda, sobra}  → los datos de las dos fichas y cuánto tiene cada una
 *   POST {accion:'combinar', queda, sobra}  → pasa todo lo de "sobra" a "queda" y borra "sobra"
 *
 * Solo administración y recepción. Lo que se pasa: reservas, ventas, fichas
 * clínicas, antes y después, consentimientos, planes, links de pago, insumos y
 * la cuenta del sitio. A la ficha que queda se le completan los datos que le
 * falten (teléfono, correo, RUT…) con los de la otra.
 *
 * Se puede repetir sin riesgo: si algo falla a medio camino, la ficha que sobra
 * NO se borra (solo se borra cuando ya no le queda nada) y basta con volver a
 * combinar. Antes de tocar nada se guarda un respaldo fuera de la carpeta web.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/supa-key.php';
require_once __DIR__ . '/require-auth.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }
function rest($metodo, $ruta, $cuerpo = null, $prefer = 'return=representation') {
    $ch = curl_init(supa_url() . '/rest/v1/' . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(), 'Content-Type: application/json', 'Prefer: ' . $prefer]]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$r, true)];
}

$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? (require $cfgFile) : [];
if (empty($cfg['serviceKey'])) responder(500, ['error' => 'Falta la llave de servicio en el servidor (bot-config.php)']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);

$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'recepcion'], true)) responder(403, ['error' => 'Tu perfil no puede combinar clientes']);

$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d)) responder(400, ['error' => 'Los datos llegaron ilegibles; vuelve a intentarlo']);
$accion = (string)($d['accion'] ?? '');
$queda = strtolower((string)($d['queda'] ?? '')); $sobra = strtolower((string)($d['sobra'] ?? ''));
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
if (!preg_match($uuid, $queda) || !preg_match($uuid, $sobra)) responder(400, ['error' => 'Cliente no válido']);
if ($queda === $sobra) responder(400, ['error' => 'Elige dos fichas distintas']);

/* todo lo que cuelga de un cliente; las que guardan además su nombre lo actualizan */
const TABLAS = [
    'appointments'       => ['Reservas', true],
    'sales'              => ['Ventas', true],
    'client_records'     => ['Fichas clínicas', false],
    'client_comparisons' => ['Antes y después', false],
    'client_consents'    => ['Consentimientos', false],
    'plans'              => ['Planes', true],
    'payment_links'      => ['Links de pago', true],
    'consumos'           => ['Insumos registrados', false],
];
function ficha_cli($id) {
    [$c, $r] = rest('GET', 'clients?select=*&id=eq.' . $id);
    return ($c === 200 && is_array($r) && count($r)) ? $r[0] : null;
}
function cuantos($id) {
    $n = [];
    foreach (TABLAS as $t => $_) {
        [$c, $r] = rest('GET', $t . '?select=client_id&client_id=eq.' . $id);
        if ($c !== 200 || !is_array($r)) responder(500, ['error' => 'No se pudo leer ' . $t]);
        $n[$t] = count($r);
    }
    return $n;
}

$a = ficha_cli($queda); $b = ficha_cli($sobra);
if (!$a || !$b) responder(404, ['error' => 'Una de las dos fichas ya no existe. Actualiza la lista de clientes.']);

if ($accion === 'previa') {
    $rotulos = []; foreach (TABLAS as $t => $x) $rotulos[$t] = $x[0];
    responder(200, ['ok' => true, 'rotulos' => $rotulos, 'queda' => ['cliente' => $a, 'tiene' => cuantos($queda)], 'sobra' => ['cliente' => $b, 'tiene' => cuantos($sobra)]]);
}
if ($accion !== 'combinar') responder(400, ['error' => 'Acción no válida']);

/* dos cuentas del sitio distintas no se pueden fundir en una */
if (!empty($a['user_id']) && !empty($b['user_id']) && $a['user_id'] !== $b['user_id'])
    responder(409, ['error' => 'Cada ficha está ligada a una cuenta distinta del sitio web. Avísale a soporte para revisarlo antes de combinarlas.']);

/* respaldo, fuera de la carpeta pública: la ficha que se va y qué filas tenía */
$respaldo = ['cuando' => date('c'), 'quien' => ($yo['name'] ?? '') . ' <' . ($yo['email'] ?? '') . '>', 'queda' => $a, 'sobra' => $b, 'filas' => []];
foreach (TABLAS as $t => $_) { [$c, $r] = rest('GET', $t . '?select=id&client_id=eq.' . $sobra); $respaldo['filas'][$t] = is_array($r) ? array_column($r, 'id') : []; }
$dir = dirname(__DIR__, 2) . '/respaldos-combinar';
if (!is_dir($dir)) @mkdir($dir, 0700, true);
@file_put_contents($dir . '/' . date('Ymd-His') . '-' . substr($sobra, 0, 8) . '.json', json_encode($respaldo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* 1. todo lo de la ficha que sobra pasa a la que queda */
$movidos = [];
foreach (TABLAS as $t => [$rotulo, $llevaNombre]) {
    $n = count($respaldo['filas'][$t]);
    if (!$n) continue;
    $cambio = ['client_id' => $queda]; if ($llevaNombre) $cambio['client_name'] = $a['name'];
    [$c, $r] = rest('PATCH', $t . '?client_id=eq.' . $sobra, $cambio, 'return=minimal');
    if ($c >= 300) responder(500, ['error' => 'No se pudo pasar: ' . $rotulo . '. No se borró nada; vuelve a intentarlo.', 'movidos' => $movidos]);
    $movidos[$rotulo] = $n;
}
/* avisos de "posible misma persona" que estaban en la ficha que sobra */
[$c, $vp] = rest('GET', 'vinculos_pendientes?select=user_id&client_id=eq.' . $sobra);
foreach ((is_array($vp) ? $vp : []) as $v) {
    [$c2] = rest('PATCH', 'vinculos_pendientes?client_id=eq.' . $sobra . '&user_id=eq.' . $v['user_id'], ['client_id' => $queda], 'return=minimal');
    if ($c2 >= 300) rest('DELETE', 'vinculos_pendientes?client_id=eq.' . $sobra . '&user_id=eq.' . $v['user_id'], null, 'return=minimal');
}

/* 2. a la ficha que queda se le completa lo que le falte */
$vacio = fn($v) => $v === null || trim((string)$v) === '';
$completa = [];
foreach (['phone', 'email', 'rut', 'gender', 'birthday'] as $k) if ($vacio($a[$k] ?? null) && !$vacio($b[$k] ?? null)) $completa[$k] = $b[$k];
$na = trim((string)($a['notes'] ?? '')); $nb = trim((string)($b['notes'] ?? ''));
if ($nb !== '' && stripos($nb, 'Creado por el asistente de WhatsApp') !== 0 && stripos($na, $nb) === false) $completa['notes'] = trim($na . ($na !== '' ? "\n" : '') . $nb);
if ($vacio($a['user_id'] ?? null) && !$vacio($b['user_id'] ?? null)) {
    rest('PATCH', 'clients?id=eq.' . $sobra, ['user_id' => null], 'return=minimal');
    $completa['user_id'] = $b['user_id'];
}
if ($completa) {
    [$c] = rest('PATCH', 'clients?id=eq.' . $queda, $completa, 'return=minimal');
    if ($c >= 300) responder(500, ['error' => 'Se pasó el historial, pero no se pudieron completar los datos de la ficha. No se borró nada; vuelve a intentarlo.']);
}

/* 3. la ficha que sobra se borra SOLO si ya no le queda nada (sus fichas clínicas se irían con ella) */
$restan = array_sum(cuantos($sobra));
if ($restan > 0) responder(500, ['error' => 'Quedaron ' . $restan . ' registros sin pasar. No se borró nada; vuelve a intentarlo.']);
[$c] = rest('DELETE', 'clients?id=eq.' . $sobra, null, 'return=minimal');
if ($c >= 300 || ficha_cli($sobra)) responder(500, ['error' => 'Todo quedó en una sola ficha, pero no se pudo borrar la duplicada. Bórrala desde la lista de clientes.']);

unset($completa['user_id']);
responder(200, ['ok' => true, 'movidos' => $movidos, 'completados' => array_keys($completa)]);
