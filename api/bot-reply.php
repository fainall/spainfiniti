<?php
require_once __DIR__ . '/supa-key.php';
/**
 * Cerebro del asistente de reservas — Spa Infinity (OpenAI)
 * POST JSON { messages:[{role,content}...], phone?:string }
 * Devuelve { reply:string, booked?:object }
 * Usa la API de OpenAI (function calling) para consultar disponibilidad y agendar.
 */
header('Content-Type: application/json; charset=UTF-8');
/* el servidor esta en UTC: sin esto, desde las 21:00 de Chile "hoy" era mañana */
date_default_timezone_set('America/Santiago');
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

$SUPA_URL = supa_url() . '/rest/v1/';
$SUPA_KEY = supa_key();

function supa($method, $path, $body = null) {
    global $SUPA_URL, $SUPA_KEY;
    /* free_slots revisa ~20 horas del dia: las lecturas se guardan mientras dura
       la respuesta, y cualquier escritura las descarta para no leer datos viejos */
    static $memo = [];
    if ($method !== 'GET') $memo = [];
    elseif (isset($memo[$path])) return $memo[$path];
    $ch = curl_init($SUPA_URL . $path);
    $h = ['apikey: '.$SUPA_KEY, 'Authorization: Bearer '.$SUPA_KEY, 'Content-Type: application/json'];
    if ($method !== 'GET') $h[] = 'Prefer: return=representation';
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>$h, CURLOPT_TIMEOUT=>15]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $r = curl_exec($ch); curl_close($ch);
    $j = json_decode($r, true);
    if ($method === 'GET' && is_array($j)) $memo[$path] = $j;
    return $j;
}

/* ── Contexto ── */
$botRows = supa('GET', 'bot_config?id=eq.1&select=*');
$bot = (is_array($botRows) && count($botRows)) ? $botRows[0] : [];
$services = supa('GET', 'services?select=id,name,price,duration,short_desc,long_desc,cat_id') ?: [];
$categorias = supa('GET', 'categories?select=id,name') ?: [];
$nombreCat = [];
foreach ($categorias as $c) $nombreCat[$c['id']] = $c['name'];
$pros = supa('GET', 'professionals?select=id,name,work_start,work_end,work_days&active=eq.true') ?: [];

$svcLines = [];
/* La descripcion es lo que de verdad distingue un servicio de otro: dice,
   por ejemplo, que un tratamiento es para 1 a 4 unas y el otro para 5 a 10.
   Sin ella el asistente se inventaba la diferencia. */
