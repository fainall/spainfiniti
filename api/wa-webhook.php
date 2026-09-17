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

function wa_enviar_texto($cfg, $to, $texto) {
    if (empty($cfg['waToken']) || empty($cfg['waPhoneId'])) return false;
    $ch = curl_init('https://graph.facebook.com/v23.0/' . $cfg['waPhoneId'] . '/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['waToken'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp','to'=>$to,'type'=>'text','text'=>['body'=>$texto]])]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code < 200 || $code >= 300) error_log('wa-webhook: envio fallo ' . $code . ' ' . substr((string)$r, 0, 300));
    return $code >= 200 && $code < 300;
}
function supa_escribir_cita($id, $estado) {
    require_once __DIR__ . '/supa-key.php';
    $ch = curl_init(supa_url() . '/rest/v1/appointments?id=eq.' . urlencode($id) . '&status=neq.cancelled');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>'PATCH', CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['apikey: '.supa_key(), 'Authorization: Bearer '.supa_key(), 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['status'=>$estado])]);
    curl_exec($ch); curl_close($ch);
}

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

/* ── Luis respondio desde la app del celular: el asistente se calla en ese chat ──
   Con el numero en coexistencia, cada mensaje que el equipo envia desde la app
   WhatsApp Business llega aqui como "smb_message_echoes". */
