<?php
/**
 * Webhook de WhatsApp Cloud API — Spa Infinity
 * GET: verificación del webhook en Meta.
 * POST: recibe mensajes, consulta al cerebro (bot-reply.php) y responde por WhatsApp.
 * Requiere api/bot-config.php con waToken, waPhoneId, waVerifyToken.
 */

$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? require $cfgFile : [];

/* ── Verificación (Meta llama con GET al configurar el webhook) ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $token = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
    $challenge = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');
    if ($mode === 'subscribe' && $token === ($cfg['waVerifyToken'] ?? '')) {
        header('Content-Type: text/plain'); echo $challenge; exit;
    }
    http_response_code(403); echo 'forbidden'; exit;
}

/* ── Mensaje entrante ── */
$body = json_decode(file_get_contents('php://input'), true);
http_response_code(200); // responder rápido a Meta

$msg = $body['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
if (!$msg || ($msg['type'] ?? '') !== 'text') { echo 'ok'; exit; }

$from = $msg['from'];               // número del cliente
$text = $msg['text']['body'] ?? '';
if (!$text) { echo 'ok'; exit; }

/* ── Sesión de conversación (archivo por número) ── */
$dir = __DIR__ . '/bot-sessions';
@mkdir($dir, 0755, true);
$file = $dir . '/' . preg_replace('/\D/', '', $from) . '.json';
$history = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
$history[] = ['role' => 'user', 'content' => $text];
if (count($history) > 16) $history = array_slice($history, -16);

/* ── Consultar al cerebro ── */
$ch = curl_init('https://spainfinity.cl/api/bot-reply.php');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>45, CURLOPT_POST=>true,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
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
echo 'ok';
