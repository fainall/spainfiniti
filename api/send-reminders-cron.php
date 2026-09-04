<?php
require_once __DIR__ . '/supa-key.php';
/**
 * Envío AUTOMÁTICO de recordatorios — Spa Infinity
 * Lo llama un cron cada ~30 min:  curl https://spainfinity.cl/api/send-reminders-cron.php?key=SECRET
 * Busca citas dentro de la ventana (rem_hours) sin recordar y envía email/WhatsApp según config.
 */
header('Content-Type: application/json; charset=UTF-8');

$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? require $cfgFile : [];
$secret = $cfg['cronKey'] ?? 'spa-cron-2026';
if (($_GET['key'] ?? '') !== $secret) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

$SUPA = supa_url() . '/rest/v1/';
$KEY  = supa_key();
function supa($m,$p,$b=null){ global $SUPA,$KEY; $ch=curl_init($SUPA.$p); $h=['apikey: '.$KEY,'Authorization: Bearer '.$KEY,'Content-Type: application/json']; if($m!=='GET')$h[]='Prefer: return=representation'; curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$m,CURLOPT_HTTPHEADER=>$h,CURLOPT_TIMEOUT=>15]); if($b!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($b)); $r=curl_exec($ch); curl_close($ch); return json_decode($r,true); }

$botRows = supa('GET','bot_config?id=eq.1'); $bot = $botRows[0] ?? [];
if (empty($bot['rem_auto'])) { echo json_encode(['skipped'=>'recordatorios automaticos desactivados']); exit; }
$hours = (int)($bot['rem_hours'] ?? 24);
$channel = $bot['rem_channel'] ?? 'email';
$tpl = $bot['rem_template'] ?: 'Hola {cliente} 👋 Te recordamos tu cita en Spa Infinity el {fecha} a las {hora} ({servicio}). ¡Te esperamos!';

$now = time();
$fromDate = date('Y-m-d', $now);
$toDate   = date('Y-m-d', $now + $hours*3600);

$appts = supa('GET','appointments?select=*&reminded_at=is.null&appt_date=gte.'.$fromDate.'&appt_date=lte.'.$toDate.'&status=in.(reserved,confirmed,pending,waiting)') ?: [];
$clients = supa('GET','clients?select=id,email,phone') ?: [];
$emailById = []; foreach($clients as $c) $emailById[$c['id']] = $c['email'] ?? '';

$MONTHS=['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$DOWS=['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];

function waPhone($raw){ $d=preg_replace('/\D/','',$raw); if(!$d)return ''; if(strpos($d,'56')===0)return $d; if(strlen($d)===9&&$d[0]==='9')return '56'.$d; if(strlen($d)===8)return '569'.$d; return $d; }

$sentEmail=0; $sentWa=0; $checked=count($appts);
foreach ($appts as $a) {
    // dentro de la ventana horaria real (no solo la fecha)
    $apptTs = strtotime($a['appt_date'].' '.$a['start_time']);
    if ($apptTs < $now || $apptTs > $now + $hours*3600) continue;

    [$y,$m,$d] = explode('-', $a['appt_date']);
    $fecha = $DOWS[(int)date('w',$apptTs)].', '.(int)$d.' de '.$MONTHS[(int)$m];
    $msg = str_replace(['{cliente}','{fecha}','{hora}','{servicio}'],
        [$a['client_name']??'', $fecha, substr($a['start_time'],0,5), $a['service_name']?:'tu servicio'], $tpl);

    $doneAny = false;
    // Email
    if (in_array($channel,['email','both'])) {
        $email = $emailById[$a['client_id']] ?? '';
        if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $body = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto"><div style="background:linear-gradient(135deg,#c5a467,#8a7344);color:#fff;padding:22px;border-radius:8px 8px 0 0;text-align:center"><h2 style="margin:0;font-family:Georgia,serif">Spa Infinity</h2><p style="margin:6px 0 0;font-size:12px;letter-spacing:2px;text-transform:uppercase">Recordatorio de tu cita</p></div><div style="background:#fff;border:1px solid #e0e0e0;border-top:none;padding:22px;border-radius:0 0 8px 8px;font-size:15px;line-height:1.6">'.nl2br(htmlspecialchars($msg)).'</div></div>';
            $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: Spa Infinity <noreply@spainfinity.cl>\r\nReply-To: reservainfinity@spainfinity.cl\r\n";
            $usedEmail=false; if (@mail($email, '=?UTF-8?B?'.base64_encode('Recordatorio de tu cita — Spa Infinity').'?=', $body, $headers)) { $sentEmail++; $doneAny=true; $usedEmail=true; }
        }
    }
    // WhatsApp (si la API de Meta está configurada)
    if (in_array($channel,['whatsapp','both']) && !empty($cfg['waToken']) && !empty($cfg['waPhoneId'])) {
        $wp = waPhone($a['client_phone']??'');
        if ($wp) {
            $ch=curl_init('https://graph.facebook.com/v20.0/'.$cfg['waPhoneId'].'/messages');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$cfg['waToken'],'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['messaging_product'=>'whatsapp','to'=>$wp,'type'=>'text','text'=>['body'=>$msg]])]);
            $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
            if ($code>=200 && $code<300) { $sentWa++; $doneAny=true; $usedWa=true; }
        }
    }
    if ($doneAny) {
        $canal = (!empty($usedWa) && !empty($usedEmail)) ? 'ambos' : (!empty($usedWa) ? 'whatsapp' : 'email');
        supa('PATCH','appointments?id=eq.'.$a['id'], ['reminded_at'=>date('c'), 'reminded_channel'=>$canal]);
    }
}
echo json_encode(['ok'=>true,'revisadas'=>$checked,'emails'=>$sentEmail,'whatsapp'=>$sentWa,'ventana_horas'=>$hours,'canal'=>$channel]);
