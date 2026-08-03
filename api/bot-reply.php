<?php
/**
 * Cerebro del asistente de reservas — Spa Infinity
 * POST JSON { messages:[{role,content}...], phone?:string }
 * Devuelve { reply:string, booked?:object }
 * Usa la API de Anthropic con herramientas para consultar disponibilidad y agendar.
 */
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$cfgFile = __DIR__ . '/bot-config.php';
if (!file_exists($cfgFile)) { echo json_encode(['reply'=>'⚙️ El asistente aún no está configurado. Agrega tu API key de Anthropic en api/bot-config.php.','error'=>'not_configured']); exit; }
$cfg = require $cfgFile;
if (empty($cfg['anthropicKey']) || strpos($cfg['anthropicKey'],'sk-ant')!==0) { echo json_encode(['reply'=>'⚙️ Falta la API key de Anthropic en la configuración.','error'=>'no_key']); exit; }

$SUPA_URL = 'https://bxwamppamqxtncvfdycy.supabase.co/rest/v1/';
$SUPA_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA';

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

/* ── Cargar contexto ── */
$botRows = supa('GET', 'bot_config?id=eq.1');
$bot = (is_array($botRows) && count($botRows)) ? $botRows[0] : [];
$services = supa('GET', 'services?select=name,price,duration,cat_id') ?: [];
$pros = supa('GET', 'professionals?select=id,name,work_start,work_end,work_days&active=eq.true') ?: [];

$svcLines = [];
foreach ($services as $s) $svcLines[] = '- '.$s['name'].' ('.($s['price']?:'consultar').', '.($s['duration']?:'').')';
$svcText = implode("\n", array_slice($svcLines, 0, 80));

$faq = '';
if (!empty($bot['faq'])) {
    $f = is_string($bot['faq']) ? json_decode($bot['faq'], true) : $bot['faq'];
    if (is_array($f)) foreach ($f as $qa) $faq .= "\nP: ".($qa['q']??'')."\nR: ".($qa['a']??'')."\n";
}

$today = date('Y-m-d');
$dow = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'][date('w')];

$system = "Eres el asistente de reservas de ".($cfg['businessName']??'Spa Infinity').", un centro podológico y spa en ".($cfg['address']??'Santiago').".
Hoy es $dow $today. Atiendes por WhatsApp de forma cálida, breve y clara (usa emojis con moderación).
Tu objetivo: responder dudas y AGENDAR citas. Pide los datos que falten (servicio, día, hora y nombre del cliente).
Antes de confirmar una reserva SIEMPRE usa la herramienta check_availability. Solo agenda con create_booking cuando tengas servicio, fecha (YYYY-MM-DD), hora (HH:MM) y nombre.
Si el horario no está disponible, ofrece alternativas cercanas. No inventes servicios ni precios: usa solo esta lista.

TONO/INSTRUCCIONES DEL NEGOCIO:
".($bot['tone'] ?? 'Amable y profesional.')."
".(!empty($bot['welcome']) ? "\nMensaje de bienvenida sugerido: ".$bot['welcome'] : '')."

SERVICIOS DISPONIBLES:
$svcText
".($faq ? "\nPREGUNTAS FRECUENTES:$faq" : '');

/* ── Herramientas ── */
$tools = [
  [ 'name'=>'check_availability', 'description'=>'Verifica si hay disponibilidad para una fecha y hora. Devuelve profesionales libres.',
    'input_schema'=>['type'=>'object','properties'=>[
      'date'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD'],
      'time'=>['type'=>'string','description'=>'Hora HH:MM (24h)'],
      'duration'=>['type'=>'integer','description'=>'Duración en minutos (default 60)']
    ],'required'=>['date','time']]],
  [ 'name'=>'create_booking', 'description'=>'Crea la reserva una vez confirmados los datos con el cliente.',
    'input_schema'=>['type'=>'object','properties'=>[
      'service_name'=>['type'=>'string'], 'date'=>['type'=>'string'], 'time'=>['type'=>'string'],
      'duration'=>['type'=>'integer'], 'client_name'=>['type'=>'string']
    ],'required'=>['service_name','date','time','client_name']]],
];

$phone = isset($_GET['phone']) ? $_GET['phone'] : '';
$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['phone'])) $phone = $input['phone'];

function weekdayIso($date){ $w = (int)date('N', strtotime($date)); return $w; } // 1=Lun..7=Dom