require_once __DIR__ . '/wa-pausas.php';
$cambio = json_decode($raw, true)['entry'][0]['changes'][0] ?? [];
/* registro breve de lo que llega (sin textos de mensajes), para diagnosticar */
(function($raw){
    $j = json_decode($raw, true);
    $lineas = [];
    foreach ((array)($j['entry'] ?? []) as $e) foreach ((array)($e['changes'] ?? []) as $ch) {
        $v = $ch['value'] ?? [];
        $ult = fn($t) => $t ? '…' . substr(preg_replace('/[^0-9]/', '', (string)$t), -4) : '-';
        $det = [];
        foreach ((array)($v['messages'] ?? []) as $m) $det[] = 'msg de ' . $ult($m['from'] ?? '') . ' tipo ' . ($m['type'] ?? '?');
        foreach ((array)($v['message_echoes'] ?? []) as $m) $det[] = 'eco a ' . $ult($m['to'] ?? '') . ' tipo ' . ($m['type'] ?? '?');
        foreach ((array)($v['statuses'] ?? []) as $m) $det[] = 'estado ' . ($m['status'] ?? '?') . ' a ' . $ult($m['recipient_id'] ?? '');
        $lineas[] = date('Y-m-d H:i:s') . ' campo=' . ($ch['field'] ?? '?') . ' claves=' . implode(',', array_keys($v)) . ' ' . implode(' | ', $det);
    }
    $f = __DIR__ . '/bot-sessions/_eventos.log';
    $prev = is_file($f) ? array_slice(file($f, FILE_IGNORE_NEW_LINES), -300) : [];
    @file_put_contents($f, implode("
", array_merge($prev, $lineas)) . "
");
})($raw);
if (($cambio['field'] ?? '') === 'smb_message_echoes') {
    $minutos = wa_control_leer()['pausaMin'];
    if ($minutos > 0) {
        foreach ((array)($cambio['value']['message_echoes'] ?? []) as $eco) {
            if (!empty($eco['to'])) wa_pausar($eco['to'], $minutos, 'Respondieron desde el celular');
        }
    }
    foreach ((array)($cambio['value']['message_echoes'] ?? []) as $eco) {
        if (empty($eco['to'])) continue;
        $txtEco = wa_texto_de($eco);
        $extraEco = ['id' => (string)($eco['id'] ?? '')];
        if (in_array(($eco['type'] ?? ''), ['image', 'audio', 'voice', 'sticker', 'document', 'video'], true)) {
            require_once __DIR__ . '/wa-media.php';
            $tipoEco = (string)$eco['type'];
            $bajEco = wa_media_bajar($cfg, (string)($eco[$tipoEco]['id'] ?? ''));
            if ($bajEco) {
                $arch = wa_media_guardar($eco['to'], (string)($eco[$tipoEco]['id'] ?? ''), $bajEco['bytes'], $bajEco['mime']);
                $extraEco['media'] = ['tipo' => $tipoEco, 'archivo' => $arch, 'mime' => explode(';', (string)$bajEco['mime'])[0]];
            }
        }
        wa_log($eco['to'], 'equipo', $txtEco, $extraEco);
        if (($eco['type'] ?? '') === 'text') { wa_contexto_agregar($eco['to'], 'assistant', $txtEco); wa_aprender($eco['to'], $txtEco, 'Celular'); }
    }
    http_response_code(200); exit('ok');
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
/* apagado: no se contesta, salvo a los numeros de prueba (se revisa mas abajo, con el numero) */

/* ── Mensaje entrante ── */
$body = json_decode($raw, true);
$msg = $body['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
if (!$msg) { echo 'ok'; exit; }

$from  = preg_replace('/\D/', '', (string)($msg['from'] ?? ''));
$text  = (string)($msg['text']['body'] ?? '');
$msgId = (string)($msg['id'] ?? '');
if ($from === '') { echo 'ok'; exit; }

/* el mismo mensaje no se procesa dos veces (Meta reintenta) */
$idsFile = __DIR__ . '/bot-sessions/_procesados.json';
@mkdir(dirname($idsFile), 0755, true);
$idsPrev = file_exists($idsFile) ? (json_decode(file_get_contents($idsFile), true) ?: []) : [];
if ($msgId !== '' && in_array($msgId, $idsPrev, true)) { echo 'ok'; exit; }
/* se marca antes de trabajar: bajar una foto y transcribir un audio toma unos
   segundos y Meta reintenta el mismo mensaje si tardamos */
if ($msgId !== '') { $idsPrev[] = $msgId; @file_put_contents($idsFile, json_encode(array_slice($idsPrev, -500))); }

/* ── Fotos y audios: se convierten a texto ──
   Antes llegaban como "📷 Imagen" y Mariet no entendia nada. Ahora el audio se
   transcribe y la foto se describe, y el resto sigue igual que con un texto. */
$extraLog = ['id' => $msgId];
$tipoMsg = (string)($msg['type'] ?? '');
if (in_array($tipoMsg, ['image', 'audio', 'voice', 'sticker', 'document', 'video'], true)) {
    require_once __DIR__ . '/wa-media.php';
    $leido = wa_mensaje_a_texto($cfg, $from, $msg);
    if (is_array($leido) && trim((string)$leido['texto']) !== '') {
        $text = $leido['texto'];
        $extraLog = array_merge($extraLog, $leido['extra']);
    }
}

/* todo lo que escribe el cliente queda en su chat, se responda o no */
$nombrePerfil = (string)($body['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? '');
$extraLog['nombre'] = $nombrePerfil;
wa_log($from, 'cliente', $text !== '' ? $text : wa_texto_de($msg), $extraLog);
/* ── Botones del recordatorio: Confirmo / Cancelar ──
   Se resuelven aqui mismo, sin depender de la IA: la cita cambia de estado en
   la agenda y el cliente recibe la respuesta. Si cancela, Mariet sigue la
   conversacion para ofrecerle otra hora. */
$botonTxt = '';
if (($msg['type'] ?? '') === 'button') $botonTxt = (string)($msg['button']['text'] ?? $msg['button']['payload'] ?? '');
if (($msg['type'] ?? '') === 'interactive') $botonTxt = (string)($msg['interactive']['button_reply']['title'] ?? '');
$botonClave = strtolower(trim(strtr($botonTxt, ['Á'=>'a','á'=>'a'])));
/* tambien si lo escribe (el recordatorio de texto de Mariet pide responder asi), dentro de 48 h */
if ($botonClave === '' && ($msg['type'] ?? '') === 'text') {
    $escrito = trim(preg_replace('/[^a-z ]/', '', strtolower(strtr($text, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'a','É'=>'e']))));
    $recReciente = wa_recordatorio_de($from);
    if ($recReciente && $recReciente['t'] > time() - 48 * 3600) {
        if (in_array($escrito, ['confirmo', 'si confirmo', 'confirmado', 'confirmo mi hora', 'confirmo mi cita', 'si voy', 'confirmo asistencia'], true)) $botonClave = 'confirmo';
        if (in_array($escrito, ['cancelar', 'cancelo', 'quiero cancelar', 'no voy', 'anular'], true)) $botonClave = 'cancelar';
        /* "si", "ok", "dale" solo cuentan si es la respuesta inmediata al recordatorio */
        $previo = wa_chat_leer($from); array_pop($previo); $previo = end($previo);
        if ($botonClave === '' && $previo && !empty($previo['recordatorio'])
            && in_array($escrito, ['si', 'ok', 'dale', 'ya', 'perfecto', 'ahi estare', 'alli estare', 'si ahi estare', 'si gracias', 'vale'], true)) $botonClave = 'confirmo';
    }
}
if ($botonClave === 'confirmo' || $botonClave === 'cancelar') {
    $rec = wa_recordatorio_de($from, (string)($msg['context']['id'] ?? ''));
    if ($rec) {
        $confirma = $botonClave === 'confirmo';
        supa_escribir_cita($rec['appt'], $confirma ? 'confirmed' : 'cancelled');
        $respuesta = $confirma
            ? "¡Listo, confirmada! Te esperamos el {$rec['fecha']} a las {$rec['hora']} ✨"
            : "Listo, anulé tu hora de {$rec['servicio']} del {$rec['fecha']} a las {$rec['hora']}. ¿Quieres que te busque otro día y hora? 😊";
        wa_contexto_agregar($from, 'user', $confirma ? 'Confirmo mi cita' : 'Quiero cancelar mi cita');
        wa_contexto_agregar($from, 'assistant', $respuesta);
        if ($encendido || wa_es_prueba($from)) {
            $okEnvio = wa_enviar_texto($cfg, $from, $respuesta);
            wa_log($from, 'asistente', $respuesta, $okEnvio ? [] : ['error' => 'No se pudo enviar']);
        }
    }
    echo 'ok'; exit;
}
if ($text === '') { echo 'ok'; exit; }

/* ── ¿Contesto con nota de voz? ──
   Solo si el cliente lo pidió. Desde ahí se le sigue hablando en audio en ese
   chat, hasta que pida texto o pase un día sin escribir. Se anota aquí, antes
   de mirar las pausas: si lo pidió mientras el equipo atendía el chat, cuando
   la IA vuelva ya sabe que le tiene que hablar. */
require_once __DIR__ . '/wa-voz.php';
$conVoz = wa_voz_activa($from);
if (wa_pide_audio($text)) { wa_voz_marcar($from, true); $conVoz = true; }
elseif (wa_pide_texto($text)) { wa_voz_marcar($from, false); $conVoz = false; }

if (!$encendido && !wa_es_prueba($from)) { http_response_code(200); exit('ok'); }

/* chat en pausa (alguien del equipo lo esta atendiendo): se guarda lo que escribio
   el cliente, para que el asistente tenga el contexto cuando vuelva, pero no responde */
if (wa_en_pausa($from)) {
    $dirP = __DIR__ . '/bot-sessions';
    @mkdir($dirP, 0755, true);
    $fileP = $dirP . '/' . $from . '.json';
    $histP = file_exists($fileP) ? (json_decode(file_get_contents($fileP), true) ?: []) : [];
    $histP[] = ['role' => 'user', 'content' => mb_substr($text, 0, 2000)];
    if (count($histP) > 16) $histP = array_slice($histP, -16);
    @file_put_contents($fileP, json_encode($histP, JSON_UNESCAPED_UNICODE));
    http_response_code(200); exit('ok');
}

/* ── Meta reintenta si tarda la respuesta: se contesta ya y se sigue trabajando ── */
$dir = __DIR__ . '/bot-sessions';
@mkdir($dir, 0755, true);

/* (los mensajes repetidos ya se descartaron al recibirlos) */

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
    CURLOPT_POSTFIELDS=>json_encode(['messages'=>$history, 'phone'=>$from, 'voz'=>$conVoz])]);
$brain = json_decode(curl_exec($ch), true); curl_close($ch);
$reply = $brain['reply'] ?? 'Disculpa, no pude procesar tu mensaje. Escríbenos y te ayudamos 🙏';

$history[] = ['role' => 'assistant', 'content' => $reply];
@file_put_contents($file, json_encode($history, JSON_UNESCAPED_UNICODE));

/* ── Enviar respuesta por WhatsApp ── */
/* mientras la IA pensaba (unos segundos) alguien del equipo pudo responder: no se pisa */
if (wa_en_pausa($from)) {
    /* la respuesta de la IA no se envio: se saca de su memoria, releyendo el archivo
       porque la respuesta del equipo pudo agregarse mientras tanto */
    $actual = json_decode((string)@file_get_contents($file), true) ?: [];
    foreach (array_reverse(array_keys($actual)) as $i) {
        if (($actual[$i]['role'] ?? '') === 'assistant' && ($actual[$i]['content'] ?? '') === $reply) { array_splice($actual, $i, 1); break; }
    }
    @file_put_contents($file, json_encode(array_values($actual), JSON_UNESCAPED_UNICODE));
    exit;
}
if ($conVoz) {
    $voz = wa_voz_generar($cfg, $reply);
    if ($voz && wa_enviar_audio($cfg, $from, $voz['bytes'], $voz['mime'])) {
        require_once __DIR__ . '/wa-media.php';
        $arch = wa_media_guardar($from, 'voz' . time(), $voz['bytes'], $voz['mime']);
        wa_log($from, 'asistente', $reply, ['media' => ['tipo'=>'audio', 'archivo'=>$arch, 'mime'=>$voz['mime']], 'voz' => $voz['voz']]);
        /* si la respuesta era larga, el audio va cortado: el texto completo también se manda */
        if (mb_strlen(wa_voz_texto_limpio($reply)) > WA_VOZ_MAX) wa_enviar_texto($cfg, $from, $reply);
        exit;
    }
    error_log('wa-webhook: no se pudo mandar la voz; se responde por texto');
}
if (!empty($cfg['waToken']) && !empty($cfg['waPhoneId'])) {
    $url = 'https://graph.facebook.com/v20.0/' . $cfg['waPhoneId'] . '/messages';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['waToken'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp','to'=>$from,'type'=>'text','text'=>['body'=>$reply]])]);
    $resEnvio = curl_exec($ch); $codEnvio = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($codEnvio < 200 || $codEnvio >= 300) error_log('wa-webhook: envio fallo ' . $codEnvio . ' ' . substr((string)$resEnvio, 0, 300));
    wa_log($from, 'asistente', $reply, ($codEnvio >= 200 && $codEnvio < 300) ? [] : ['error' => 'No se pudo enviar (' . $codEnvio . ')']);
}
