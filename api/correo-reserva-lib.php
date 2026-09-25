<?php
/**
 * Correo de confirmación de reserva — Spa Infinity
 *
 * Cuando se crea una reserva (desde el panel, el sitio web o Mariet) salen dos
 * correos: uno al cliente, si tiene correo, y otro al spa. Los dos llevan la
 * reserva como invitación de calendario (.ics), que Gmail, Google Calendar,
 * Outlook, iPhone y Mac reconocen solos, y además botones para agregarla a
 * Google Calendar y a Outlook con un clic (pedido de Luis).
 *
 * Cada reserva se confirma una sola vez: la columna correo_confirmacion_at
 * queda con la hora de envío y no se vuelve a mandar.
 */
require_once __DIR__ . '/supa-key.php';
require_once __DIR__ . '/cliente.php';

const RESERVA_LUGAR = 'Santo Domingo 1083, Of. 502, Santiago Centro';

function cr_supa($metodo, $ruta, $cuerpo = null) {
    $ch = curl_init(supa_url() . '/rest/v1/' . $ruta);
    $h = ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(), 'Content-Type: application/json'];
    if ($metodo !== 'GET') $h[] = 'Prefer: return=representation';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $h]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string)$r, true);
}

/* "martes 29 de septiembre" */
function cr_dia($fecha) {
    $ts = strtotime($fecha . ' 12:00:00');
    $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return $dias[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)];
}

/* hora de Chile → formato de calendario en UTC (20260929T140000Z) */
function cr_utc($fecha, $hora) {
    $d = new DateTime($fecha . ' ' . substr($hora, 0, 5), new DateTimeZone('America/Santiago'));
    $d->setTimezone(new DateTimeZone('UTC'));
    return $d->format('Ymd\THis\Z');
}

/* texto dentro de un .ics: se escapan comas, puntos y coma, barras y saltos */
function cr_ics_txt($t) {
    return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], (string)$t);
}
/* las líneas de un .ics no pueden pasar de 75 bytes: se doblan */
function cr_ics_doblar($linea) {
    $out = ''; $actual = '';
    foreach (preg_split('//u', $linea, -1, PREG_SPLIT_NO_EMPTY) as $c) {
        if (strlen($actual . $c) > 74) { $out .= $actual . "\r\n "; $actual = ''; }
        $actual .= $c;
    }
    return $out . $actual;
}

function cr_ics($a, $prof, $para, $metodo = 'REQUEST') {
    $organizador = cliente_correo();
    $inicio = cr_utc($a['appt_date'], $a['start_time']);
    $fin = cr_utc($a['appt_date'], $a['end_time'] ?: $a['start_time']);
    $detalle = trim(($a['service_name'] ?? '') . ($prof ? ' con ' . $prof : '') . '. '
             . (!empty($a['price']) ? 'Valor: ' . $a['price'] . '. ' : '')
             . 'Si necesitas cambiarla, escríbenos por WhatsApp al +' . cliente_whatsapp() . '.');
    $l = [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Spa Infinity//Agenda//ES', 'CALSCALE:GREGORIAN', 'METHOD:' . $metodo,
        'BEGIN:VEVENT',
        'UID:reserva-' . $a['id'] . '@spainfinity.cl',
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART:' . $inicio,
        'DTEND:' . $fin,
        'SUMMARY:' . cr_ics_txt(($a['service_name'] ?? 'Reserva') . ' · Spa Infinity'),
        'LOCATION:' . cr_ics_txt(RESERVA_LUGAR),
        'DESCRIPTION:' . cr_ics_txt($detalle),
        'ORGANIZER;CN=Spa Infinity:mailto:' . $organizador,
        'ATTENDEE;CN=' . cr_ics_txt($para['nombre']) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=ACCEPTED;RSVP=FALSE:mailto:' . $para['correo'],
        'STATUS:' . ($metodo === 'CANCEL' ? 'CANCELLED' : 'CONFIRMED'),
        'SEQUENCE:0', 'TRANSP:OPAQUE',
        'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:' . cr_ics_txt('Tu hora en Spa Infinity'), 'END:VALARM',
        'END:VEVENT', 'END:VCALENDAR',
    ];
    return implode("\r\n", array_map('cr_ics_doblar', $l)) . "\r\n";
}

