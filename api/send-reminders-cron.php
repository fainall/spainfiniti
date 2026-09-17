<?php
require_once __DIR__ . '/supa-key.php';
require_once __DIR__ . '/wa-recordatorio-lib.php';
/**
 * Envío AUTOMÁTICO de recordatorios — Spa Infinity
 * Lo llama un cron cada ~30 min:  php send-reminders-cron.php   (o por web con ?key=cronKey)
 *
 * Busca las citas dentro de la ventana (rem_hours) que no tienen recordatorio y lo
 * envía por correo y/o WhatsApp según la configuración de Recordatorios.
 *
 * Por WhatsApp se usa la plantilla aprobada por Meta "recordatorio_cita_base_v1"
 * (con botones Confirmo / Cancelar): a un cliente que no escribió en las últimas
 * 24 horas WhatsApp solo deja escribirle con una plantilla. El envío queda en el
 * chat y en la memoria de Mariet, y el webhook atiende los botones: confirma o
 * anula la hora y Mariet sigue la conversación si quiere otra hora.
 */
header('Content-Type: application/json; charset=UTF-8');
date_default_timezone_set('America/Santiago');

$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? require $cfgFile : [];
$enConsola = PHP_SAPI === 'cli';
$secret = (string)($cfg['cronKey'] ?? '');
if (!$enConsola && ($secret === '' || !hash_equals($secret, (string)($_GET['key'] ?? '')))) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }
$simular = $enConsola ? in_array('--simular', $argv ?? [], true) : !empty($_GET['simular']);

$SUPA = supa_url() . '/rest/v1/';
$KEY  = supa_key();
function supa($m,$p,$b=null){ global $SUPA,$KEY; $ch=curl_init($SUPA.$p); $h=['apikey: '.$KEY,'Authorization: Bearer '.$KEY,'Content-Type: application/json']; if($m!=='GET')$h[]='Prefer: return=representation'; curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>15]); if($b!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b)); $r=curl_exec($ch); curl_close($ch); return json_decode($r,true); }

$botRows = supa('GET','bot_config?id=eq.1&select=rem_auto,rem_hours,rem_channel,rem_template,local_info'); $bot = $botRows[0] ?? [];
if (empty($bot['rem_auto']) && !$simular) { echo json_encode(['skipped'=>'recordatorios automaticos desactivados']); exit; }
$hours = max(1, (int)($bot['rem_hours'] ?? 24));
$channel = $bot['rem_channel'] ?? 'email';
$tpl = $bot['rem_template'] ?: 'Hola {cliente} 👋 Te recordamos tu cita en Spa Infinity el {fecha} a las {hora} ({servicio}). ¡Te esperamos!';
$li = is_array($bot['local_info'] ?? null) ? $bot['local_info'] : [];
$direccion = trim(($li['address'] ?? ($cfg['address'] ?? 'Santo Domingo 1083, Of. 502, Santiago Centro')));

$now = time();
$appts = supa('GET','appointments?select=*&reminded_at=is.null&appt_date=gte.'.date('Y-m-d', $now).'&appt_date=lte.'.date('Y-m-d', $now + $hours*3600).'&status=in.(reserved,confirmed,pending,waiting)') ?: [];

$MONTHS=['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$DOWS=['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];


$enviados = ['email'=>0, 'whatsapp'=>0]; $errores = []; $simulados = [];
foreach ($appts as $a) {
    $apptTs = strtotime($a['appt_date'].' '.$a['start_time']);
    if ($apptTs < $now || $apptTs > $now + $hours*3600) continue;

    [$y,$m,$d] = explode('-', $a['appt_date']);
    $hora = substr($a['start_time'],0,5);
    $fecha = $DOWS[(int)date('w',$apptTs)].' '.(int)$d.' de '.$MONTHS[(int)$m];
    $nombre = trim((string)($a['client_name'] ?? ''));
    $primerNombre = $nombre !== '' ? mb_convert_case(explode(' ', $nombre)[0], MB_CASE_TITLE, 'UTF-8') : 'Hola';
    $msg = str_replace(['{cliente}','{fecha}','{hora}','{servicio}'], [$nombre, $fecha, $hora, $a['service_name']?:'tu servicio'], $tpl);

    $usedEmail = false; $usedWa = false;

    if (in_array($channel, ['email','both'], true)) {
        $cli = $a['client_id'] ? (supa('GET','clients?select=email&id=eq.'.urlencode($a['client_id']))[0] ?? []) : [];
        $email = $cli['email'] ?? '';
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($simular) { $simulados[] = ['email'=>$email, 'cita'=>$fecha.' '.$hora]; }
            else {
                $body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto"><div style="background:linear-gradient(135deg,#c5a467,#8a7344);color:#fff;padding:22px;border-radius:8px 8px 0 0;text-align:center"><h2 style="margin:0;font-family:Georgia,serif">Spa Infinity</h2><p style="margin:6px 0 0;font-size:12px;letter-spacing:2px;text-transform:uppercase">Recordatorio de tu cita</p></div><div style="background:#fff;border:1px solid #e0e0e0;border-top:none;padding:22px;border-radius:0 0 8px 8px;font-size:15px;line-height:1.6">'.nl2br(htmlspecialchars($msg)).'</div></div>';
                $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Spa Infinity <noreply@spainfinity.cl>\r\nReply-To: reservainfinity@spainfinity.cl\r\n";
                if (@mail($email, '=?UTF-8?B?'.base64_encode('Recordatorio de tu cita — Spa Infinity').'?=', $body, $headers)) { $enviados['email']++; $usedEmail = true; }
            }
        }
    }

    if (in_array($channel, ['whatsapp','both'], true) && !empty($cfg['waToken']) && !empty($cfg['waPhoneId'])) {
        if ($simular) {
            $wp = wa_rec_telefono($a['client_phone'] ?? '');
            if ($wp) $simulados[] = ['whatsapp'=>'…'.substr($wp,-4), 'cita'=>$fecha.' '.$hora, 'como'=>wa_ventana_abierta($wp) ? 'mensaje de Mariet' : 'plantilla'];
        } else {
            $rw = wa_enviar_recordatorio($cfg, $a, $direccion);
            if ($rw['ok']) { $enviados['whatsapp']++; $usedWa = true; }
            elseif (($rw['error'] ?? '') !== 'La cita no tiene un teléfono válido') $errores[] = substr((string)($a['client_phone'] ?? ''), -4) . ': ' . $rw['error'];
        }
    }

    if (!$simular && ($usedEmail || $usedWa)) {
        supa('PATCH','appointments?id=eq.'.$a['id'], ['reminded_at'=>date('c'), 'reminded_channel'=>($usedWa && $usedEmail) ? 'ambos' : ($usedWa ? 'whatsapp' : 'email')]);
    }
}
if ($errores) error_log('recordatorios: '.implode(' | ', array_slice($errores, 0, 5)));
echo json_encode(['ok'=>true, 'simulacion'=>$simular, 'revisadas'=>count($appts), 'enviados'=>$enviados, 'errores'=>$errores,
                  'se_enviarian'=>$simulados, 'ventana_horas'=>$hours, 'canal'=>$channel], JSON_UNESCAPED_UNICODE);
