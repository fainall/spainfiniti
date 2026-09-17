<?php
/**
 * Pausas del asistente por chat — Spa Infinity
 *
 * Luis atiende desde la app WhatsApp Business del celular y el mismo número
 * está conectado a la API (coexistencia). Cuando él responde a un cliente,
 * Meta lo avisa al webhook (campo smb_message_echoes) y el asistente se calla
 * en ESE chat durante un rato, para no pisarle la conversación. También se
 * puede pausar o reactivar a mano desde el panel.
 *
 * Además hay "números de prueba": con el asistente apagado para los clientes,
 * sigue respondiendo solo a esos números, para probarlo por WhatsApp de verdad.
 *
 * Todo vive en api/bot-sessions/_control.json (cerrado a la web por .htaccess).
 */

const WA_CONTROL_ARCHIVO = __DIR__ . '/bot-sessions/_control.json';

function wa_tel($t) {
    /* se guarda con codigo de pais, solo digitos: 56912345678 */
    $d = preg_replace('/\D/', '', (string)$t);
    if (strlen($d) === 9 && $d[0] === '9') $d = '56' . $d;       // 912345678 → 56912345678
    return $d;
}

function wa_control_leer() {
    $c = is_file(WA_CONTROL_ARCHIVO) ? json_decode((string)file_get_contents(WA_CONTROL_ARCHIVO), true) : null;
    $c = is_array($c) ? $c : [];
    return [
        'pausaMin' => isset($c['pausaMin']) ? max(0, (int)$c['pausaMin']) : 30,   // la IA se calla 30 min desde la ultima respuesta del equipo en ese chat
        'pruebas'  => array_values(array_filter(array_map('wa_tel', (array)($c['pruebas'] ?? [])))),
        'pausas'   => is_array($c['pausas'] ?? null) ? $c['pausas'] : [],
    ];
}

function wa_control_guardar($c) {
    @mkdir(dirname(WA_CONTROL_ARCHIVO), 0755, true);
    /* las pausas vencidas se limpian al guardar */
    $ahora = time();
    foreach ($c['pausas'] as $tel => $p) {
        if (($p['hasta'] ?? 0) !== 0 && ($p['hasta'] ?? 0) < $ahora) unset($c['pausas'][$tel]);
    }
    $fp = fopen(WA_CONTROL_ARCHIVO, 'c+');
    if (!$fp) return false;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($c, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN); fclose($fp);
    return true;
}

/* hasta = 0 significa "hasta que se reactive a mano" */
function wa_pausar($tel, $minutos, $motivo) {
    $c = wa_control_leer();
    $tel = wa_tel($tel);
    if ($tel === '') return false;
    $c['pausas'][$tel] = [
        'hasta'  => $minutos > 0 ? time() + $minutos * 60 : 0,
        'desde'  => time(),
        'motivo' => $motivo,
    ];
    return wa_control_guardar($c);
}

function wa_reanudar($tel) {
    $c = wa_control_leer();
    unset($c['pausas'][wa_tel($tel)]);
    return wa_control_guardar($c);
}

/* ¿el asistente debe callarse en este chat? */
function wa_en_pausa($tel) {
    $c = wa_control_leer();
    $p = $c['pausas'][wa_tel($tel)] ?? null;
    if (!$p) return false;
    return ($p['hasta'] ?? 0) === 0 || $p['hasta'] > time();
}

function wa_es_prueba($tel) {
    return in_array(wa_tel($tel), wa_control_leer()['pruebas'], true);
}

/* ══ Historial de cada chat, para verlo e intervenir desde el panel ══
   Un archivo por numero: bot-sessions/chat-56912345678.json, con los ultimos
   400 mensajes. quien = cliente | asistente | equipo (celular) | panel. */
function wa_chat_archivo($tel) { return __DIR__ . '/bot-sessions/chat-' . wa_tel($tel) . '.json'; }