/* botones para agregar a Google Calendar y a Outlook */
function cr_links($a, $prof) {
    $titulo = ($a['service_name'] ?? 'Reserva') . ' · Spa Infinity';
    $det = ($a['service_name'] ?? '') . ($prof ? ' con ' . $prof : '');
    $g = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
       . '&text=' . rawurlencode($titulo)
       . '&dates=' . cr_utc($a['appt_date'], $a['start_time']) . '/' . cr_utc($a['appt_date'], $a['end_time'] ?: $a['start_time'])
       . '&details=' . rawurlencode($det) . '&location=' . rawurlencode(RESERVA_LUGAR) . '&ctz=America/Santiago';
    $iso = function ($f, $h) { $d = new DateTime($f . ' ' . substr($h, 0, 5), new DateTimeZone('America/Santiago')); return $d->format('Y-m-d\TH:i:sP'); };
    $o = 'https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent'
       . '&subject=' . rawurlencode($titulo)
       . '&startdt=' . rawurlencode($iso($a['appt_date'], $a['start_time']))
       . '&enddt=' . rawurlencode($iso($a['appt_date'], $a['end_time'] ?: $a['start_time']))
       . '&location=' . rawurlencode(RESERVA_LUGAR) . '&body=' . rawurlencode($det);
    return [$g, $o];
}

/* $opc (opcional, para cancelaciones y cambios): titulo, saludo (HTML), aviso (HTML
   antes de la tabla) y sinCalendario (sin los botones para agregar al calendario) */
