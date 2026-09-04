<?php
/**
 * Webhook de WhatsApp Cloud API — Spa Infinity
 * GET: verificación del webhook en Meta.
 * POST: recibe mensajes, consulta al cerebro (bot-reply.php) y responde por WhatsApp.
 * Requiere api/bot-config.php con waToken, waPhoneId, waVerifyToken y waAppSecret.
 *
 * Seguridad: cada POST se comprueba contra la firma que manda Meta
 * (X-Hub-Signature-256, HMAC con el App Secret). Sin eso, cualquiera podía
 * enviar un JSON con el formato de Meta y hacer que el número del spa
 * escribiera a quien quisiera, y crear reservas a nombre de ese número.
 */

$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? require $cfgFile : [];

/* ── Verificación (Meta llama con GET al configurar el webhook) ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
    $challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');
    if ($mode === 'subscribe' && $token !== '' && hash_equals((string)($cfg['waVerifyToken'] ?? ''), $token)) {
        header('Content-Type: text/plain'); echo $challenge; exit;
    }
    http_response_code(403); echo 'forbidden'; exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('method'); }

/* ── Firma de Meta: sin App Secret configurado no se acepta nada ── */
$raw = file_get_contents('php://input');
$secret = (string)($cfg['waAppSecret'] ?? '');
if ($secret === '') {
    error_log('wa-webhook: falta waAppSecret en bot-config.php; se rechaza el mensaje');
    http_response_code(503); exit('not configured');
}
$firma = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
if (!hash_equals('sha256=' . hash_hmac('sha256', $raw, $secret), $firma)) {
    http_response_code(403); exit('bad signature');
}

/* ── ¿Está encendido el asistente? ──
   La casilla "Activo" del panel se guardaba pero no la miraba nadie: el bot
   habria seguido respondiendo aunque se apagara. Se consulta aqui, no en
   bot-reply.php, para que el simulador del panel siga sirviendo para probar
   aunque el asistente este apagado de cara a los clientes. */
require_once __DIR__ . '/supa-key.php';
$ch = curl_init(supa_url() . '/rest/v1/bot_config?select=active&id=eq.1');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8,
    CURLOPT_HTTPHEADER=>['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key()]]);
$cfgBot = json_decode(curl_exec($ch), true); curl_close($ch);
$encendido = is_array($cfgBot) && count($cfgBot) ? ($cfgBot[0]['active'] ?? false) : false;
if (!$encendido) { http_response_code(200); exit('ok'); }   // apagado: no se contesta

/* ── Mensaje entrante ── */
$body = json_decode($raw, true);
$msg = $body['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
if (!$msg || ($msg['type'] ?? '') !== 'text') { echo 'ok'; exit; }

$from  = preg_replace('/\D/', '', (string)($msg['from'] ?? ''));
$text  = (string)($msg['text']['body'] ?? '');
$msgId = (string)($msg['id'] ?? '');
if ($from === '' || $text === '') { echo 'ok'; exit; }

/* ── Meta reintenta si tarda la respuesta: se contesta ya y se sigue trabajando ── */
$dir = __DIR__ . '/bot-sessions';
@mkdir($dir, 0755, true);

/* el mismo mensaje no se procesa dos veces */
$idsFile = $dir . '/_procesados.json';
$ids = file_exists($idsFile) ? (json_decode(file_get_contents($idsFile), true) ?: []) : [];
if ($msgId !== '' && in_array($msgId, $ids, true)) { echo 'ok'; exit; }
if ($msgId !== '') { $ids[] = $msgId; if (count($ids) > 500) $ids = array_slice($ids, -500); @file_put_contents($idsFile, json_encode($ids)); }

http_response_code(200);
echo 'ok';
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
else { @ob_end_flush(); @flush(); }

/* ── Sesión de conversación (archivo por número) ── */
$file = $dir . '/' . $from . '.json';
$history = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
$history[] = ['role' => 'user', 'content' => mb_substr($text, 0, 2000)];
if (count($history) > 16) $history = array_slice($history, -16);

/* ── Consultar al cerebro (con la clave interna que lo distingue de un extraño) ── */
$internalKey = hash('sha256', (string)($cfg['openaiKey'] ?? '') . '|spa-internal');
$ch = curl_init('https://spainfinity.cl/api/bot-reply.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>45, CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'X-Internal-Key: ' . $internalKey],
    CURLOPT_POSTFIELDS=>json_encode(['messages'=>$history, 'phone'=>$from])]);
$brain = json_decode(curl_exec($ch), true); curl_close($ch);
$reply = $brain['reply'] ?? 'Disculpa, no pude procesar tu mensaje. Escríbenos y te ayudamos 🙏';

$history[] = ['role' => 'assistant', 'content' => $reply];
@file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE));

/* ── Enviar respuesta por WhatsApp ── */
if (!empty($cfg['waToken']) && !empty($cfg['waPhoneId'])) {
    $url = 'https://graph.facebook.com/v20.0/' . $cfg['waPhoneId'] . '/messages';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['waToken'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp','to'=>$from,'type'=>'text','text'=>['body'=>$reply]])]);
    curl_exec($ch); curl_close($ch);
}
