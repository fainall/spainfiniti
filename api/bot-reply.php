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

/* Quién puede pedirle al cerebro: el panel (con su sesión) o el webhook de
   WhatsApp (con la clave interna). Antes era público: cualquiera creaba
   reservas reales a nombre del teléfono que quisiera y gastaba créditos. */
$INTERNAL_KEY = hash('sha256', ($cfg['openaiKey'] ?? '') . '|spa-internal');
$hdrKey = (string)($_SERVER['HTTP_X_INTERNAL_KEY'] ?? '');
$esWebhook = ($hdrKey !== '' && hash_equals($INTERNAL_KEY, $hdrKey));
if (!$esWebhook) {
    require_once __DIR__ . '/require-auth.php';
    require_panel_user(false);
}

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
$botRows = supa('GET', 'bot_config?id=eq.1&select=*');
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

/* ── quién es y cómo habla ── */
$botName = trim($bot['bot_name'] ?? '') ?: 'Julia';
$negocio = $cfg['businessName'] ?? 'Spa Infinity';

/* datos del local, para responder dónde están y cómo llegar */
$li = $bot['local_info'] ?? [];
if (is_string($li)) $li = json_decode($li, true);
if (!is_array($li)) $li = [];
$direccion = trim(($li['address'] ?? ($cfg['address'] ?? 'Santiago')) . ' ' . ($li['extra'] ?? ''));
$DIAS_ES = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',0=>'Domingo'];
$horarioTxt = '';
foreach ([1,2,3,4,5,6,0] as $d) {
    $hd = ($li['hours'][$d] ?? ($li['hours'][(string)$d] ?? null));
    if (!is_array($hd)) continue;
    $horarioTxt .= "\n- {$DIAS_ES[$d]}: " . ((($hd[2] ?? true) === false || empty($hd[0])) ? 'cerrado' : ($hd[0].' a '.$hd[1]));
}
$tips = trim($bot['local_tips'] ?? '');

/* varias bienvenidas (una por línea) para no saludar siempre igual */
$bienvenidas = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string)($bot['welcome'] ?? '')))));
/* el saludo se sortea aqui: si se le pasan todos al modelo elige
   siempre el mismo, y todos los clientes reciben la misma frase */
$bienvenidasTxt = $bienvenidas
    ? "\nSi es tu primer mensaje de la conversacion, saluda con esta frase (puedes ajustar alguna palabra, no la cambies entera):\n" . $bienvenidas[array_rand($bienvenidas)]
    : "\nAl saludar por primera vez presentate: \"Hola, soy $botName, asistente de $negocio. En que puedo ayudarte?\"";

$system = "Eres $botName, la asistente de $negocio, un centro podológico y spa en $direccion.
Hoy es $dow $today. Atiendes por WhatsApp.

CÓMO HABLAS:
- Natural y profesional, como una recepcionista con experiencia. Nunca robótica ni con frases hechas repetidas.
- Te presentas por tu nombre la primera vez y luego ya no repites la presentación.
- Frases cortas, de tú, en español de Chile. Un emoji de vez en cuando, no en cada mensaje.
- No repitas la misma fórmula ni el mismo saludo dos veces en la conversación.
- Una pregunta a la vez: no pidas todos los datos de golpe.
$bienvenidasTxt

QUÉ HACES:
- Resuelves dudas y AGENDAS citas. Pide lo que falte (servicio, día, hora y nombre).
- Antes de confirmar SIEMPRE usa check_availability. Agenda con create_booking solo cuando tengas servicio, fecha (YYYY-MM-DD), hora (HH:MM) y nombre.
- Si no hay disponibilidad, ofrece alternativas cercanas.

LO QUE NO HACES:
- No inventas servicios, precios, promociones ni horarios: usa solo lo que aparece aquí abajo.
- Si te preguntan algo que no está en esta información (estacionamiento, metro cercano, formas de pago, convenios), NO lo adivines. Di con naturalidad que lo confirmas con el equipo y ofrece que alguien le escriba.
- No das diagnósticos ni indicaciones médicas. Para eso, invitas a evaluación con la podóloga.

DÓNDE ESTAMOS:
$direccion
HORARIO DEL LOCAL:$horarioTxt
" . ($tips ? "\nDATOS PRÁCTICOS (cómo llegar, estacionamiento, accesos):\n$tips\n" : '') . "
INSTRUCCIONES DEL NEGOCIO:
" . ($bot['tone'] ?? 'Cercana, resolutiva y profesional.') . "

SERVICIOS:
$svcText$smartText
" . ($faq ? "\nPREGUNTAS FRECUENTES:$faq" : '');


/* ── Reglas del servicio (horario especial, cupos y recursos) ── */
$svcMeta = $bot['svc_meta'] ?? [];
if (is_string($svcMeta)) $svcMeta = json_decode($svcMeta, true);
if (!is_array($svcMeta)) $svcMeta = [];

