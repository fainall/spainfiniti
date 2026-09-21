<?php
/**
 * Mariet le pregunta al equipo — Spa Infinity
 *
 * Antes, cuando algo no estaba en el catálogo ni en las preguntas frecuentes,
 * Mariet decía "lo confirmo con el equipo" y ahí moría: nadie se enteraba.
 * Ahora le escribe la duda a Luis por WhatsApp, le avisa al cliente que está
 * consultando, y cuando Luis usa "Responder" sobre ese mensaje, la respuesta le
 * llega al cliente y queda guardada para no volver a preguntar lo mismo.
 * Si el equipo le contesta directo al cliente, la consulta se cierra sola.
 */
require_once __DIR__ . '/wa-pausas.php';

const WA_CONSULTAS_ARCHIVO = __DIR__ . '/bot-sessions/_consultas.json';
const WA_CONSULTA_VENCE = 24 * 3600;        // pasado un día ya no se relaciona con nada
const WA_CONSULTAS_POR_HORA = 6;            // tope, para no llenarle el WhatsApp a Luis

function wa_consultas_leer() {
    $l = is_file(WA_CONSULTAS_ARCHIVO) ? json_decode((string)file_get_contents(WA_CONSULTAS_ARCHIVO), true) : [];
    return is_array($l) ? $l : [];
}
function wa_consultas_guardar($l) {
    @mkdir(dirname(WA_CONSULTAS_ARCHIVO), 0755, true);
    $fp = @fopen(WA_CONSULTAS_ARCHIVO, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    /* se limpian las viejas y las ya respondidas de hace rato */
    $l = array_values(array_filter($l, fn($c) => ($c['t'] ?? 0) > time() - 7 * 86400));
    ftruncate($fp, 0); rewind($fp);
    fwrite($fp, json_encode(array_slice($l, -200), JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN); fclose($fp);
}

/* ¿a qué número del equipo se le pregunta? */
function wa_equipo_tel($cfg) {
    if (!empty($cfg['equipoTel'])) return wa_tel($cfg['equipoTel']);
    /* si no está configurado, el primer número de pruebas del panel (el de Luis) */
    $pruebas = wa_control_leer()['pruebas'];
    return $pruebas ? wa_tel($pruebas[0]) : '';
}
function wa_es_equipo($cfg, $tel) {
    $e = wa_equipo_tel($cfg);
    return $e !== '' && wa_tel($tel) === $e;
}

/* cuántas consultas se enviaron en la última hora */
function wa_consultas_ultima_hora() {
    return count(array_filter(wa_consultas_leer(), fn($c) => ($c['t'] ?? 0) > time() - 3600));
}

/**
 * Le manda la duda al equipo por WhatsApp y la deja anotada.
 * Devuelve ['ok'=>bool, 'reason'=>...]
 */
function wa_consultar_al_equipo($cfg, $pregunta, $clienteTel, $clienteNombre = '', $paraElCliente = '') {
    $pregunta = trim((string)$pregunta);
    if ($pregunta === '') return ['ok' => false, 'reason' => 'la pregunta viene vacía'];
    $equipo = wa_equipo_tel($cfg);
    if ($equipo === '') return ['ok' => false, 'reason' => 'no hay número del equipo configurado (equipoTel en bot-config.php)'];
    /* una duda abierta por cliente: el 19/09 salieron tres casi iguales seguidas */
    if (wa_consulta_abierta_de($clienteTel))
        return ['ok' => false, 'reason' => 'ya hay una consulta de este cliente esperando respuesta del equipo: no mandes otra. Dile que apenas le confirmen le avisas.'];
    if (wa_consultas_ultima_hora() >= WA_CONSULTAS_POR_HORA)
        return ['ok' => false, 'reason' => 'ya se enviaron muchas consultas esta hora; resuélvelo con lo que sabes y ofrece que el equipo le escriba'];

    /* la misma duda, ya respondida antes, no se vuelve a preguntar */
    foreach (wa_consultas_leer() as $c) {
        if (!empty($c['respuesta']) && svc_clave_consulta($c['pregunta']) === svc_clave_consulta($pregunta))
            return ['ok' => true, 'ya_respondida' => true, 'respuesta' => $c['respuesta'],
                    'nota' => 'el equipo ya respondió esto antes: úsalo y no vuelvas a consultar'];
    }

    $quien = trim($clienteNombre) !== '' ? trim($clienteNombre) : ('el cliente +' . $clienteTel);
    $texto = "🤖 Mariet necesita una aclaración\n\n"
           . "Cliente: {$quien} (+{$clienteTel})\n\n"
           . "❓ {$pregunta}\n\n"
           . "👉 Mantén presionado este mensaje y elige *Responder*: así le paso tu respuesta al cliente y la guardo para la próxima. Si no usas Responder, no se envía nada.";

    $ch = curl_init('https://graph.facebook.com/v23.0/' . $cfg['waPhoneId'] . '/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>20, CURLOPT_POST=>true,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $cfg['waToken'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp', 'to'=>$equipo, 'type'=>'text',
                                         'text'=>['body'=>$texto]], JSON_UNESCAPED_UNICODE)]);
    $r = json_decode((string)curl_exec($ch), true); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code < 200 || $code >= 300) {
        error_log('wa-consultas: no se pudo preguntar ' . $code . ' ' . json_encode($r));
        return ['ok' => false, 'reason' => 'no se pudo avisar al equipo; dile al cliente que alguien le escribirá'];
    }
    $wamid = (string)($r['messages'][0]['id'] ?? '');
    $l = wa_consultas_leer();
    $l[] = ['t' => time(), 'wamid' => $wamid, 'equipo' => $equipo, 'cliente' => wa_tel($clienteTel),
            'nombre' => trim($clienteNombre), 'pregunta' => $pregunta, 'para_cliente' => trim($paraElCliente), 'respuesta' => ''];
    wa_consultas_guardar($l);
    wa_log($equipo, 'asistente', $texto, ['consulta' => true]);
    return ['ok' => true, 'enviada' => true];
}