function limpiaTexto($t) {
    $t = preg_replace('/<br\s*\/?>/i', ' ', (string)$t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
    return trim(preg_replace('/\s+/', ' ', $t));
}
foreach ($services as $s) {
    $linea = '- ' . $s['name'] . ' (' . ($s['price'] ?: 'consultar') . ', ' . ($s['duration'] ?: '') . ')';
    if (!empty($nombreCat[$s['cat_id'] ?? ''])) $linea .= ' [' . $nombreCat[$s['cat_id']] . ']';
    $desc = limpiaTexto($s['long_desc'] ?? '') ?: limpiaTexto($s['short_desc'] ?? '');
    if ($desc !== '') $linea .= "\n    " . mb_substr($desc, 0, 300);
    $svcLines[] = $linea;
}
$svcText = implode("\n", $svcLines);

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
$botName = trim($bot['bot_name'] ?? '') ?: 'Mariet';
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
Hoy es $dow $today y ahora son las " . date('H:i') . ". Atiendes por WhatsApp.

CÓMO HABLAS:
- Natural y profesional, como una recepcionista con experiencia. Nunca robótica ni con frases hechas repetidas.
- Te presentas por tu nombre la primera vez y luego ya no repites la presentación.
- Frases cortas, de tú, en español de Chile.
- Usa emoticones con naturalidad, uno o dos por mensaje, como escribe el equipo por
  WhatsApp: para saludar, para acompañar una recomendación o al cerrar. Ejemplos que
  van bien: sonrisa, guiño, manito saludando, chispas, calendario, reloj, pie, uñas,
  flor, corazón. No los pongas en cada frase ni pongas tres seguidos.
- Excepción: cuando la persona cuenta dolor, una infección o algo que le preocupa,
  responde con calidez pero sin emoticones alegres; ahí suenan a burla.
- No repitas la misma fórmula ni el mismo saludo dos veces en la conversación.
- Una pregunta a la vez: no pidas todos los datos de golpe.
- NO SEAS REDUNDANTE (pedido de Luis):
  · Pregunta \"¿necesitas algo más?\" (o parecido) como máximo UNA vez en toda la conversación. Si ya lo preguntaste, no lo vuelvas a preguntar.
  · Si el cliente responde \"no\", \"gracias\", \"ok\", \"listo\" o un emoji, despídete en una línea corta y no hagas más preguntas.
  · No repitas datos que ya dijiste en esta conversación (servicio, fecha completa, profesional, precio, dirección). Si la hora ya quedó confirmada, basta con algo como \"¡Listo! Te esperamos el viernes a las 19:00 ✨\".
  · Mensajes cortos: una o dos frases cuando se trata de confirmar, agradecer o despedirse.
$bienvenidasTxt

QUÉ HACES:
- Resuelves dudas y AGENDAS citas. Pide lo que falte, de a una cosa por mensaje.
- Antes de agendar necesitas: servicio, día, hora, NOMBRE Y APELLIDO y CORREO. Si más abajo aparece CLIENTE QUE YA CONOCEMOS, no los pidas: confírmalos.
- Pregunta siempre si viene con acompañante; si dice que sí, pide su nombre y teléfono.

PRIMERO ENTENDER, DESPUÉS RECOMENDAR:
Casi nadie llega sabiendo qué tratamiento necesita: llegan con una molestia o con
lo que vieron en una publicación. Tu trabajo es entender el caso antes de nombrar
un servicio. Nunca des por hecho que lo que pidieron es lo que les conviene.

- Haz DE DOS A CUATRO preguntas cortas, una por mensaje, antes de recomendar.
  Pregunta como una recepcionista con experiencia, no como un formulario.
- Según lo que cuenten, esto es lo que conviene saber:
  · Uñas con hongos: cuántas uñas están comprometidas, cómo las nota (engrosadas,
    amarillas, deformadas, quebradizas), desde cuándo, si ya se trató antes.
  · Uña encarnada: si es un lado o los dos, si hay inflamación, dolor o pus,
    si se la han tratado antes.
  · Dolor al caminar, callos o durezas: en qué parte del pie, desde cuándo,
    qué calzado usa a diario.
  · Tratamientos corporales o faciales: qué zona le preocupa, qué resultado
    espera, si se ha hecho algo parecido antes.
- Pregunta si es su primera vez en el centro. Y si el caso es de podología,
  pregunta si tiene diabetes o problemas de circulación: cambia el tratamiento
  y el equipo necesita saberlo.
- Cuando ya tengas el cuadro, recomienda UN servicio y explica en una frase por
  qué ese y no otro, con lo que dice su descripción.
- Si lo que cuenta no calza con ningún servicio, o suena a algo que hay que ver en
  persona, dilo con naturalidad y ofrece una evaluación con la podóloga.
- Si el precio depende de algo (cantidad de uñas, zona, largo), pregúntalo antes de
  dar un valor.
- Excepción: si la persona ya sabe exactamente lo que quiere y lo pide por su
  nombre, no la interrogues. Confirma y agenda.
- Todo lo que averigües (cuántas uñas, molestias, si es primera vez, diabetes)
  pásalo en el campo 'detalle' al crear la reserva, para que el equipo lo lea.
- Cuando el cliente diga un día pero no una hora exacta (para hoy, el jueves en la tarde), usa free_slots y ofrécele 3 o 4 horas de esa lista, repartidas si pidió mañana o tarde. Nunca digas que no hay disponibilidad sin haber usado free_slots para ese día.
- Nunca ofrezcas una hora que ya pasó: ahora son las " . date('H:i') . ".
- Antes de confirmar SIEMPRE usa check_availability. Agenda con create_booking solo cuando tengas servicio, fecha (YYYY-MM-DD), hora (HH:MM) y nombre.
- En service_name escribe el nombre EXACTO del servicio tal como aparece en la lista de abajo.
- No preguntes con qué profesional quiere: si hay varias libres, agenda con la primera y dile con quién quedó. Solo si el cliente pide a alguien en particular, o si tú ya le nombraste a una, pásala en professional_name: no se agenda con otra sin avisarle.
- Si el cliente quiere CAMBIAR una hora que ya tiene, crea la nueva con replace_date y replace_time de la anterior: así la anterior se cancela sola. Si tenía VARIOS servicios ese día y cambia de día, crea cada uno en el día nuevo y luego cancela CADA UNO de los anteriores con cancel_booking (una llamada por reserva; create_booking te devuelve la lista en otras_reservas_vigentes). Si solo quiere anular, usa cancel_booking. Nunca digas que una hora quedó cancelada si la función no respondió ok. Y al revés: si create_booking devolvió previous_cancelled true, o cancel_booking respondió ok, la anterior YA está cancelada: dilo como hecho, no preguntes si quiere cancelarla.
- Si no hay disponibilidad, ofrece alternativas cercanas.
- Cuando la reserva ya quedó creada y el cliente solo responde 'sí' o 'gracias', NO vuelvas a llamar a create_booking: despídete o pregunta si necesita algo más.

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

/* El modelo escribe el nombre del servicio como le sale ("Tratamiento Ácido
   Nítrico + Alta Frecuencia-Tipo 2" en vez de "Tratamiento con Ácido Nítrico+
   Alta Frecuencia-Tipo 2"). Con el nombre distinto la reserva quedaba sin
   precio y el panel no la asociaba a ningun servicio. Aqui se busca el del
   catalogo que mas se parece: sin tildes, sin signos, y si aun no calza, el
   que comparte mas palabras. */
function svc_clave($t) {
    $t = mb_strtolower(trim((string)$t), 'UTF-8');
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
}
function svc_del_catalogo($name) {
    global $services;
    $k = svc_clave($name);
    if ($k === '') return null;
    foreach ($services as $s) if (svc_clave($s['name']) === $k) return $s;
    $sinRelleno = fn($x) => array_values(array_diff(explode(' ', $x), ['con','de','del','la','el','y','tipo','en','para','']));
    $pal = $sinRelleno($k);
    $mejor = null; $mejorPts = 0;
    foreach ($services as $s) {
        $ps = $sinRelleno(svc_clave($s['name']));
        $comunes = count(array_intersect($pal, $ps));
        if (!$comunes) continue;
        /* las palabras que no calzan restan: "tipo 1" y "tipo 2" se distinguen */
        $pts = $comunes * 2 - count(array_diff($ps, $pal)) - count(array_diff($pal, $ps));
        if ($pts > $mejorPts) { $mejorPts = $pts; $mejor = $s; }
    }
    return ($mejor && $mejorPts >= 2) ? $mejor : null;
}
/* id del servicio a partir de su nombre */
function svc_id_by_name($name) {
    $s = svc_del_catalogo($name);
    return $s ? ($s['id'] ?? null) : null;
}
/* minutos que dura un servicio del catalogo ("90 min", "1 hora 30") */
function svc_minutos($s, $porDefecto = 60) {
    $d = mb_strtolower((string)($s['duration'] ?? ''));
    if (preg_match('/(\d+)\s*h/', $d, $h)) {
        $min = (int)$h[1] * 60;
        if (preg_match('/h[a-z]*\s*(\d+)/', $d, $m2)) $min += (int)$m2[1];
        return $min ?: $porDefecto;
    }
    if (preg_match('/(\d+)/', $d, $m)) return (int)$m[1] ?: $porDefecto;
    return $porDefecto;
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
      'duration'=>['type'=>'integer'],
      'client_name'=>['type'=>'string','description'=>'Nombre y apellido de quien se atiende'],
      'client_email'=>['type'=>'string','description'=>'Correo del cliente'],
      'acompanante'=>['type'=>'string','description'=>'Nombre del acompanante, si viene con alguien'],
      'acompanante_telefono'=>['type'=>'string','description'=>'Telefono del acompanante'],
      'detalle'=>['type'=>'string','description'=>'Lo que conviene que sepa el equipo: cuantas unas afectadas, molestias, etc.'],
      'professional_name'=>['type'=>'string','description'=>'Profesional que se le dijo al cliente (el que devolvio check_availability). Obligatorio si se le nombro una.'],
      'replace_date'=>['type'=>'string','description'=>'Si es un cambio de hora: fecha YYYY-MM-DD de la reserva anterior, que se cancela sola al crear la nueva'],
      'replace_time'=>['type'=>'string','description'=>'Hora HH:MM de la reserva anterior que se reemplaza']
    ],'required'=>['service_name','date','time']]]],
  ['type'=>'function','function'=>['name'=>'free_slots','description'=>'Devuelve TODAS las horas libres de un día para un servicio, con qué profesionales. Úsala siempre que el cliente pida un día sin hora exacta, o pregunte qué horas hay.',
    'parameters'=>['type'=>'object','properties'=>[
      'date'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD'],
      'service_name'=>['type'=>'string','description'=>'Nombre exacto del servicio']
    ],'required'=>['date']]]],
  ['type'=>'function','function'=>['name'=>'confirm_booking','description'=>'Marca como CONFIRMADA una reserva del cliente en la agenda. Úsala cuando el cliente confirme que asistirá (por ejemplo responde "sí", "confirmo", "ahí estaré" a un recordatorio). Nunca digas que una hora quedó confirmada sin llamar a esta función.',
    'parameters'=>['type'=>'object','properties'=>[
      'date'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD de la reserva'],
      'time'=>['type'=>'string','description'=>'Hora HH:MM de la reserva']
    ],'required'=>['date','time']]]],
  ['type'=>'function','function'=>['name'=>'cancel_booking','description'=>'Cancela una reserva existente del cliente. Usala cuando pida anular o cuando cambie la hora y no se haya usado replace_date. Nunca digas que una hora fue cancelada sin llamar a esta funcion.',
    'parameters'=>['type'=>'object','properties'=>[
      'date'=>['type'=>'string','description'=>'Fecha YYYY-MM-DD de la reserva a cancelar'],
      'time'=>['type'=>'string','description'=>'Hora HH:MM de la reserva a cancelar'],
      'client_name'=>['type'=>'string','description'=>'Nombre con que se reservo']
    ],'required'=>['date','time','client_name']]]],
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