function cr_html($a, $prof, $paraSpa, $opc = []) {
    $e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
    [$g, $o] = cr_links($a, $prof);
    $hora = substr($a['start_time'], 0, 5) . ($a['end_time'] ? ' a ' . substr($a['end_time'], 0, 5) : '') . ' hrs';
    $origen = ['bot' => 'WhatsApp (Mariet)', 'web' => 'sitio web', 'panel' => 'panel'][$a['origen'] ?? ''] ?? ($a['origen'] ?? '');
    $saludo = $paraSpa
        ? '<p style="margin:0 0 16px">Entró una reserva nueva' . ($origen ? ' por <strong>' . $e($origen) . '</strong>' : '') . '.</p>'
        : '<p style="margin:0 0 16px">Hola ' . $e(explode(' ', trim($a['client_name'] ?? ''))[0] ?: '') . ', tu hora quedó reservada. Te esperamos ✨</p>';
    $fila = fn($k, $v) => '<tr><td style="padding:7px 0;color:#8a7344;font-size:13px;width:110px;vertical-align:top">' . $k . '</td><td style="padding:7px 0;font-size:15px;color:#222">' . $v . '</td></tr>';
    $tabla = '<table style="width:100%;border-collapse:collapse;margin:0 0 20px">'
        . ($paraSpa ? $fila('Cliente', '<strong>' . $e($a['client_name']) . '</strong>' . ($a['client_phone'] ? '<br><span style="color:#666">+' . $e(ltrim($a['client_phone'], '+')) . '</span>' : '')) : '')
        . $fila('Servicio', '<strong>' . $e($a['service_name']) . '</strong>')
        . $fila('Día', $e(ucfirst(cr_dia($a['appt_date']))))
        . $fila('Hora', $e($hora))
        . ($prof ? $fila('Te atiende', $e($prof)) : '')
        . (!empty($a['price']) ? $fila('Valor', $e($a['price'])) : '')
        . $fila('Dónde', $e(RESERVA_LUGAR) . '<br><span style="color:#666;font-size:13px">A una cuadra del Metro Plaza de Armas, salida Bandera</span>')
        . ($paraSpa && !empty($a['notes']) ? $fila('Notas', $e($a['notes'])) : '')
        . '</table>';
    $boton = fn($url, $txt, $fondo) => '<a href="' . $e($url) . '" style="display:inline-block;margin:0 8px 8px 0;padding:11px 18px;border-radius:8px;background-color:' . $fondo . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px">' . $txt . '</a>';
    $botones = '<p style="margin:0 0 6px;font-size:13px;color:#666">Agrégala a tu calendario:</p>'
        . $boton($g, '📅 Google Calendar', '#1a73e8') . $boton($o, '📅 Outlook', '#0f6cbd')
        . '<p style="margin:10px 0 0;font-size:12px;color:#777777">En iPhone o Mac abre el archivo adjunto <em>reserva.ics</em>.</p>';
    if (isset($opc['saludo'])) $saludo = $opc['saludo'];
    if (!empty($opc['aviso'])) $saludo .= $opc['aviso'];
    if (!empty($opc['sinCalendario'])) $botones = '';
    $pie = $paraSpa ? '' : '<p style="margin:22px 0 0;font-size:13px;color:#666">¿Necesitas cambiarla? Escríbenos por WhatsApp: '
        . '<a href="https://wa.me/' . $e(cliente_whatsapp()) . '" style="color:#8a7344">+' . $e(cliente_whatsapp()) . '</a></p>';
    if (!empty($opc['sinPie'])) $pie = '';
    /* armado con tablas y colores sólidos (bgcolor): Gmail y Outlook borran los degradados,
       y sin color de respaldo el encabezado quedaba blanco con letras blancas */
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"></head>'
        . '<body style="margin:0;padding:0;background-color:#f5f1ea;font-family:Arial,Helvetica,sans-serif" bgcolor="#f5f1ea">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f1ea" style="background-color:#f5f1ea"><tr><td align="center" style="padding:24px 14px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px">'
        . '<tr><td align="center" bgcolor="#8a7344" style="background-color:#8a7344;background-image:linear-gradient(135deg,#c5a467 0%,#8a7344 100%);color:#ffffff;padding:24px;border-radius:12px 12px 0 0;text-align:center">'
        /* el logo en PNG (los correos de Outlook no muestran webp) */
        . '<img src="' . cliente_dominio() . '/images/logo-correo.png" width="110" height="110" alt="Spa Infinity" style="display:block;margin:0 auto;width:110px;height:110px;border:0;border-radius:50%;background-color:#ffffff">'
        . '<div style="margin-top:12px;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#ffffff">' . $e($opc['titulo'] ?? ($paraSpa ? 'Nueva reserva' : 'Reserva confirmada')) . '</div></td></tr>'
        . '<tr><td bgcolor="#ffffff" style="background-color:#ffffff;padding:24px;border-radius:0 0 12px 12px;line-height:1.5;color:#333333;font-size:15px">'
        . $saludo . $tabla . $botones . $pie
        . '</td></tr>'
        . '<tr><td align="center" style="font-size:11px;color:#8c8c8c;padding:14px 0 0">Spa Infinity · Centro Podológico &amp; Spa · ' . $e(RESERVA_LUGAR) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/* envía un correo HTML con el .ics solo como adjunto: si el calendario va dentro de
   multipart/alternative, algunos clientes muestran esa parte en vez del HTML */
function cr_enviar($para, $asunto, $html, $ics, $extra = null) {
    $ics = str_replace('METHOD:REQUEST', 'METHOD:PUBLISH', $ics);
    $metodo = strpos($ics, 'METHOD:CANCEL') !== false ? 'CANCEL' : 'PUBLISH';
    $remitente = cliente_correo();
    $limite = 'spa' . bin2hex(random_bytes(8));
    $alt = 'alt' . bin2hex(random_bytes(8));
    $texto = trim(html_entity_decode(strip_tags(str_replace(['<br>', '</tr>', '</p>'], ["\n", "\n", "\n\n"], $html)), ENT_QUOTES, 'UTF-8'));
    $cab = [
        'MIME-Version: 1.0',
        'From: Spa Infinity <' . $remitente . '>',
        'Reply-To: Spa Infinity <' . $remitente . '>',
        'Content-Type: multipart/mixed; boundary="' . $limite . '"',
    ];
    $cuerpo = "--$limite\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n"
        . "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texto)) . "\r\n"
        . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
        . "--$alt--\r\n"
        . "--$limite\r\nContent-Type: text/calendar; charset=UTF-8; method=$metodo; name=\"reserva.ics\"\r\nContent-Disposition: attachment; filename=\"reserva.ics\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($ics)) . "\r\n"
        . ($extra ? "--$limite\r\nContent-Type: text/calendar; charset=UTF-8; method=CANCEL; name=\"" . $extra['nombre'] . "\"\r\nContent-Disposition: attachment; filename=\"" . $extra['nombre'] . "\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($extra['ics'])) . "\r\n" : '')
        . "--$limite--";
    return @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, implode("\r\n", $cab), '-f' . $remitente);
}

/**
 * Manda la confirmación de una reserva. Devuelve lo que pasó con cada correo.
 * Solo una vez por reserva, salvo $forzar (reenviar desde el panel).
 */
function enviar_correo_reserva($id, $forzar = false) {
    $a = cr_supa('GET', 'appointments?select=*&id=eq.' . rawurlencode($id))[0] ?? null;
    if (!$a) return ['ok' => false, 'motivo' => 'no existe la reserva'];
    if (in_array($a['status'] ?? '', ['cancelled', 'block'], true)) return ['ok' => false, 'motivo' => 'la reserva no está vigente'];
    if (!$forzar && !empty($a['correo_confirmacion_at'])) return ['ok' => true, 'ya_enviado' => $a['correo_confirmacion_at']];

    $prof = '';
    if (!empty($a['professional_id'])) $prof = cr_supa('GET', 'professionals?select=name&id=eq.' . rawurlencode($a['professional_id']))[0]['name'] ?? '';
    $correoCliente = cr_correo_cliente($a);

    $res = ['ok' => true, 'cliente' => 'sin correo', 'spa' => false];
    $asunto = 'Tu hora en Spa Infinity: ' . ($a['service_name'] ?? '') . ' · ' . cr_dia($a['appt_date']) . ' ' . substr($a['start_time'], 0, 5);
    if ($correoCliente !== '' && filter_var($correoCliente, FILTER_VALIDATE_EMAIL)) {
        $ics = cr_ics($a, $prof, ['nombre' => $a['client_name'] ?: 'Cliente', 'correo' => $correoCliente]);
        $res['cliente'] = cr_enviar($correoCliente, $asunto, cr_html($a, $prof, false), $ics) ? $correoCliente : 'falló el envío';
    }
    $spa = cliente_correo();
    $icsSpa = cr_ics($a, $prof, ['nombre' => 'Spa Infinity', 'correo' => $spa]);
    $asuntoSpa = 'Nueva reserva: ' . ($a['client_name'] ?? '') . ' · ' . ($a['service_name'] ?? '') . ' · ' . cr_dia($a['appt_date']) . ' ' . substr($a['start_time'], 0, 5);
    $res['spa'] = cr_enviar($spa, $asuntoSpa, cr_html($a, $prof, true), $icsSpa);

    cr_supa('PATCH', 'appointments?id=eq.' . rawurlencode($id), ['correo_confirmacion_at' => gmdate('c')]);
    return $res;
}

/* el correo del cliente de una reserva: el de su ficha y, si la ficha no tiene,
   el de la cuenta del sitio con que se hizo (se lee con la llave de servicio) */
function cr_correo_cliente($a) {
    $c = '';
    if (!empty($a['client_id'])) $c = trim((string)(cr_supa('GET', 'clients?select=email&id=eq.' . rawurlencode($a['client_id']))[0]['email'] ?? ''));
    if (($c === '' || !filter_var($c, FILTER_VALIDATE_EMAIL)) && !empty($a['user_id'])) {
        $ch = curl_init(supa_url() . '/auth/v1/admin/users/' . rawurlencode($a['user_id']));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key()]]);
        $u = json_decode((string)curl_exec($ch), true); curl_close($ch);
        $c = trim((string)($u['email'] ?? ''));
    }
    return filter_var($c, FILTER_VALIDATE_EMAIL) ? $c : '';
}

