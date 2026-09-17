<?php
/**
 * Bandeja de WhatsApp del panel — Spa Infinity
 *
 *   GET                      lista de chats (último mensaje, si la IA está en pausa)
 *   GET ?tel=569...          mensajes de un chat
 *   POST {tel, texto}        el equipo responde desde el panel: se envía por
 *                            WhatsApp, queda en el chat y la IA se pausa en ese chat
 *
 * Solo administración y recepción.
 */
require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/wa-pausas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('America/Santiago');

$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'recepcion'], true)) {
    http_response_code(403); echo json_encode(['error' => 'Solo administración y recepción ven los chats de WhatsApp']); exit;
}
$DIR = __DIR__ . '/bot-sessions';

function estado_pausa($tel) {
    $p = wa_control_leer()['pausas'][wa_tel($tel)] ?? null;
    if (!$p || (($p['hasta'] ?? 0) !== 0 && $p['hasta'] < time())) return null;
    return ['hasta' => $p['hasta'] ? date('c', $p['hasta']) : null, 'motivo' => $p['motivo'] ?? ''];
}

/* chats anteriores a la bandeja: solo tenian el contexto de la IA, sin horas */
function importar_antiguo($DIR, $tel) {
    if (is_file(wa_chat_archivo($tel))) return;
    $f = $DIR . '/' . $tel . '.json';
    $h = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (!is_array($h) || !$h) return;
    $t = filemtime($f) - count($h);
    $lista = [];
    foreach ($h as $i => $m) {
        $lista[] = ['t' => $t + $i, 'quien' => ($m['role'] ?? '') === 'user' ? 'cliente' : 'asistente', 'texto' => (string)($m['content'] ?? ''), 'antiguo' => true];
    }
    @file_put_contents(wa_chat_archivo($tel), json_encode($lista, JSON_UNESCAPED_UNICODE));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tel = wa_tel($_GET['tel'] ?? '');
    if ($tel !== '') {
        importar_antiguo($DIR, $tel);
        echo json_encode(['tel' => $tel, 'mensajes' => wa_chat_leer($tel), 'pausa' => estado_pausa($tel),
                          'prueba' => wa_es_prueba($tel)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    foreach (glob($DIR . '/*.json') ?: [] as $f) {
        if (preg_match('/^(\d{8,15})\.json$/', basename($f), $m)) importar_antiguo($DIR, $m[1]);
    }
    $chats = [];
    foreach (glob($DIR . '/chat-*.json') ?: [] as $f) {
        if (!preg_match('/^chat-(\d{8,15})\.json$/', basename($f), $m)) continue;
        $l = json_decode((string)file_get_contents($f), true);
        if (!is_array($l) || !$l) continue;
        $ult = end($l);
        $nombre = '';
        foreach (array_reverse($l) as $x) if (!empty($x['nombre'])) { $nombre = $x['nombre']; break; }
        $esperando = ($ult['quien'] ?? '') === 'cliente';
        $chats[] = ['tel' => $m[1], 'nombre' => $nombre, 't' => (int)($ult['t'] ?? filemtime($f)),
                    'ultimo' => mb_substr((string)($ult['texto'] ?? ''), 0, 120), 'quien' => $ult['quien'] ?? '',
                    'esperando' => $esperando, 'pausa' => estado_pausa($m[1]), 'prueba' => wa_es_prueba($m[1])];
    }
    usort($chats, fn($a, $b) => $b['t'] <=> $a['t']);
    echo json_encode(['chats' => $chats], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ── responder desde el panel ── */
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$tel = wa_tel($in['tel'] ?? '');
$texto = trim((string)($in['texto'] ?? ''));
if (strlen($tel) < 8 || $texto === '') { http_response_code(400); echo json_encode(['error' => 'Falta el mensaje']); exit; }
if (mb_strlen($texto) > 4000) { http_response_code(400); echo json_encode(['error' => 'El mensaje es demasiado largo']); exit; }

$cfg = require __DIR__ . '/bot-config.php';
if (empty($cfg['waToken']) || empty($cfg['waPhoneId'])) { http_response_code(503); echo json_encode(['error' => 'WhatsApp no está conectado']); exit; }

$ch = curl_init('https://graph.facebook.com/v23.0/' . $cfg['waPhoneId'] . '/messages');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['waToken'], 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'to' => $tel, 'type' => 'text', 'text' => ['body' => $texto]])]);
$res = json_decode((string)curl_exec($ch), true);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code < 200 || $code >= 300) {
    $err = $res['error'] ?? [];
    $cod = (int)($err['code'] ?? 0);
    $msg = in_array($cod, [131047, 131026], true)
        ? 'Pasaron más de 24 horas desde el último mensaje del cliente: WhatsApp solo deja escribirle con una plantilla aprobada. Respóndele desde el celular o espera a que vuelva a escribir.'
        : 'WhatsApp no aceptó el mensaje: ' . ($err['error_user_msg'] ?? $err['message'] ?? ('error ' . $code));
    http_response_code(502); echo json_encode(['error' => $msg]); exit;
}

$autor = trim((string)($yo['name'] ?? '')) ?: (string)($yo['email'] ?? 'Equipo');
wa_log($tel, 'panel', $texto, ['autor' => $autor, 'id' => (string)($res['messages'][0]['id'] ?? '')]);
wa_contexto_agregar($tel, 'assistant', $texto);
/* quien interviene toma el chat: la IA se calla ahi */
$min = wa_control_leer()['pausaMin'];
wa_pausar($tel, $min > 0 ? $min : 120, 'Respondió ' . $autor . ' desde el panel');

echo json_encode(['ok' => true, 'mensajes' => wa_chat_leer($tel), 'pausa' => estado_pausa($tel)], JSON_UNESCAPED_UNICODE);
