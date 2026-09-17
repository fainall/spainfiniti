<?php
/**
 * Canje de gift cards desde la web — Spa Infinity
 *
 * GET ?code=GC-XXXXXXXXX
 * Quien recibe una gift card escribe su codigo en /giftcard y ve para quien
 * es, que servicios incluye, hasta cuando vale y en que estado esta cada uno:
 * disponible (se puede agendar), agendado (ya tiene hora) o canjeado.
 *
 * Es publico, sin sesion. Por eso:
 *  - solo responde gift cards pagadas, y nunca entrega correos, montos del
 *    pago ni el mensaje: solo nombres de pila y servicios;
 *  - frena los intentos: 12 consultas cada 15 minutos por visitante y 300
 *    en total, para que nadie pueda probar codigos al azar.
 *
 * Las gift cards viven en api/orders/*.json; los canjes y las reservas que
 * las nombran se consultan con la funcion giftcard_estado (giftcard-canje.sql).
 */
require_once __DIR__ . '/supa-key.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('America/Santiago');

const GC_DIAS_VALIDEZ = 45;   // lo que promete la pagina de gift cards
/* Desde el 16-09-2026 todos los servicios se agendan en la agenda propia
   (Luis). Lista vacia = todos; se deja por si hubiera que volver atras. */
const GC_AGENDA_PROPIA = [];

function responder($status, $data) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$codigo = strtoupper(trim((string)($_GET['code'] ?? '')));
if (!preg_match('/^GC-[A-Z0-9]{6,12}$/', $codigo)) {
    responder(400, ['error' => 'Revisa el código: empieza con GC- y sigue con letras y números.']);
}

/* ── freno de intentos ──
   El CDN de Hostinger pone la IP real en X-Client-IP y reemplaza lo que mande
   el visitante, asi que no se puede falsificar desde fuera. */
$ip = $_SERVER['HTTP_X_CLIENT_IP'] ?? '';
if ($ip === '') $ip = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0]);
if ($ip === '') $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
$dirCache = __DIR__ . '/cache';
@mkdir($dirCache, 0755, true);
function contar_intento($archivo, $limite, $ventana) {
    $ahora = time();
    $fp = @fopen($archivo, 'c+');
    if (!$fp) return true;                       // sin cache no se bloquea a nadie
    flock($fp, LOCK_EX);
    $previos = json_decode(stream_get_contents($fp), true);
    $recientes = array_values(array_filter(is_array($previos) ? $previos : [], fn($t) => $t > $ahora - $ventana));
    $permitido = count($recientes) < $limite;
    if ($permitido) $recientes[] = $ahora;
    ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($recientes));
    flock($fp, LOCK_UN); fclose($fp);
    return $permitido;
}
if (!contar_intento($dirCache . '/canje-ip-' . sha1($ip . '|spa-canje') . '.json', 12, 900)
 || !contar_intento($dirCache . '/canje-global.json', 300, 900)) {
    responder(429, ['error' => 'Hubo demasiados intentos. Espera unos minutos o escríbenos por WhatsApp.']);
}

/* ── la gift card ── */
$orden = null;
foreach (glob(__DIR__ . '/orders/SI-*.json') ?: [] as $f) {
    $o = json_decode(@file_get_contents($f), true);
    if (is_array($o) && strtoupper((string)($o['data']['code'] ?? '')) === $codigo) { $orden = $o; break; }
}
if (!$orden || ($orden['status'] ?? '') !== 'paid') {
    responder(404, ['error' => 'No encontramos una gift card pagada con ese código. Revisa que esté bien escrito.']);
}
$d = $orden['data'] ?? [];

$pagada = strtotime((string)($orden['paidAt'] ?? $orden['createdAt'] ?? 'now')) ?: time();
$vence  = date('Y-m-d', strtotime('+' . GC_DIAS_VALIDEZ . ' days', $pagada));

/* ── consultas a la base (con la llave publica: solo lectura de lo necesario) ── */
function supa_llamar($metodo, $ruta, $cuerpo = null) {
    $ch = curl_init(supa_url() . '/rest/v1/' . $ruta);
    $cab = ['apikey: ' . supa_anon(), 'Authorization: Bearer ' . supa_anon(), 'Content-Type: application/json'];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => $cab,
        CURLOPT_CUSTOMREQUEST => $metodo]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string)$res, true);
    return ($code >= 200 && $code < 300 && is_array($j)) ? $j : null;
}
/* los nombres se escriben distinto segun quien los escribio: sin tildes ni mayusculas */
function normal($s) {
    $s = strtr(mb_strtolower((string)$s, 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
}

$usos = supa_llamar('POST', 'rpc/giftcard_estado', ['p_code' => $codigo]);
if ($usos === null) {
    responder(503, ['error' => 'No pudimos revisar tu gift card en este momento. Inténtalo de nuevo en un rato.']);
}

$servicios = [];
foreach ((array)($d['services'] ?? []) as $s) {
    if (is_array($s) && !empty($s['name'])) $servicios[] = ['nombre' => (string)$s['name'], 'estado' => 'disponible', 'fecha' => null];
}
/* primero lo canjeado y despues lo agendado, cada uso a un servicio que siga libre */
foreach (['canjeado', 'agendado'] as $tipo) {
    foreach ($usos as $u) {
        if (($u['tipo'] ?? '') !== $tipo) continue;
        $k = normal($u['service_name'] ?? '');
        foreach ($servicios as $i => $s) {
            if ($s['estado'] === 'disponible' && ($k === '' || normal($s['nombre']) === $k)) {
                $servicios[$i]['estado'] = $tipo;
                $servicios[$i]['fecha']  = $u['fecha'] ?? null;
                break;
            }
        }
    }
}

/* el servicio del catalogo, para poder agendarlo */
$catalogo = supa_llamar('GET', 'services?select=id,name,duration,cat_id,link') ?: [];
foreach ($servicios as $i => $s) {
    foreach ($catalogo as $c) {
        if (normal($c['name'] ?? '') === normal($s['nombre'])) {
            $servicios[$i]['svc'] = $c['id'];
            $servicios[$i]['duracion'] = $c['duration'] ?? '';
            /* se agenda aqui solo si su categoria usa la agenda propia */
            $servicios[$i]['propia'] = !GC_AGENDA_PROPIA || in_array($c['cat_id'] ?? '', GC_AGENDA_PROPIA, true);
            $link = (string)($c['link'] ?? '');
            if (preg_match('#^https://[a-z0-9.-]*agendapro\.com/#i', $link)) $servicios[$i]['agendapro'] = $link;
            break;
        }
    }
}

$pila = fn($n) => explode(' ', trim((string)$n))[0] ?? '';
responder(200, [
    'ok'        => true,
    'codigo'    => $codigo,
    'para'      => $pila($d['to'] ?? ''),
    'de'        => $pila($d['from'] ?? ''),
    'vence'     => $vence,
    'vencida'   => date('Y-m-d') > $vence,
    'servicios' => $servicios,
]);