function do_check($args) {
    global $pros;
    $date=$args['date']; $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $wd = weekdayIso($date);
    $startM = (int)substr($time,0,2)*60 + (int)substr($time,3,2);
    $endM = $startM + $dur;
    $appts = supa('GET', 'appointments?select=professional_id,start_time,end_time&appt_date=eq.'.$date) ?: [];
    $free = [];
    foreach ($pros as $p) {
        $days = is_string($p['work_days']) ? json_decode($p['work_days'],true) : $p['work_days'];
        if (!in_array($wd, $days ?: [1,2,3,4,5,6])) continue;
        $ws = (int)substr($p['work_start'],0,2)*60+(int)substr($p['work_start'],3,2);
        $we = (int)substr($p['work_end'],0,2)*60+(int)substr($p['work_end'],3,2);
        if ($startM < $ws || $endM > $we) continue;
        $busy = false;
        foreach ($appts as $a) {
            if ($a['professional_id'] !== $p['id']) continue;
            $as=(int)substr($a['start_time'],0,2)*60+(int)substr($a['start_time'],3,2);
            $ae=(int)substr($a['end_time'],0,2)*60+(int)substr($a['end_time'],3,2);
            if ($startM < $ae && $endM > $as) { $busy=true; break; }
        }
        if (!$busy) $free[] = ['id'=>$p['id'],'name'=>$p['name']];
    }
    return ['available'=>count($free)>0, 'professionals'=>$free];
}

function do_book($args) {
    global $pros, $phone;
    $chk = do_check($args);
    if (!$chk['available']) return ['ok'=>false, 'reason'=>'no_disponible'];
    $prof = $chk['professionals'][0];
    $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $endM=(int)substr($time,0,2)*60+(int)substr($time,3,2)+$dur;
    $end=sprintf('%02d:%02d', intdiv($endM,60), $endM%60);
    $row = ['professional_id'=>$prof['id'], 'client_name'=>$args['client_name'], 'client_phone'=>$phone,
        'service_name'=>$args['service_name'], 'appt_date'=>$args['date'], 'start_time'=>$time, 'end_time'=>$end,
        'status'=>'reserved', 'notes'=>'Agendado por el asistente de WhatsApp'];
    $res = supa('POST', 'appointments', $row);
    if (is_array($res) && count($res)) return ['ok'=>true, 'professional'=>$prof['name'], 'appointment'=>$res[0]];
    return ['ok'=>false, 'reason'=>'error'];
}

/* ── Mensajes ── */
$messages = $input['messages'] ?? [];
if (!is_array($messages) || !count($messages)) { echo json_encode(['reply'=>'Hola 👋 ¿En qué puedo ayudarte?']); exit; }

/* ── Llamada a Anthropic con loop de herramientas ── */
function anthropic($cfg, $system, $tools, $messages) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>40,
        CURLOPT_HTTPHEADER=>['x-api-key: '.$cfg['anthropicKey'], 'anthropic-version: 2023-06-01', 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(['model'=>$cfg['model']??'claude-haiku-4-5-20251001','max_tokens'=>700,'system'=>$system,'tools'=>$tools,'messages'=>$messages])]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode($r, true)];
}

$booked = null;
for ($i=0; $i<4; $i++) {
    list($code, $resp) = anthropic($cfg, $system, $tools, $messages);
    if ($code !== 200 || !isset($resp['content'])) { echo json_encode(['reply'=>'Disculpa, tuve un problema. ¿Puedes repetirlo? 🙏','error'=>'api_'.$code]); exit; }
    $messages[] = ['role'=>'assistant','content'=>$resp['content']];
    $toolResults = [];
    $textOut = '';
    foreach ($resp['content'] as $block) {
        if ($block['type']==='text') $textOut .= $block['text'];
        if ($block['type']==='tool_use') {
            $out = $block['name']==='check_availability' ? do_check($block['input']) : do_book($block['input']);
            if ($block['name']==='create_booking' && !empty($out['ok'])) $booked = $out;
            $toolResults[] = ['type'=>'tool_result','tool_use_id'=>$block['id'],'content'=>json_encode($out, JSON_UNESCAPED_UNICODE)];
        }
    }
    if (count($toolResults)) { $messages[] = ['role'=>'user','content'=>$toolResults]; continue; }
    echo json_encode(['reply'=>trim($textOut) ?: '¿Podrías darme más detalles? 🙂', 'booked'=>$booked], JSON_UNESCAPED_UNICODE); exit;
}
echo json_encode(['reply'=>'¿Seguimos? Cuéntame qué servicio y día prefieres 🙂', 'booked'=>$booked], JSON_UNESCAPED_UNICODE);
