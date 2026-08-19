<?php
require_once __DIR__ . '/supa-key.php';
/**
 * Cerebro del asistente de reservas — Spa Infinity (OpenAI)
 * POST JSON { messages:[{role,content}...], phone?:string }
 * Devuelve { reply:string, booked?:object }
 * Usa la API de OpenAI (function calling) para consultar disponibilidad y agendar.
 */
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$cfgFile = __DIR__ . '/bot-config.php';
if (!file_exists($cfgFile)) { echo json_encode(['reply'=>'⚙️ El asistente aún no está configurado. Agrega tu API key en api/bot-config.php.','error'=>'not_configured']); exit; }
$cfg = require $cfgFile;
$KEY = $cfg['openaiKey'] ?? ($cfg['apiKey'] ?? '');
if (!$KEY || strpos($KEY,'sk-')!==0) { echo json_encode(['reply'=>'⚙️ Falta la API key de OpenAI en la configuración.','error'=>'no_key']); exit; }
$MODEL = $cfg['model'] ?? 'gpt-4o-mini';

$SUPA_URL = 'https://bxwamppamqxtncvfdycy.supabase.co/rest/v1/';
$SUPA_KEY = supa_key();

function supa($method, $path, $body = null) {
    global $SUPA_URL, $SUPA_KEY;
    $ch = curl_init($SUPA_URL . $path);
    $h = ['apikey: '.$SUPA_KEY, 'Authorization: Bearer '.$SUPA_KEY, 'Content-Type: application/json'];
    if ($method !== 'GET') $h[] = 'Prefer: return=representation';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$h, CURLOPT_TIMEOUT=>15]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $r = curl_exec($ch); curl_close($ch);
    return json_decode($r, true);
}

/* ── Contexto ── */
$botRows = supa('GET', 'bot_config?id=eq.1');
$bot = (is_array($botRows) && count($botRows)) ? $botRows[0] : [];
$services = supa('GET', 'services?select=name,price,duration') ?: [];
$pros = supa('GET', 'professionals?select=id,name,work_start,work_end,work_days&active=eq.true') ?: [];

$svcLines = [];
foreach ($services as $s) $svcLines[] = '- '.$s['name'].' ('.($s['price']?:'consultar').', '.($s['duration']?:'').')';
$svcText = implode("\n", array_slice($svcLines, 0, 80));

/* ── Precios inteligentes: descuentos por franja horaria ── */
$smart = $bot['smart_pricing'] ?? null;
if (is_string($smart)) $smart = json_decode($smart, true);
$smartRules = (is_array($smart) && !empty($smart['on'])) ? ($smart['rules'] ?? []) : [];
$smartRules = array_values(array_filter($smartRules, function($r){ return !empty($r['active']); }));

$DOW_ES = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
function money_int($p){ return (int)preg_replace('/[^0-9]/', '', (string)$p); }
function money_fmt($n){ return '$'.number_format($n, 0, ',', '.'); }

/* devuelve ['price'=>int,'pct'=>int,'rule'=>string] para una fecha/hora/servicio */
function smart_price($date, $time, $serviceName, $basePrice) {
    global $smartRules;
    $base = money_int($basePrice);
    $w = (int)date('w', strtotime($date));          // 0=domingo
    $hhmm = substr($time, 0, 5);
    foreach ($smartRules as $r) {
        $days = $r['days'] ?? [];
        if (!in_array($w, $days)) continue;
        if ($hhmm < ($r['from'] ?? '00:00') || $hhmm >= ($r['to'] ?? '23:59')) continue;
        $svcs = $r['services'] ?? 'all';
        if ($svcs !== 'all' && is_array($svcs) && !in_array($serviceName, $svcs)) continue;
        $pct = (int)($r['pct'] ?? 0);
        return ['price'=>(int)round($base * (100 - $pct) / 100), 'pct'=>$pct, 'rule'=>$r['name'] ?? ''];
    }
    return ['price'=>$base, 'pct'=>0, 'rule'=>''];
}

