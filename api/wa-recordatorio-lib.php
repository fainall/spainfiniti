<?php
/**
 * Envío de un recordatorio de cita por WhatsApp — Spa Infinity
 * Lo usan el envío automático (send-reminders-cron.php) y el botón del panel (wa-recordar.php).
 *
 * WhatsApp solo deja escribir texto libre si el cliente escribió en las últimas
 * 24 horas. Entonces:
 *   - ventana abierta  → mensaje de Mariet, con su tono, sin costo;
 *   - ventana cerrada  → plantilla aprobada por Meta "recordatorio_cita_base_v1",
 *                        con botones Confirmo / Cancelar.
 * En los dos casos queda en el chat, en la memoria de Mariet y ligado a la cita,
 * para atender el "Confirmo" o "Cancelar" del cliente.
 */
require_once __DIR__ . '/wa-pausas.php';

const WA_PLANTILLA_RECORDATORIO = 'recordatorio_cita_base_v1';
const WA_CONTACTO_SPA = '+56 9 8668 8771';

function wa_rec_telefono($raw) {
    $d = preg_replace('/\D/', '', (string)$raw);
    if (!$d) return '';
    if (strpos($d, '56') === 0) return $d;
    if (strlen($d) === 9 && $d[0] === '9') return '56' . $d;
    if (strlen($d) === 8) return '569' . $d;
    return $d;
}

/* ¿el cliente escribio en las ultimas 24 horas? (con margen de 30 minutos) */
function wa_ventana_abierta($tel) {
    foreach (array_reverse(wa_chat_leer($tel)) as $m) {
        if (($m['quien'] ?? '') === 'cliente' && empty($m['antiguo'])) return (time() - (int)$m['t']) < 23.5 * 3600;
    }
    return false;
}

function wa_fecha_legible($fechaIso, $hora) {
    $MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $ts = strtotime($fechaIso . ' ' . $hora);
    return $DIAS[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' de ' . $MESES[(int)date('n', $ts)];
}

/* devuelve ['ok'=>bool, 'modo'=>'texto'|'plantilla', 'error'=>string] */
function wa_enviar_recordatorio($cfg, $a, $direccion) {
    if (empty($cfg['waToken']) || empty($cfg['waPhoneId'])) return ['ok' => false, 'error' => 'WhatsApp no está conectado'];
    $tel = wa_rec_telefono($a['client_phone'] ?? '');
    if (strlen($tel) < 10) return ['ok' => false, 'error' => 'La cita no tiene un teléfono válido'];

    $hora = substr((string)$a['start_time'], 0, 5);
    $fecha = wa_fecha_legible($a['appt_date'], $hora);
    $servicio = (string)($a['service_name'] ?: 'tu servicio');
    $nombre = trim((string)($a['client_name'] ?? ''));
    $primer = $nombre !== '' ? mb_convert_case(explode(' ', $nombre)[0], MB_CASE_TITLE, 'UTF-8') : '';

    if (wa_ventana_abierta($tel)) {
        $modo = 'texto';
        $texto = 'Hola' . ($primer ? " $primer" : '') . " 👋 Te escribe Mariet, de Spa Infinity. Te recuerdo tu hora de $servicio el $fecha a las $hora ✨\n\n¿Me confirmas si vienes? Responde *Confirmo*, o *Cancelar* si no puedes y te busco otra hora 😊";
        $cuerpo = ['messaging_product' => 'whatsapp', 'to' => $tel, 'type' => 'text', 'text' => ['body' => $texto]];
    } else {
        $modo = 'plantilla';
        $params = [$primer ?: 'hola', 'Spa Infinity', ucfirst($fecha), $hora, $direccion, WA_CONTACTO_SPA];
        $texto = "Hola {$params[0]}.\n\nEste es un recordatorio de tu cita en Spa Infinity, programada para el {$params[2]} a las $hora, en $direccion.\n\nPuedes comunicarte al " . WA_CONTACTO_SPA . " si necesitas ayuda.\n[Confirmo] [Cancelar]";
        $cuerpo = ['messaging_product' => 'whatsapp', 'to' => $tel, 'type' => 'template',
            'template' => ['name' => WA_PLANTILLA_RECORDATORIO, 'language' => ['code' => 'es'],
                'components' => [['type' => 'body', 'parameters' => array_map(fn($p) => ['type' => 'text', 'text' => (string)$p], $params)]]]];
    }

    $ch = curl_init('https://graph.facebook.com/v23.0/' . $cfg['waPhoneId'] . '/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['waToken'], 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($cuerpo, JSON_UNESCAPED_UNICODE)]);
    $r = json_decode((string)curl_exec($ch), true);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code < 200 || $code >= 300) {
        $e = $r['error'] ?? [];
        $cod = (int)($e['code'] ?? 0);
        $msg = $cod === 131042 ? 'Meta bloquea los mensajes por un problema con el medio de pago de la cuenta de WhatsApp'
             : (in_array($cod, [131047, 131026], true) ? 'WhatsApp no aceptó el mensaje (ventana de 24 horas cerrada)'
             : ($e['error_user_msg'] ?? $e['message'] ?? ('error ' . $code)));
        return ['ok' => false, 'modo' => $modo, 'error' => $msg];
    }
    $wamid = (string)($r['messages'][0]['id'] ?? '');
    wa_log($tel, 'asistente', $texto, ['recordatorio' => $a['id'], 'id' => $wamid, 'modo' => $modo]);
    wa_contexto_agregar($tel, 'assistant', $modo === 'texto' ? $texto
        : "Recordatorio enviado al cliente: cita de $servicio el $fecha a las $hora. Botones: Confirmo / Cancelar.");
    wa_recordatorio_guardar($tel, $a['id'], $wamid, $fecha, $hora, $servicio);
    return ['ok' => true, 'modo' => $modo];
}