/* id del servicio a partir de su nombre */
function svc_id_by_name($name) {
    global $services;
    foreach ($services as $s) if (mb_strtolower($s['name']) === mb_strtolower(trim($name))) return $s['id'] ?? null;
    return null;
}
/* devuelve null si se puede agendar, o el motivo del bloqueo */
function service_rule_block($serviceName, $date, $time, $dur) {
    global $svcMeta;
    if (!$serviceName) return null;
    $sid = svc_id_by_name($serviceName);
    if (!$sid || empty($svcMeta[$sid])) return null;
    $m = $svcMeta[$sid];
    $startM = (int)substr($time,0,2)*60 + (int)substr($time,3,2);
    $endM = $startM + ($dur ?: 60);
    $hhmm = substr($time,0,5);
    $endHHMM = sprintf('%02d:%02d', intdiv($endM,60), $endM%60);

    // horario especial
    if (($m['hourMode'] ?? 'none') === 'range' && !empty($m['from']) && !empty($m['to'])) {
        if ($hhmm < $m['from'] || $endHHMM > $m['to'])
            return 'ese servicio solo se realiza entre las '.$m['from'].' y las '.$m['to'];
    }
    if (($m['hourMode'] ?? 'none') === 'hours' && !empty($m['hours'])) {
        if (!in_array($hhmm, $m['hours']))
            return 'ese servicio solo se realiza a las '.implode(' o las ', $m['hours']).' hrs';
    }

    // citas que se cruzan con ese bloque
    $appts = supa('GET', 'appointments?select=service_name,start_time,end_time,status&appt_date=eq.'.$date) ?: [];
    $solapan = [];
    foreach ($appts as $a) {
        if (in_array($a['status'] ?? '', ['block','cancelled'])) continue;
        $as = (int)substr($a['start_time'],0,2)*60 + (int)substr($a['start_time'],3,2);
        $ae = (int)substr($a['end_time'] ?: $a['start_time'],0,2)*60 + (int)substr($a['end_time'] ?: $a['start_time'],3,2);
        if ($as < $endM && $ae > $startM) $solapan[] = $a;
    }

    // cupos simultáneos
    if (!empty($m['cap']) && $m['cap'] > 1) {
        $mismos = 0;
        foreach ($solapan as $a) if (($a['service_name'] ?? '') === $serviceName) $mismos++;
        if ($mismos >= $m['cap']) return 'ese servicio ya tiene todos sus cupos tomados a esa hora';
    }

    // recursos: box, camilla o equipo que la cita necesita
    $motivo = recurso_bloqueado($serviceName, $date, $startM, $endM, $solapan);
    if ($motivo) return $motivo;

    return null;
}

/* ── RECURSOS (Administración → Recursos) ──────────────────────────
   Cada recurso tiene una cantidad y, si corresponde, su propio horario.
   Devuelve el motivo del bloqueo, o null si hay disponibilidad. */
function recursos_del_servicio($serviceName) {
    global $bot;
    $lista = $bot['resources'] ?? [];
    if (is_string($lista)) $lista = json_decode($lista, true);
    if (!is_array($lista) || !$serviceName) return [];
    $out = [];
    foreach ($lista as $r) {
        if (!is_array($r)) continue;                        // formato antiguo: se ignora
        if (($r['active'] ?? true) === false) continue;
        $sv = $r['services'] ?? [];
        if ($sv === 'all' || (is_array($sv) && in_array($serviceName, $sv))) $out[] = $r;
    }
    return $out;
}
function recurso_abierto($r, $date, $iniM, $finM) {
    if (empty($r['hasHours'])) return true;
    $dow = (int)date('w', strtotime($date));
    $dias = $r['days'] ?? [];
    if (!in_array($dow, $dias)) return false;
    $hpd = $r['horasPorDia'] ?? [];
    $rango = $hpd[$dow] ?? ($hpd[(string)$dow] ?? null);
    $desde = $rango ? (int)substr($rango[0],0,2)*60 + (int)substr($rango[0],3,2) : 9*60;
    $hasta = $rango ? (int)substr($rango[1],0,2)*60 + (int)substr($rango[1],3,2) : 19*60;
    if ($iniM < $desde || $finM > $hasta) return false;
    $brk = $r['breaks'] ?? [];
    $desc = $brk[$dow] ?? ($brk[(string)$dow] ?? []);
    foreach ($desc as $b) {
        $bi = (int)substr($b[0],0,2)*60 + (int)substr($b[0],3,2);
        $bf = (int)substr($b[1],0,2)*60 + (int)substr($b[1],3,2);
        if ($iniM < $bf && $finM > $bi) return false;
    }
    return true;
}
function recurso_bloqueado($serviceName, $date, $iniM, $finM, $solapan) {
    $necesita = recursos_del_servicio($serviceName);
    if (!$necesita) return null;
    foreach ($necesita as $r) {
        if (!recurso_abierto($r, $date, $iniM, $finM))
            return 'a esa hora no está disponible '.$r['name'];
        $cupo = max(1, (int)($r['qty'] ?? 1));
        $enUso = 0;
        foreach ($solapan as $a) {
            foreach (recursos_del_servicio($a['service_name'] ?? '') as $x)
                if (($x['id'] ?? '') === ($r['id'] ?? '')) { $enUso++; break; }
        }
        if ($enUso >= $cupo) return 'a esa hora ya está ocupado '.$r['name'];
    }
    return null;
}

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
/* el teléfono solo lo aporta el webhook, que lo lee del mensaje de Meta */
$phone = $esWebhook ? preg_replace('/\D/', '', (string)($input['phone'] ?? '')) : '';