$smartText = '';
if ($smartRules) {
    $lines = [];
    foreach ($smartRules as $r) {
        $dias = implode(', ', array_map(function($d) use ($DOW_ES){ return $DOW_ES[$d]; }, $r['days'] ?? []));
        $svcs = ($r['services'] ?? 'all') === 'all' ? 'todos los servicios' : implode(', ', $r['services']);
        $lines[] = '- '.($r['name'] ?? 'Descuento').': '.$r['pct'].'% menos los '.$dias.' entre '.$r['from'].' y '.$r['to'].' en '.$svcs.'.';
    }
    $smartText = "\n\nPRECIOS REBAJADOS EN HORARIOS DE BAJA DEMANDA (menciónalos cuando el cliente pregunte precios o dude de la hora; son una oportunidad real de ahorro):\n".implode("\n", $lines)."\nCuando propongas una hora dentro de estas franjas, di el precio rebajado y cuánto ahorra.";
}

$faq = '';
if (!empty($bot['faq'])) {
    $f = is_string($bot['faq']) ? json_decode($bot['faq'], true) : $bot['faq'];
    if (is_array($f)) foreach ($f as $qa) $faq .= "\nP: ".($qa['q']??'')."\nR: ".($qa['a']??'')."\n";
}

$today = date('Y-m-d');
$dow = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'][date('w')];

$system = "Eres el asistente de reservas de ".($cfg['businessName']??'Spa Infinity').", un centro podológico y spa en ".($cfg['address']??'Santiago').".
Hoy es $dow $today. Atiendes por WhatsApp de forma cálida, breve y clara (emojis con moderación).
Objetivo: responder dudas y AGENDAR citas. Pide lo que falte (servicio, día, hora y nombre del cliente).
Antes de confirmar SIEMPRE usa la función check_availability. Solo agenda con create_booking cuando tengas servicio, fecha (YYYY-MM-DD), hora (HH:MM) y nombre.
Si no hay disponibilidad, ofrece alternativas cercanas. No inventes servicios ni precios: usa solo esta lista.

TONO/INSTRUCCIONES DEL NEGOCIO:
".($bot['tone'] ?? 'Amable y profesional.')."
".(!empty($bot['welcome']) ? "\nBienvenida sugerida: ".$bot['welcome'] : '')."

SERVICIOS:
$svcText$smartText
".($faq ? "\nPREGUNTAS FRECUENTES:$faq" : '');

/* ── Funciones (tools OpenAI) ── */
$tools = [
  ['type'=>'function','function'=>['name'=>'check_availability','description'=>'Verifica disponibilidad para una fecha y hora. Devuelve profesionales libres.',
    'parameters'=>['type'=>'object','properties'=>[
      'date'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD'],
      'time'=>['type'=>'string','description'=>'Hora HH:MM (24h)'],
      'duration'=>['type'=>'integer','description'=>'Minutos (default 60)'],
      'service_name'=>['type'=>'string','description'=>'Servicio consultado; con él se devuelve el precio de esa hora, ya con descuento si corresponde']
    ],'required'=>['date','time']]]],
  ['type'=>'function','function'=>['name'=>'create_booking','description'=>'Crea la reserva cuando el cliente confirma los datos.',
    'parameters'=>['type'=>'object','properties'=>[
      'service_name'=>['type'=>'string'],'date'=>['type'=>'string'],'time'=>['type'=>'string'],
      'duration'=>['type'=>'integer'],'client_name'=>['type'=>'string']
    ],'required'=>['service_name','date','time','client_name']]]],
];

$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';

function weekdayIso($date){ return (int)date('N', strtotime($date)); }