/* clave simple para comparar dos preguntas parecidas */
function svc_clave_consulta($t) {
    $t = mb_strtolower(trim((string)$t), 'UTF-8');
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
}

/* SOLO cuenta como respuesta si Luis usa "Responder" sobre el mensaje de la
   consulta. Antes también valía cualquier mensaje suelto cuando había una sola
   consulta abierta, y eso mandó a clientes mensajes de prueba que Luis le
   escribía a Mariet desde su número ("Ya lo confirmé con el equipo 😊 hola,
   cuanto cuesta..."). Nunca más se adivina. */
function wa_consulta_que_responde($contextoId = '') {
    if ($contextoId === '') return null;
    foreach (wa_consultas_leer() as $i => $c)
        if (($c['wamid'] ?? '') === $contextoId && empty($c['respuesta'])
            && ($c['t'] ?? 0) > time() - WA_CONSULTA_VENCE) return $i;
    return null;
}
/* El equipo le contestó directo al cliente en su chat: sus consultas abiertas
   quedan cerradas, para no mandarle después la misma respuesta repetida. */
function wa_consultas_cerrar_de_cliente($clienteTel, $respuesta) {
    $l = wa_consultas_leer(); $cambio = false;
    foreach ($l as $i => $c) {
        if (empty($c['respuesta']) && wa_tel($c['cliente'] ?? '') === wa_tel($clienteTel)) {
            $l[$i]['respuesta'] = trim((string)$respuesta);
            $l[$i]['respondida'] = time();
            $l[$i]['directo'] = true;
            $cambio = true;
        }
    }
    if ($cambio) wa_consultas_guardar($l);
}
/* ¿ya hay una consulta abierta de este cliente, reciente? */
function wa_consulta_abierta_de($clienteTel) {
    foreach (wa_consultas_leer() as $c)
        if (empty($c['respuesta']) && wa_tel($c['cliente'] ?? '') === wa_tel($clienteTel)
            && ($c['t'] ?? 0) > time() - 3 * 3600) return $c;
    return null;
}
function wa_consulta_responder($i, $respuesta) {
    $l = wa_consultas_leer();
    if (!isset($l[$i])) return null;
    $l[$i]['respuesta'] = trim((string)$respuesta);
    $l[$i]['respondida'] = time();
    wa_consultas_guardar($l);
    return $l[$i];
}
/* las aclaraciones ya respondidas, para que el prompt las tenga a mano */
function wa_consultas_resueltas($max = 12) {
    /* solo lo que el equipo respondió de verdad: las vencidas no son una respuesta */
    $l = array_values(array_filter(wa_consultas_leer(), fn($c) => !empty($c['respuesta']) && empty($c['vencida'])));
    return array_map(function ($c) { $c['respuesta'] = mb_substr($c['respuesta'], 0, 500); return $c; }, array_slice($l, -$max));
}