/* ¿el profesional tiene asignado ese servicio? (Administración → Profesionales) */
function prof_hace_servicio($profId, $serviceName) {
    global $bot;
    if (!$serviceName) return true;
    $meta = $bot['prof_meta'] ?? [];
    if (is_string($meta)) $meta = json_decode($meta, true);
    $m = (is_array($meta) && isset($meta[$profId])) ? $meta[$profId] : null;
    if (!$m || empty($m['services']) || $m['services'] === 'all') return true;
    return in_array($serviceName, (array)$m['services']);
}

function weekdayIso($date){ return (int)date('N', strtotime($date)); }

function do_check($args) {
    global $pros;
    $date=$args['date']; $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $wd = weekdayIso($date);
    $startM=(int)substr($time,0,2)*60+(int)substr($time,3,2); $endM=$startM+$dur;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) return ['available'=>false,'professionals'=>[],'motivo'=>'fecha no válida'];
    $appts = supa('GET', 'appointments?select=professional_id,start_time,end_time&appt_date=eq.'.$date.'&or=(status.is.null,status.neq.cancelled)') ?: [];
    $free=[];
    $svcPedido = $args['service_name'] ?? '';
    foreach ($pros as $p) {
        if ($svcPedido && !prof_hace_servicio($p['id'], $svcPedido)) continue;   // no lo tiene asignado
        $days = is_string($p['work_days']) ? json_decode($p['work_days'],true) : $p['work_days'];
        /* el panel guarda el domingo como 0; un gestor antiguo como 7 */
        if (!in_array($wd, $days ?: [1,2,3,4,5,6]) && !in_array($wd % 7, $days ?: [1,2,3,4,5,6])) continue;
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
    // reglas propias del servicio (horario especial, cupos, recursos)
    if (!empty($args['service_name'])) {
        $bloqueo = service_rule_block($args['service_name'], $date, $time, $dur);
        if ($bloqueo) { $out['available'] = false; $out['motivo'] = $bloqueo; }
    }
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
    if (!$chk['available']) return ['ok'=>false,'reason'=>$chk['motivo'] ?? 'no_disponible'];
    $prof=$chk['professionals'][0];
    if (!prof_hace_servicio($prof['id'], $args['service_name'] ?? ''))
        return ['ok'=>false,'reason'=>'ese profesional no realiza ese servicio'];
    $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $endM=(int)substr($time,0,2)*60+(int)substr($time,3,2)+$dur;
    $end=sprintf('%02d:%02d', intdiv($endM,60), $endM%60);
    $row=['professional_id'=>$prof['id'],'client_name'=>$args['client_name'],'client_phone'=>$phone,
        'service_name'=>$args['service_name'],'appt_date'=>$args['date'],'start_time'=>$time,'end_time'=>$end,
        'status'=>'reserved','origen'=>'bot','notes'=>'Agendado por el asistente de WhatsApp'];
    $res=supa('POST','appointments',$row);
    if (is_array($res)&&count($res)) return ['ok'=>true,'professional'=>$prof['name'],'appointment'=>$res[0]];
    return ['ok'=>false,'reason'=>'error'];
}

/* solo mensajes de usuario y asistente: nadie mete instrucciones de sistema desde fuera */
$convo = array_values(array_filter(array_map(function ($m) {
    if (!is_array($m)) return null;
    $r = $m['role'] ?? ''; $c = $m['content'] ?? '';
    if (!in_array($r, ['user','assistant'], true) || !is_string($c)) return null;
    return ['role' => $r, 'content' => mb_substr($c, 0, 2000)];
}, is_array($input['messages'] ?? null) ? $input['messages'] : [])));
if (!count($convo)) { echo json_encode(['reply'=>'Hola 👋 ¿En qué puedo ayudarte?']); exit; }

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
        error_log('bot-reply openai '.$code.': '.($resp['error']['message'] ?? ''));
        echo json_encode(['reply'=>'Disculpa, tuve un problema. ¿Puedes repetirlo? 🙏','error'=>'api_'.$code]); exit;
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