function do_check($args) {
    global $pros;
    $date=$args['date']; $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $wd = weekdayIso($date);
    $startM=(int)substr($time,0,2)*60+(int)substr($time,3,2); $endM=$startM+$dur;
    $appts = supa('GET', 'appointments?select=professional_id,start_time,end_time&appt_date=eq.'.$date) ?: [];
    $free=[];
    foreach ($pros as $p) {
        $days = is_string($p['work_days']) ? json_decode($p['work_days'],true) : $p['work_days'];
        if (!in_array($wd, $days ?: [1,2,3,4,5,6])) continue;
        $ws=(int)substr($p['work_start'],0,2)*60+(int)substr($p['work_start'],3,2);
        $we=(int)substr($p['work_end'],0,2)*60+(int)substr($p['work_end'],3,2);
        if ($startM<$ws || $endM>$we) continue;
        $busy=false;
        foreach ($appts as $a) { if ($a['professional_id']!==$p['id']) continue;
            $as=(int)substr($a['start_time'],0,2)*60+(int)substr($a['start_time'],3,2);
            $ae=(int)substr($a['end_time'],0,2)*60+(int)substr($a['end_time'],3,2);
            if ($startM<$ae && $endM>$as) { $busy=true; break; } }
        if (!$busy) $free[]=['id'=>$p['id'],'name'=>$p['name']];
    }
    $out = ['available'=>count($free)>0,'professionals'=>$free];
    // precio rebajado si esa hora cae en una franja con precio inteligente
    if (!empty($args['service_name'])) {
        global $services;
        $base = '';
        foreach ($services as $s) if ($s['name'] === $args['service_name']) { $base = $s['price']; break; }
        if ($base !== '') {
            $sp = smart_price($date, $time, $args['service_name'], $base);
            $out['precio_normal'] = money_fmt(money_int($base));
            if ($sp['pct'] > 0) {
                $out['precio_en_esta_hora'] = money_fmt($sp['price']);
                $out['descuento_pct'] = $sp['pct'];
                $out['motivo_descuento'] = $sp['rule'];
                $out['ahorro'] = money_fmt(money_int($base) - $sp['price']);
            }
        }
    }
    return $out;
}
function do_book($args) {
    global $phone;
    $chk=do_check($args);
    if (!$chk['available']) return ['ok'=>false,'reason'=>'no_disponible'];
    $prof=$chk['professionals'][0];
    $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $endM=(int)substr($time,0,2)*60+(int)substr($time,3,2)+$dur;
    $end=sprintf('%02d:%02d', intdiv($endM,60), $endM%60);
    $row=['professional_id'=>$prof['id'],'client_name'=>$args['client_name'],'client_phone'=>$phone,
        'service_name'=>$args['service_name'],'appt_date'=>$args['date'],'start_time'=>$time,'end_time'=>$end,
        'status'=>'reserved','notes'=>'Agendado por el asistente de WhatsApp'];
    $res=supa('POST','appointments',$row);
    if (is_array($res)&&count($res)) return ['ok'=>true,'professional'=>$prof['name'],'appointment'=>$res[0]];
    return ['ok'=>false,'reason'=>'error'];
}

$convo = $input['messages'] ?? [];
if (!is_array($convo) || !count($convo)) { echo json_encode(['reply'=>'Hola 👋 ¿En qué puedo ayudarte?']); exit; }

$messages = array_merge([['role'=>'system','content'=>$system]], $convo);

function openai($KEY, $MODEL, $tools, $messages) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>40,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$KEY, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>$MODEL,'max_tokens'=>700,'tools'=>$tools,'messages'=>$messages])]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode($r,true)];
}

$booked=null;
for ($i=0; $i<4; $i++) {
    list($code,$resp)=openai($KEY,$MODEL,$tools,$messages);
    if ($code!==200 || !isset($resp['choices'][0]['message'])) {
        echo json_encode(['reply'=>'Disculpa, tuve un problema. ¿Puedes repetirlo? 🙏','error'=>'api_'.$code, 'detail'=>($resp['error']['message']??'')]); exit;
    }
    $m = $resp['choices'][0]['message'];
    $messages[] = $m;
    if (!empty($m['tool_calls'])) {
        foreach ($m['tool_calls'] as $tc) {
            $args = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $name = $tc['function']['name'];
            $out = $name==='check_availability' ? do_check($args) : do_book($args);
            if ($name==='create_booking' && !empty($out['ok'])) $booked=$out;
            $messages[] = ['role'=>'tool','tool_call_id'=>$tc['id'],'content'=>json_encode($out, JSON_UNESCAPED_UNICODE)];
        }
        continue;
    }
    echo json_encode(['reply'=>trim($m['content'] ?? '') ?: '¿Podrías darme más detalles? 🙂', 'booked'=>$booked], JSON_UNESCAPED_UNICODE); exit;
}
echo json_encode(['reply'=>'¿Seguimos? Cuéntame qué servicio y día prefieres 🙂', 'booked'=>$booked], JSON_UNESCAPED_UNICODE);