function cr_reserva($id) {
    $a = cr_supa('GET', 'appointments?select=*&id=eq.' . rawurlencode($id))[0] ?? null;
    if (!$a) return [null, ''];
    $prof = '';
    if (!empty($a['professional_id'])) $prof = cr_supa('GET', 'professionals?select=name&id=eq.' . rawurlencode($a['professional_id']))[0]['name'] ?? '';
    return [$a, $prof];
}

/**
 * Aviso de cancelación: al cliente (con el .ics que borra la hora de su
 * calendario) y al spa. Se usa cuando el cliente cancela desde su cuenta.
 */
function enviar_correo_cancelacion($id) {
    [$a, $prof] = cr_reserva($id);
    if (!$a) return ['ok' => false, 'motivo' => 'no existe la reserva'];
    if (($a['status'] ?? '') !== 'cancelled') return ['ok' => false, 'motivo' => 'la reserva no está cancelada'];
    $e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
    $nombre = explode(' ', trim($a['client_name'] ?? ''))[0] ?: '';
    $cuando = cr_dia($a['appt_date']) . ' ' . substr($a['start_time'], 0, 5);
    $res = ['ok' => true, 'cliente' => 'sin correo', 'spa' => false];

    $correo = cr_correo_cliente($a);
    if ($correo !== '') {
        $html = cr_html($a, $prof, false, ['titulo' => 'Hora cancelada', 'sinCalendario' => true, 'sinPie' => true,
            'saludo' => '<p style="margin:0 0 16px">Hola ' . $e($nombre) . ', tu hora quedó <strong>cancelada</strong>. '
                      . 'Si quieres, puedes reservar otra cuando te acomode desde <a href="' . $e(cliente_dominio()) . '/mi-cuenta" style="color:#8a7344">tu cuenta</a>.</p>']);
        $ics = cr_ics($a, $prof, ['nombre' => $a['client_name'] ?: 'Cliente', 'correo' => $correo], 'CANCEL');
        $res['cliente'] = cr_enviar($correo, 'Hora cancelada: ' . ($a['service_name'] ?? '') . ' · ' . $cuando, $html, $ics) ? $correo : 'falló el envío';
    }
    $spa = cliente_correo();
    $htmlSpa = cr_html($a, $prof, true, ['titulo' => 'Reserva cancelada', 'sinCalendario' => true,
        'saludo' => '<p style="margin:0 0 16px">El cliente <strong>canceló</strong> esta hora desde su cuenta del sitio. Ya quedó libre en la agenda.</p>']);
    $res['spa'] = cr_enviar($spa, 'Cancelación: ' . ($a['client_name'] ?? '') . ' · ' . ($a['service_name'] ?? '') . ' · ' . $cuando,
        $htmlSpa, cr_ics($a, $prof, ['nombre' => 'Spa Infinity', 'correo' => $spa], 'CANCEL'));
    return $res;
}

