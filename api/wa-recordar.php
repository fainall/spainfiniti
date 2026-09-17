<?php
/**
 * Enviar recordatorios con Mariet desde el panel — Spa Infinity
 *   POST {ids: [uuid, ...]}   (máximo 60 por vez)
 * Devuelve el resultado de cada cita: enviado como mensaje o como plantilla, o el error.
 */
require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/wa-recordatorio-lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('America/Santiago');
@set_time_limit(180);

$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'recepcion'], true)) {
    http_response_code(403); echo json_encode(['error' => 'Solo administración y recepción envían recordatorios']); exit;
}
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$ids = array_values(array_unique(array_filter(array_map('strval', (array)($in['ids'] ?? [])), fn($x) => preg_match('/^[0-9a-f-]{36}$/', $x))));
if (!$ids) { http_response_code(400); echo json_encode(['error' => 'No hay citas para recordar']); exit; }
if (count($ids) > 60) { http_response_code(400); echo json_encode(['error' => 'Máximo 60 recordatorios por vez']); exit; }

$cfg = require __DIR__ . '/bot-config.php';
function supa_rec($m, $p, $b = null) {
    $ch = curl_init(supa_url() . '/rest/v1/' . $p);
    $h = ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(), 'Content-Type: application/json'];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $m, CURLOPT_HTTPHEADER => $h, CURLOPT_TIMEOUT => 15]);
    if ($b !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($b));
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string)$r, true);
}
$bot = supa_rec('GET', 'bot_config?id=eq.1&select=local_info')[0] ?? [];
$direccion = trim((string)($bot['local_info']['address'] ?? ($cfg['address'] ?? 'Santo Domingo 1083, Of. 502, Santiago Centro')));

$citas = supa_rec('GET', 'appointments?select=id,client_name,client_phone,service_name,appt_date,start_time,status&id=in.(' . implode(',', $ids) . ')') ?: [];
$res = [];
foreach ($citas as $a) {
    if (in_array($a['status'] ?? '', ['cancelled', 'block', 'attending', 'no_show'], true)) { $res[$a['id']] = ['ok' => false, 'error' => 'La cita no está pendiente']; continue; }
    $r = wa_enviar_recordatorio($cfg, $a, $direccion);
    if ($r['ok']) supa_rec('PATCH', 'appointments?id=eq.' . urlencode($a['id']), ['reminded_at' => date('c'), 'reminded_channel' => 'whatsapp']);
    $res[$a['id']] = $r;
}
echo json_encode(['resultados' => $res,
                  'enviados' => count(array_filter($res, fn($r) => $r['ok'])),
                  'como_mensaje' => count(array_filter($res, fn($r) => $r['ok'] && ($r['modo'] ?? '') === 'texto')),
                  'con_plantilla' => count(array_filter($res, fn($r) => $r['ok'] && ($r['modo'] ?? '') === 'plantilla'))], JSON_UNESCAPED_UNICODE);