/* La jornada de una profesional ese dia, como la muestra la agenda: primero el
   horario por dia de Administracion -> Profesionales (con sus descansos), y si
   no lo tiene, el horario general. Devuelve null si no trabaja ese dia. */
function jornada_de($p, $date) {
    global $bot;
    $w = (int)date('w', strtotime($date));                     // 0 = domingo
    $meta = $bot['prof_meta'] ?? [];
    if (is_string($meta)) $meta = json_decode($meta, true);
    $m = (is_array($meta) && isset($meta[$p['id']])) ? $meta[$p['id']] : [];
    $aMin = fn($h) => (int)substr($h,0,2)*60 + (int)substr($h,3,2);
    $hpd = $m['horasPorDia'] ?? null;
    if (is_array($hpd) && count($hpd)) {
        $r = $hpd[$w] ?? ($hpd[(string)$w] ?? null);
        if (!is_array($r) || empty($r[0])) return null;
        $ini = $aMin($r[0]); $fin = $aMin($r[1]);
    } else {
        $days = is_string($p['work_days']) ? json_decode($p['work_days'], true) : $p['work_days'];
        $days = $days ?: [1,2,3,4,5,6];
        $wd = weekdayIso($date);
        if (!in_array($wd, $days) && !in_array($wd % 7, $days)) return null;
        $ini = $aMin($p['work_start']); $fin = $aMin($p['work_end']);
    }
    $descansos = [];
    $brk = $m['breaks'] ?? [];
    foreach (($brk[$w] ?? ($brk[(string)$w] ?? [])) as $b) $descansos[] = [$aMin($b[0]), $aMin($b[1])];
    return ['ini'=>$ini, 'fin'=>$fin, 'descansos'=>$descansos];
}