/**
 * Cambio de hora: un solo correo con la hora nueva (y su .ics) y, adjunto, el
 * .ics que borra la hora anterior del calendario. Al spa, lo mismo.
 */
function enviar_correo_cambio($idNueva, $idAnterior) {
    [$a, $prof] = cr_reserva($idNueva);
    [$ant, $profAnt] = cr_reserva($idAnterior);
    if (!$a || !$ant) return ['ok' => false, 'motivo' => 'no existe la reserva'];
    $e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
    $nombre = explode(' ', trim($a['client_name'] ?? ''))[0] ?: '';
    $antes = ucfirst(cr_dia($ant['appt_date'])) . ' a las ' . substr($ant['start_time'], 0, 5);
    $aviso = '<p style="margin:0 0 16px;padding:10px 12px;border-radius:8px;background-color:#f5ede2;font-size:14px;color:#555555">'
           . 'Reemplaza tu hora anterior del <strong>' . $e($antes) . '</strong>, que quedó cancelada.</p>';
    $cuando = cr_dia($a['appt_date']) . ' ' . substr($a['start_time'], 0, 5);
    $res = ['ok' => true, 'cliente' => 'sin correo', 'spa' => false];

    $correo = cr_correo_cliente($a);
    if ($correo !== '') {
        $para = ['nombre' => $a['client_name'] ?: 'Cliente', 'correo' => $correo];
        $html = cr_html($a, $prof, false, ['titulo' => 'Hora cambiada', 'aviso' => $aviso,
            'saludo' => '<p style="margin:0 0 16px">Hola ' . $e($nombre) . ', tu hora quedó cambiada. Estos son los nuevos datos:</p>']);
        $res['cliente'] = cr_enviar($correo, 'Tu hora cambió: ' . ($a['service_name'] ?? '') . ' · ' . $cuando, $html,
            cr_ics($a, $prof, $para), ['nombre' => 'hora-anterior-cancelada.ics', 'ics' => cr_ics($ant, $profAnt, $para, 'CANCEL')]) ? $correo : 'falló el envío';
    }
    $spa = cliente_correo();
    $paraSpa = ['nombre' => 'Spa Infinity', 'correo' => $spa];
    $htmlSpa = cr_html($a, $prof, true, ['titulo' => 'Cambio de hora', 'aviso' => $aviso,
        'saludo' => '<p style="margin:0 0 16px">El cliente <strong>cambió su hora</strong> desde su cuenta del sitio.</p>']);
    $res['spa'] = cr_enviar($spa, 'Cambio de hora: ' . ($a['client_name'] ?? '') . ' · ' . ($a['service_name'] ?? '') . ' · ' . $cuando,
        $htmlSpa, cr_ics($a, $prof, $paraSpa), ['nombre' => 'hora-anterior-cancelada.ics', 'ics' => cr_ics($ant, $profAnt, $paraSpa, 'CANCEL')]);
    cr_supa('PATCH', 'appointments?id=eq.' . rawurlencode($idNueva), ['correo_confirmacion_at' => gmdate('c')]);
    return $res;
}