function wa_log($tel, $quien, $texto, $extra = []) {
    $tel = wa_tel($tel);
    if ($tel === '') return;
    $f = wa_chat_archivo($tel);
    @mkdir(dirname($f), 0755, true);
    $fp = @fopen($f, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    $prev = json_decode((string)stream_get_contents($fp), true);
    $lista = is_array($prev) ? $prev : [];
    $lista[] = array_merge(['t' => time(), 'quien' => $quien, 'texto' => mb_substr((string)$texto, 0, 4000)], $extra);
    if (count($lista) > 400) $lista = array_slice($lista, -400);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode($lista, JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}

function wa_chat_leer($tel) {
    $f = wa_chat_archivo($tel);
    $l = is_file($f) ? json_decode((string)file_get_contents($f), true) : [];
    return is_array($l) ? $l : [];
}

/* lo que el equipo escribe tambien entra al contexto de la IA, para que al
   volver sepa lo que ya se le dijo al cliente */
function wa_contexto_agregar($tel, $rol, $texto) {
    $f = __DIR__ . '/bot-sessions/' . wa_tel($tel) . '.json';
    $h = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $h[] = ['role' => $rol, 'content' => mb_substr((string)$texto, 0, 2000)];
    if (count($h) > 16) $h = array_slice($h, -16);
    @file_put_contents($f, json_encode($h, JSON_UNESCAPED_UNICODE));
}

/* texto legible de un mensaje de WhatsApp de cualquier tipo */
function wa_texto_de($m) {
    $tipo = $m['type'] ?? '';
    if ($tipo === 'text') return (string)($m['text']['body'] ?? '');
    $nombres = ['image'=>'📷 Imagen', 'audio'=>'🎤 Audio', 'video'=>'🎬 Video', 'document'=>'📄 Documento', 'sticker'=>'Sticker',
                'location'=>'📍 Ubicación', 'contacts'=>'👤 Contacto', 'reaction'=>'Reacción', 'button'=>'Botón', 'interactive'=>'Respuesta'];
    $cap = $m[$tipo]['caption'] ?? ($m['button']['text'] ?? ($m['interactive']['button_reply']['title'] ?? ($m['reaction']['emoji'] ?? '')));
    return ($nombres[$tipo] ?? ('[' . $tipo . ']')) . ($cap ? ': ' . $cap : '');
}

/* ══ Aprendizaje: la IA toma como ejemplo lo que responde el equipo ══
   Cada vez que alguien responde a un cliente (desde el celular o el panel) se
   guarda el par "lo que pregunto el cliente → lo que respondio el equipo".
   bot-reply.php usa los mas parecidos a la consulta actual como ejemplos.
   Desde el panel se pueden descartar los que no sirvan. */
const WA_APRENDE_ARCHIVO = __DIR__ . '/bot-sessions/_aprendizaje.json';

function wa_aprende_leer() {
    $l = is_file(WA_APRENDE_ARCHIVO) ? json_decode((string)file_get_contents(WA_APRENDE_ARCHIVO), true) : [];
    return is_array($l) ? $l : [];
}
function wa_aprende_guardar($l) {
    @mkdir(dirname(WA_APRENDE_ARCHIVO), 0755, true);
    $fp = @fopen(WA_APRENDE_ARCHIVO, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode(array_values(array_slice($l, -300)), JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}
/* datos personales fuera: correos, telefonos, RUT */
function wa_aprende_limpiar($t) {
    $t = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/u', '[correo]', (string)$t);
    $t = preg_replace('/\+?\d[\d\s-]{7,}\d/', '[teléfono]', $t);
    $t = preg_replace('/\b\d{1,2}\.?\d{3}\.?\d{3}-[\dkK]\b/', '[rut]', $t);
    return trim($t);
}
function wa_aprender($tel, $respuesta, $autor) {
    $respuesta = trim((string)$respuesta);
    /* "ok", "hola", "un momento": no enseñan nada */
    if (mb_strlen($respuesta) < 25) return;
    /* lo que escribio el cliente justo antes, desde el ultimo mensaje que no fue suyo */
    $chat = wa_chat_leer($tel);
    array_pop($chat);                                    // la respuesta recien guardada
    $preg = [];
    for ($i = count($chat) - 1; $i >= 0; $i--) {
        if (($chat[$i]['quien'] ?? '') !== 'cliente') break;
        array_unshift($preg, (string)$chat[$i]['texto']);
    }
    if (!$preg) return;
    if (time() - (int)(end($chat)['t'] ?? 0) > 6 * 3600) return;   // respuesta a algo muy antiguo: sin contexto claro
    $l = wa_aprende_leer();
    $l[] = ['id' => substr(md5($tel . microtime()), 0, 10), 't' => time(), 'tel' => substr(wa_tel($tel), -4),
            'cliente' => mb_substr(wa_aprende_limpiar(implode("\n", $preg)), 0, 600),
            'respuesta' => mb_substr(wa_aprende_limpiar($respuesta), 0, 1200),
            'autor' => (string)$autor, 'activo' => true];
    wa_aprende_guardar($l);
}

/* ══ Recordatorios enviados por WhatsApp, para atender los botones ══ */
const WA_RECORD_ARCHIVO = __DIR__ . '/bot-sessions/_recordatorios.json';
function wa_recordatorio_guardar($tel, $apptId, $wamid, $fecha, $hora, $servicio) {
    $l = is_file(WA_RECORD_ARCHIVO) ? (json_decode((string)file_get_contents(WA_RECORD_ARCHIVO), true) ?: []) : [];
    $l[] = ['tel' => wa_tel($tel), 'appt' => $apptId, 'wamid' => $wamid, 'fecha' => $fecha, 'hora' => $hora, 'servicio' => $servicio, 't' => time()];
    $l = array_values(array_filter($l, fn($x) => $x['t'] > time() - 7 * 86400));
    @file_put_contents(WA_RECORD_ARCHIVO, json_encode($l, JSON_UNESCAPED_UNICODE));
}
/* el recordatorio al que responde el cliente: por el id del mensaje citado, o el ultimo que se le envio */
function wa_recordatorio_de($tel, $wamidCitado = '') {
    $l = is_file(WA_RECORD_ARCHIVO) ? (json_decode((string)file_get_contents(WA_RECORD_ARCHIVO), true) ?: []) : [];
    $suyos = array_values(array_filter($l, fn($x) => $x['tel'] === wa_tel($tel)));
    if ($wamidCitado !== '') foreach ($suyos as $x) if ($x['wamid'] === $wamidCitado) return $x;
    return $suyos ? end($suyos) : null;
}