function do_check($args) {
    global $pros;
    $date=$args['date']; $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $wd = weekdayIso($date);
    $startM=(int)substr($time,0,2)*60+(int)substr($time,3,2); $endM=$startM+$dur;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) return ['available'=>false,'professionals'=>[],'motivo'=>'fecha no válida'];
    $appts = supa('GET', 'appointments?select=professional_id,start_time,end_time&appt_date=eq.'.$date.'&or=(status.is.null,status.neq.cancelled)') ?: [];
    $free=[];
    $svcPedido = $args['service_name'] ?? '';
    if ($svcPedido !== '') {
        $sc = svc_del_catalogo($svcPedido);
        if ($sc) {
            $svcPedido = $sc['name']; $args['service_name'] = $sc['name'];
            if (empty($args['duration'])) { $dur = svc_minutos($sc); $endM = $startM + $dur; }
        }
    }
    /* una hora que ya paso no se ofrece */
    if ($date < date('Y-m-d') || ($date === date('Y-m-d') && $startM < (int)date('H')*60 + (int)date('i') + 30))
        return ['available'=>false,'professionals'=>[],'motivo'=>'esa hora ya pasó; ahora son las '.date('H:i').'. Usa free_slots para ver lo que queda hoy'];
    foreach ($pros as $p) {
        if ($svcPedido && !prof_hace_servicio($p['id'], $svcPedido)) continue;   // no lo tiene asignado
        $j = jornada_de($p, $date);
        if (!$j) continue;                                        // no trabaja ese dia
        if ($startM < $j['ini'] || $endM > $j['fin']) continue;
        $enDescanso = false;
        foreach ($j['descansos'] as $d) if ($startM < $d[1] && $endM > $d[0]) { $enDescanso = true; break; }
        if ($enDescanso) continue;
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
        $sc = svc_del_catalogo($args['service_name']);
        if ($sc) $base = $sc['price'];
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
/* Busca al cliente por los ultimos 8 digitos del telefono; si no esta, lo
   crea. Devuelve su id para que la reserva quede ligada a su ficha. */
function ficha_de_cliente($nombre, $telefono, $correo) {
    $dig = preg_replace('/\D/', '', (string)$telefono);
    if (strlen($dig) >= 8) {
        $ult = substr($dig, -8);
        $hay = supa('GET', 'clients?select=id,name,email&phone=like.*' . $ult . '&limit=1');
        if (is_array($hay) && count($hay)) {
            $c = $hay[0];
            /* mismo telefono pero otro nombre (una hija, un acompañante): ficha propia */
            $pn = fn($t) => explode(' ', trim(strtr(mb_strtolower((string)$t, 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'])))[0] ?? '';
            if (trim((string)$nombre) !== '' && $pn($nombre) !== $pn($c['name'] ?? '')) {
                $otro = supa('GET', 'clients?select=id&phone=like.*' . $ult . '&name=ilike.' . rawurlencode($pn($nombre)) . '*&limit=1');
                if (is_array($otro) && count($otro)) return $otro[0]['id'];
                $nuevo = supa('POST', 'clients', ['name' => trim($nombre), 'phone' => $telefono, 'email' => $correo ?: null,
                                                  'notes' => 'Creado por el asistente de WhatsApp (mismo teléfono que ' . ($c['name'] ?? '') . ')']);
                return (is_array($nuevo) && count($nuevo) && !empty($nuevo[0]['id'])) ? $nuevo[0]['id'] : $c['id'];
            }
            /* si llega un correo y la ficha no lo tenia, se completa */
            if ($correo && empty($c['email'])) {
                supa('PATCH', 'clients?id=eq.' . urlencode($c['id']), ['email' => $correo]);
            }
            return $c['id'];
        }
    }
    $nuevo = supa('POST', 'clients', [
        'name'  => trim($nombre),
        'phone' => $telefono,
        'email' => $correo ?: null,
        'notes' => 'Creado por el asistente de WhatsApp',
    ]);
    return (is_array($nuevo) && count($nuevo) && !empty($nuevo[0]['id'])) ? $nuevo[0]['id'] : null;
}

/* Cancela la reserva del cliente en esa fecha y hora (queda como cancelada en
   su historial, como cuando se anula desde el panel). */
/* Todas las horas libres de un dia para un servicio, cada 30 minutos. Antes el
   bot probaba una o dos horas al azar (a veces ya pasadas) y si esas estaban
   tomadas decia que no habia disponibilidad aunque la agenda estuviera libre. */
function do_free_slots($args) {
    global $pros;
    $date = (string)($args['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return ['ok'=>false,'reason'=>'fecha no válida'];
    if ($date < date('Y-m-d')) return ['ok'=>false,'reason'=>'esa fecha ya pasó'];
    $svc = !empty($args['service_name']) ? svc_del_catalogo($args['service_name']) : null;
    $dur = $svc ? svc_minutos($svc) : 60;
    $desde = 24*60; $hasta = 0;
    foreach ($pros as $p) { $j = jornada_de($p, $date); if ($j) { $desde = min($desde, $j['ini']); $hasta = max($hasta, $j['fin']); } }
    $libres = [];
    for ($t = $desde; $t + $dur <= $hasta; $t += 30) {
        $hh = sprintf('%02d:%02d', intdiv($t,60), $t%60);
        $r = do_check(['date'=>$date, 'time'=>$hh, 'duration'=>$dur, 'service_name'=>$svc ? $svc['name'] : '']);
        if (!empty($r['available'])) $libres[] = ['time'=>$hh, 'professionals'=>array_column($r['professionals'], 'name')];
    }
    return ['date'=>$date, 'service'=>$svc ? $svc['name'] : null, 'duration'=>$dur, 'now'=>date('H:i'),
            'free'=>$libres, 'total'=>count($libres)];
}

/* confirma la reserva del cliente en esa fecha y hora (por su telefono o su ficha) */
function do_confirm($args) {
    global $phone, $clienteConocido;
    $date = (string)($args['date'] ?? ''); $time = substr((string)($args['time'] ?? ''), 0, 5);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) return ['ok'=>false,'reason'=>'fecha u hora no válida'];
    $lista = supa('GET', 'appointments?select=id,status,client_phone,client_id&appt_date=eq.'.$date.'&start_time=eq.'.$time.':00&status=in.(reserved,pending,waiting,confirmed)') ?: [];
    $ult = substr(preg_replace('/\D/', '', (string)$phone), -8);
    foreach ($lista as $a) {
        $suya = ($ult !== '' && substr(preg_replace('/\D/', '', (string)($a['client_phone'] ?? '')), -8) === $ult)
             || ($clienteConocido && ($a['client_id'] ?? '') === $clienteConocido['id']);
        if (!$suya) continue;
        if (($a['status'] ?? '') === 'confirmed') return ['ok'=>true, 'ya_estaba_confirmada'=>true];
        $r = supa('PATCH', 'appointments?id=eq.'.urlencode($a['id']), ['status'=>'confirmed']);
        return is_array($r) ? ['ok'=>true, 'confirmed'=>$date.' '.$time] : ['ok'=>false,'reason'=>'error al guardar'];
    }
    return ['ok'=>false,'reason'=>'no encontré una reserva suya en esa fecha y hora'];
}

function do_cancel($args) {
    global $phone;
    $date = (string)($args['date'] ?? ''); $time = substr((string)($args['time'] ?? ''), 0, 5);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) return ['ok'=>false,'reason'=>'fecha u hora no válida'];
    $q = 'appointments?select=id,client_name,client_phone,professional_id&appt_date=eq.'.$date.'&start_time=eq.'.$time.':00&status=neq.cancelled&status=neq.block';
    $lista = supa('GET', $q) ?: [];
    $dig = preg_replace('/\D/', '', (string)$phone);
    $ult = strlen($dig) >= 8 ? substr($dig, -8) : '';
    $nombre = svc_clave($args['client_name'] ?? '');
    foreach ($lista as $a) {
        $mismoTel = $ult !== '' && substr(preg_replace('/\D/', '', (string)($a['client_phone'] ?? '')), -8) === $ult;
        $mismoNombre = $nombre !== '' && svc_clave($a['client_name'] ?? '') === $nombre;
        if ($mismoTel || $mismoNombre) {
            $r = supa('PATCH', 'appointments?id=eq.'.urlencode($a['id']), ['status'=>'cancelled']);
            return is_array($r) ? ['ok'=>true, 'cancelled'=>$date.' '.$time] : ['ok'=>false,'reason'=>'error'];
        }
    }
    return ['ok'=>false,'reason'=>'no encontré una reserva a ese nombre en esa fecha y hora'];
}

/* reservas futuras vigentes del cliente (por telefono o nombre), sin la recien creada */
function otras_reservas($nombre, $excluirId) {
    global $phone;
    $hoy = date('Y-m-d');
    $lista = supa('GET', 'appointments?select=id,appt_date,start_time,service_name,client_name,client_phone&appt_date=gte.'.$hoy.'&status=neq.cancelled&status=neq.block&origen=eq.bot&order=appt_date,start_time') ?: [];
    $dig = preg_replace('/D/', '', (string)$phone);
    $ult = strlen($dig) >= 8 ? substr($dig, -8) : '';
    $k = svc_clave($nombre);
    $out = [];
    foreach ($lista as $a) {
        if (($a['id'] ?? '') === $excluirId) continue;
        $mismoTel = $ult !== '' && substr(preg_replace('/D/', '', (string)($a['client_phone'] ?? '')), -8) === $ult;
        $mismoNombre = $k !== '' && svc_clave($a['client_name'] ?? '') === $k;
        if ($mismoTel || $mismoNombre) $out[] = ['date'=>$a['appt_date'], 'time'=>substr($a['start_time'],0,5), 'service'=>$a['service_name']];
    }
    return $out;
}

function do_book($args) {
    global $phone, $clienteConocido;
    /* cliente conocido que confirmo sus datos: se usan los de la ficha */
    if ($clienteConocido) {
        if (trim((string)($args['client_name'] ?? '')) === '')  $args['client_name']  = $clienteConocido['nombre'];
        if (trim((string)($args['client_email'] ?? '')) === '') $args['client_email'] = $clienteConocido['correo'];
    }
    if (trim((string)($args['client_name'] ?? '')) === '') return ['ok'=>false,'reason'=>'falta el nombre y apellido del cliente'];
    /* el servicio tal como esta en el catalogo: su nombre, su duracion y su precio */
    $svc = svc_del_catalogo($args['service_name'] ?? '');
    if (!$svc) return ['ok'=>false,'reason'=>'no encuentro ese servicio en el catálogo; usa el nombre exacto de la lista'];
    $args['service_name'] = $svc['name'];
    if (empty($args['duration'])) $args['duration'] = svc_minutos($svc);
    /* Si el cliente escribe "sí, confirmo" despues de que ya quedo agendado, el
       modelo volvia a llamar a create_booking y quedaban dos reservas iguales.
       Si ya tiene ese mismo servicio ese dia, se devuelve la que existe. */
    $dig = preg_replace('/\D/', '', (string)$phone);
    $ult = strlen($dig) >= 8 ? substr($dig, -8) : '';
    $previas = supa('GET', 'appointments?select=id,start_time,professional_id,client_name,client_phone&appt_date=eq.'.$args['date'].'&status=neq.cancelled&status=neq.block&service_name=eq.'.rawurlencode($svc['name'])) ?: [];
    foreach ($previas as $pv) {
        $mismoTel = $ult !== '' && substr(preg_replace('/\D/', '', (string)($pv['client_phone'] ?? '')), -8) === $ult;
        $mismoNombre = svc_clave($pv['client_name'] ?? '') === svc_clave($args['client_name']);
        $esLaQueSeCambia = !empty($args['replace_date']) && $args['replace_date'] === $args['date'] && substr($pv['start_time'],0,5) === substr((string)($args['replace_time'] ?? ''),0,5);
        if (($mismoTel || $mismoNombre) && !$esLaQueSeCambia) {
            $pn = '';
            global $pros;
            foreach ($pros as $p) if ($p['id'] === $pv['professional_id']) $pn = $p['name'];
            return ['ok'=>true,'already_booked'=>true,'professional'=>$pn,'service'=>$svc['name'],'price'=>(string)($svc['price'] ?? ''),
                    'time'=>substr($pv['start_time'],0,5),'note'=>'ya tenía esta reserva; no se creó otra. Dile que su hora sigue confirmada a las '.substr($pv['start_time'],0,5).($pn ? ' con '.$pn : '')];
        }
    }
    $chk=do_check($args);
    if (!$chk['available']) return ['ok'=>false,'reason'=>$chk['motivo'] ?? 'no_disponible'];
    /* la profesional que se le nombro al cliente; si ya no esta libre se avisa
       en vez de agendar con otra a escondidas */
    $prof = null;
    $pedida = svc_clave($args['professional_name'] ?? '');
    if ($pedida !== '') {
        foreach ($chk['professionals'] as $p) {
            $kp = svc_clave($p['name']);
            if ($kp === $pedida || strpos($kp, $pedida) === 0 || strpos($pedida, explode(' ', $kp)[0]) === 0) { $prof = $p; break; }
        }
        if (!$prof) return ['ok'=>false,'reason'=>'esa profesional ya no está libre a esa hora; libres: '.implode(', ', array_column($chk['professionals'], 'name'))];
    }
    if (!$prof) $prof = $chk['professionals'][0];
    if (!prof_hace_servicio($prof['id'], $args['service_name'] ?? ''))
        return ['ok'=>false,'reason'=>'ese profesional no realiza ese servicio'];
    $time=substr($args['time'],0,5); $dur=$args['duration']??60;
    $endM=(int)substr($time,0,2)*60+(int)substr($time,3,2)+$dur;
    $end=sprintf('%02d:%02d', intdiv($endM,60), $endM%60);
    /* la ficha del cliente: si ya existe se reutiliza, y si no se crea, para
       que la reserva no quede suelta y el equipo tenga sus datos */
    $clientId = ficha_de_cliente($args['client_name'], $phone, $args['client_email'] ?? '');

    $nota = 'Agendado por el asistente de WhatsApp';
    if (!empty($args['detalle']))              $nota .= '. ' . trim($args['detalle']);
    if (!empty($args['acompanante']))          $nota .= '. Viene con ' . trim($args['acompanante']);
    if (!empty($args['acompanante_telefono'])) $nota .= ' (tel. ' . trim($args['acompanante_telefono']) . ')';

    /* el precio del catalogo en el momento de reservar: sin el, el panel no
       sabia cuanto cobrar y marcaba la reserva como "precio ajustado" */
    $precio = (string)($svc['price'] ?? '');

    $row=['professional_id'=>$prof['id'],'client_name'=>$args['client_name'],'client_phone'=>$phone,
        'service_name'=>$args['service_name'],'appt_date'=>$args['date'],'start_time'=>$time,'end_time'=>$end,
        'status'=>'reserved','origen'=>'bot','notes'=>$nota];
    if ($precio !== '') $row['price'] = $precio;
    if ($clientId) $row['client_id'] = $clientId;
    $res=supa('POST','appointments',$row);
    if (is_array($res)&&count($res)) {
        $out = ['ok'=>true,'professional'=>$prof['name'],'service'=>$svc['name'],'price'=>$precio,'appointment'=>$res[0]];
        /* Las demas reservas vigentes del cliente: si pidio cambiar de dia y
           tenia varias, el modelo las ve aqui y las cancela una por una. */
        $otras = otras_reservas($args['client_name'], $res[0]['id'] ?? '');
        if ($otras) {
            $out['otras_reservas_vigentes'] = $otras;
            $out['aviso'] = 'El cliente tiene estas otras reservas activas. Si pidió cambiar o mover su hora, cancela cada una con cancel_booking; si son citas distintas que sí quiere, déjalas.';
        }
        /* cambio de hora: la anterior se cancela recien cuando la nueva ya existe */
        if (!empty($args['replace_date']) && !empty($args['replace_time'])) {
            $c = do_cancel(['date'=>$args['replace_date'], 'time'=>$args['replace_time'], 'client_name'=>$args['client_name']]);
            $out['previous_cancelled'] = !empty($c['ok']);
            if (empty($c['ok'])) $out['previous_reason'] = $c['reason'] ?? '';
        }
        return $out;
    }
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

/* ── Cliente que ya conocemos (pedido de Luis) ──
   Por el numero de WhatsApp se busca su ficha: asi no se le vuelven a pedir
   nombre y correo, pero se le confirman antes de agendar. El correo se
   muestra enmascarado, por si el telefono lo esta usando otra persona. */
function enmascarar_correo($e) {
    $e = trim((string)$e);
    if (!preg_match('/^([^@]+)@(.+)$/', $e, $m)) return '';
    $u = $m[1];
    return mb_substr($u, 0, 2) . '***@' . $m[2];
}
function cliente_por_telefono($telefono) {
    $dig = preg_replace('/\D/', '', (string)$telefono);
    if (strlen($dig) < 8) return null;
    $hay = supa('GET', 'clients?select=id,name,email,rut&phone=like.*' . substr($dig, -8) . '&order=created_at.asc&limit=1');
    if (!is_array($hay) || !count($hay) || trim((string)($hay[0]['name'] ?? '')) === '') return null;
    $c = $hay[0];
    $hoy = date('Y-m-d');
    $citas = supa('GET', 'appointments?select=appt_date,start_time,service_name,status,professional_id&client_id=eq.' . urlencode($c['id']) . '&order=appt_date.desc,start_time.desc&limit=40') ?: [];
    global $pros;
    $nombrePro = function ($id) use ($pros) { foreach ($pros as $p) if ($p['id'] === $id) return $p['name']; return ''; };
    $proximas = []; $ultima = null; $atendidas = 0;
    foreach ($citas as $a) {
        $st = $a['status'] ?? '';
        if ($a['appt_date'] >= $hoy && !in_array($st, ['cancelled', 'no_show', 'block'], true)) {
            $estadoTxt = ['confirmed'=>'CONFIRMADA', 'reserved'=>'sin confirmar', 'pending'=>'sin confirmar', 'waiting'=>'sin confirmar', 'attending'=>'asistió'][$st] ?? $st;
            $proximas[] = $a['appt_date'] . ' ' . substr($a['start_time'], 0, 5) . ' · ' . $a['service_name'] . ($nombrePro($a['professional_id']) ? ' con ' . $nombrePro($a['professional_id']) : '') . ' (' . $estadoTxt . ')';
        }
        if ($st === 'attending') { $atendidas++; if (!$ultima) $ultima = $a['appt_date'] . ' · ' . $a['service_name']; }
    }
    return ['id' => $c['id'], 'nombre' => trim($c['name']), 'correo' => trim((string)($c['email'] ?? '')),
            'correo_oculto' => enmascarar_correo($c['email'] ?? ''), 'atendidas' => $atendidas, 'ultima' => $ultima,
            'proximas' => array_reverse($proximas)];
}
/* ── Aprendizaje: como responde el equipo (Luis) ──
   Se eligen las respuestas reales del equipo mas parecidas a lo que pregunta
   ahora el cliente, mas un par de las recientes, como ejemplos de estilo y de
   informacion. Lo que diga el catalogo y la agenda manda sobre los ejemplos. */
require_once __DIR__ . '/wa-pausas.php';
$ejemplos = array_values(array_filter(wa_aprende_leer(), fn($e) => !empty($e['activo'])));
if ($ejemplos) {
    $ultimoCliente = '';
    foreach (array_reverse($convo) as $m) if ($m['role'] === 'user') { $ultimoCliente = $m['content']; break; }
    $pal = array_values(array_filter(explode(' ', svc_clave($ultimoCliente)), fn($w) => mb_strlen($w) > 3));
    foreach ($ejemplos as $i => $e) {
        $pe = explode(' ', svc_clave($e['cliente']));
        $ejemplos[$i]['pts'] = count(array_intersect($pal, $pe)) * 10 + $i / 1000;   // empate: el mas reciente
    }
    usort($ejemplos, fn($a, $b) => $b['pts'] <=> $a['pts']);
    $elegidos = array_slice(array_filter($ejemplos, fn($e) => $e['pts'] >= 10), 0, 5);
    $ids = array_column($elegidos, 'id');
    $recientes = array_reverse(array_values(array_filter(wa_aprende_leer(), fn($e) => !empty($e['activo']) && !in_array($e['id'], $ids, true))));
    $elegidos = array_merge($elegidos, array_slice($recientes, 0, 3));
    if ($elegidos) {
        $txt = '';
        foreach ($elegidos as $e) $txt .= "\n— Cliente: " . str_replace("\n", ' / ', $e['cliente']) . "\n  Respuesta del equipo: " . str_replace("\n", ' / ', $e['respuesta']) . "\n";
        $system .= "\n\nASÍ RESPONDE EL EQUIPO DEL SPA (respuestas reales de Luis y su equipo; aprende de ellas):" . $txt .
            "\nCÓMO USAR ESTOS EJEMPLOS:\n- Imita su tono, su forma de explicar y la información útil que entregan (qué recomiendan, cómo lo explican, qué preguntan).\n" .
            "- Si un ejemplo contradice el catálogo, la disponibilidad o los precios de estas instrucciones, manda el catálogo: los ejemplos pueden ser de antes de un cambio.\n" .
            "- No copies nombres, fechas ni datos de otros clientes. No digas que estás usando ejemplos.";
    }
}

$clienteConocido = $phone ? cliente_por_telefono($phone) : null;
if ($clienteConocido) {
    $k = $clienteConocido;
    $system = "CLIENTE QUE YA CONOCEMOS (encontrado por su número de WhatsApp):
- Nombre en la ficha: {$k['nombre']}
- Correo en la ficha: " . ($k['correo'] ? $k['correo_oculto'] : '(no tiene)') . "
- Veces atendido: {$k['atendidas']}" . ($k['ultima'] ? " · última atención: {$k['ultima']}" : '') . "
- Próximas reservas: " . ($k['proximas'] ? implode('; ', $k['proximas']) : 'ninguna') . "
CÓMO USARLO:
- Salúdalo por su primer nombre, con naturalidad, sin decir que lo buscaste en una base de datos.
- NO le pidas nombre ni correo. Confirma el nombre UNA SOLA VEZ en toda la conversación, corto y junto con la hora que le ofreces, por ejemplo:
  \"Tengo el viernes a las 19:00 con Keidy. ¿Te la dejo a nombre de {$k['nombre']}?\"
  Si ya lo confirmó antes en esta conversación (aunque sea para otra hora), NO lo vuelvas a preguntar: agenda directo.
  No menciones el correo ni prometas enviar una confirmación por correo: no se envía ningún correo al agendar.
- Si confirma, llama a create_booking SIN client_name ni client_email: el sistema usa los de la ficha.
- Si dice que el nombre o el correo cambió, o que la hora es para otra persona, pídele el dato correcto y pásalo en create_booking.
" . ($k['correo'] ? '' : "- La ficha no tiene correo: puedes pedírselo una sola vez, al final, como opcional (\"si quieres, déjame tu correo para tu ficha\"). Si no lo da, agenda igual.\n") . "- Si pregunta por sus horas, usa las próximas reservas de arriba. Si quiere cambiar una, usa replace_date y replace_time.
- Si le enviamos un recordatorio y responde que sí asistirá (\"sí\", \"confirmo\", \"ahí estaré\"), llama a confirm_booking con esa fecha y hora. Solo di que quedó confirmada si la función respondió ok.
- Nunca escribas el correo del cliente, ni completo ni enmascarado, salvo que él pregunte qué correo tenemos (ahí usa el enmascarado).

" . $system;
}

$messages = array_merge([['role'=>'system','content'=>$system]], $convo);

/* Los modelos GPT-5 no aceptan max_tokens (piden max_completion_tokens) y
   ademas gastan parte del presupuesto pensando antes de responder: se les da
   mas margen y el esfuerzo mas bajo, que para atender por WhatsApp basta. */
function cuerpoOpenAI($MODEL, $tools, $messages) {
    $cuerpo = ['model'=>$MODEL, 'tools'=>$tools, 'messages'=>$messages];
    if (preg_match('/^(gpt-5|o[134])/', $MODEL)) {
        $cuerpo['max_completion_tokens'] = 2500;
        $cuerpo['reasoning_effort'] = 'low';
    } else {
        $cuerpo['max_tokens'] = 700;
    }
    return $cuerpo;
}
function openai($KEY, $MODEL, $tools, $messages) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>40,
        CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$KEY, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS=>json_encode(cuerpoOpenAI($MODEL, $tools, $messages))]);
    $r=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode($r,true)];
}

$booked=null;
$traza=[];
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
            $out = $name==='check_availability' ? do_check($args) : ($name==='cancel_booking' ? do_cancel($args) : ($name==='confirm_booking' ? do_confirm($args) : ($name==='free_slots' ? do_free_slots($args) : do_book($args))));
            if ($name==='create_booking' && !empty($out['ok'])) $booked=$out;
            $messages[] = ['role'=>'tool','tool_call_id'=>$tc['id'],'content'=>json_encode($out, JSON_UNESCAPED_UNICODE)];
            /* traza para diagnosticar: solo con la clave interna y pidiendola */
            if ($esWebhook && !empty($input['debug'])) $traza[] = ['tool'=>$name, 'args'=>$args, 'out'=>$out];
        }
        continue;
    }
    $salida = ['reply'=>trim($m['content'] ?? '') ?: '¿Podrías darme más detalles? 🙂', 'booked'=>$booked];
    if ($traza) $salida['traza'] = $traza;
    if ($esWebhook && !empty($input['debug'])) $salida['cliente'] = $clienteConocido ? ['nombre'=>$clienteConocido['nombre'], 'correo'=>$clienteConocido['correo_oculto'], 'proximas'=>count($clienteConocido['proximas'])] : null;
    echo json_encode($salida, JSON_UNESCAPED_UNICODE); exit;
}
echo json_encode(['reply'=>'¿Seguimos? Cuéntame qué servicio y día prefieres 🙂', 'booked'=>$booked], JSON_UNESCAPED_UNICODE);
