<?php
/**
 * Combinar dos fichas de cliente duplicadas — Spa Infinity (pedido de Luis)
 *
 *   POST {accion:'previa',   queda, sobra}  → los datos de las dos fichas y cuánto tiene cada una
 *   POST {accion:'combinar', queda, sobra}  → pasa todo lo de "sobra" a "queda" y borra "sobra"
 *   POST {accion:'auto', simular:true|false} → las fichas que son la misma persona con seguridad
 *        (mismo teléfono y mismo nombre): con simular las lista; sin él las combina.
 *
 * Solo administración y recepción. La lógica está en combinar-lib.php, que
 * también usa Mariet para combinar sola cuando encuentra el mismo teléfono y
 * nombre en dos fichas.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/combinar-lib.php';
require_once __DIR__ . '/require-auth.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? (require $cfgFile) : [];
if (empty($cfg['serviceKey'])) responder(500, ['error' => 'Falta la llave de servicio en el servidor (bot-config.php)']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);

$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'recepcion'], true)) responder(403, ['error' => 'Tu perfil no puede combinar clientes']);
$quien = ($yo['name'] ?? '') . ' <' . ($yo['email'] ?? '') . '>';

$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d)) responder(400, ['error' => 'Los datos llegaron ilegibles; vuelve a intentarlo']);
$accion = (string)($d['accion'] ?? '');

/* ── las que son la misma persona con seguridad, todas de una vez ── */
if ($accion === 'auto') {
    $todas = [];
    for ($o = 0; $o < 20000; $o += 1000) {
        [$c, $p] = cmb_rest('GET', 'clients?select=id,name,phone,email,rut,user_id,created_at&order=id&limit=1000&offset=' . $o);
        if ($c !== 200 || !is_array($p)) responder(500, ['error' => 'No se pudo leer la lista de clientes']);
        $todas = array_merge($todas, $p);
        if (count($p) < 1000) break;
    }
    $parejas = cmb_parejas_seguras($todas);
    $ver = fn($f) => ['id' => $f['id'], 'nombre' => $f['name'], 'telefono' => $f['phone'], 'correo' => $f['email']];
    if (!empty($d['simular'])) responder(200, ['ok' => true, 'parejas' => array_map(fn($p) => [$ver($p[0]), $ver($p[1])], $parejas)]);
    $hechas = []; $fallas = [];
    foreach ($parejas as [$a, $b]) {
        $r = cmb_combinar_pareja($a, $b, $quien . ' (automático)');
        if (!empty($r['ok'])) $hechas[] = $r['nombre']; else $fallas[] = ($a['name'] ?? '') . ': ' . ($r['error'] ?? 'error');
    }
    responder(200, ['ok' => true, 'combinadas' => $hechas, 'fallas' => $fallas]);
}

$queda = strtolower((string)($d['queda'] ?? '')); $sobra = strtolower((string)($d['sobra'] ?? ''));
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';
if (!preg_match($uuid, $queda) || !preg_match($uuid, $sobra)) responder(400, ['error' => 'Cliente no válido']);
if ($queda === $sobra) responder(400, ['error' => 'Elige dos fichas distintas']);

if ($accion === 'previa') {
    $a = cmb_ficha($queda); $b = cmb_ficha($sobra);
    if (!$a || !$b) responder(404, ['error' => 'Una de las dos fichas ya no existe. Actualiza la lista de clientes.']);
    $ca = cmb_cuantos($queda); $cb = cmb_cuantos($sobra);
    if ($ca === null || $cb === null) responder(500, ['error' => 'No se pudo leer el historial de las fichas']);
    $rotulos = []; foreach (CMB_TABLAS as $t => $x) $rotulos[$t] = $x[0];
    responder(200, ['ok' => true, 'rotulos' => $rotulos, 'queda' => ['cliente' => $a, 'tiene' => $ca], 'sobra' => ['cliente' => $b, 'tiene' => $cb]]);
}
if ($accion !== 'combinar') responder(400, ['error' => 'Acción no válida']);

$r = cmb_combinar($queda, $sobra, $quien);
if (empty($r['ok'])) responder($r['codigo'] ?? 500, ['error' => $r['error'] ?? 'No se pudo combinar']);
responder(200, ['ok' => true, 'movidos' => $r['movidos'], 'completados' => $r['completados']]);
