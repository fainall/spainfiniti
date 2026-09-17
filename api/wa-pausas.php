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
        'pausaMin' => isset($c['pausaMin']) ? max(0, (int)$c['pausaMin']) : 120,
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
