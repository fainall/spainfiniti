<?php
/**
 * POST /api/abono-web.php — el pago del abono al reservar por el sitio.
 *
 * Desde el 28-sep (Luis) la hora NO se toma antes de pagar:
 *   {accion:'iniciar', cliente, profesional, servicio, fecha, inicio, fin,
 *    precio, nota, nombre, fono, correo?}
 *        revisa que la hora se pueda reservar (sin tomarla), crea el pago en
 *        Flow con los datos de la reserva y devuelve { paymentUrl, orden }.
 *        La reserva se crea cuando llega el pago (abono-lib.php).
 *        Si el abono está apagado responde { sinAbono } y el sitio reserva
 *        como siempre.
 *   {orden, consultar:1}   en qué está ese pago (la página de vuelta)
 *   {orden, accion:'reintentar'}  otro intento de pago para la misma hora
 *        (se vuelve a revisar que siga libre)
 *
 * Camino anterior, que queda para reservas ya creadas:
 *   {id, gc}          reserva pagada con gift card: se confirma sin abono
 *   {id}              reserva en 'pago_pendiente' (de antes del cambio): pago en Flow
 *   {id, consultar:1} en qué está la reserva
 *
 * No recibe montos: el abono sale de la configuración del panel y del precio
 * del servicio.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/abono-lib.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);
$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d)) responder(400, ['error' => 'Los datos llegaron ilegibles; vuelve a intentarlo']);
$UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

/* el correo para Flow: el de la ficha, o el que escribió, o el del spa (Flow exige uno) */
function correo_para_flow($clienteId, $escrito) {
    $c = $clienteId ? trim((string)(cr_supa('GET', 'clients?select=email&id=eq.' . rawurlencode($clienteId))[0]['email'] ?? '')) : '';
    if (!filter_var($c, FILTER_VALIDATE_EMAIL)) $c = filter_var((string)$escrito, FILTER_VALIDATE_EMAIL) ? (string)$escrito : '';
    return $c !== '' ? $c : cliente_correo();
}
function vuelve_cliente($clienteId, $citaId = '') {
    return $clienteId ? cr_visitas(['client_id' => $clienteId, 'id' => $citaId ?: '00000000-0000-0000-0000-000000000000']) > 0 : false;
}
$errorPago = 'No pudimos iniciar el pago. Tu hora no quedó reservada: inténtalo de nuevo en un momento o escríbenos por WhatsApp.';

/* ════════ iniciar: revisar la hora y pagar, sin tomarla ════════ */
if (($d['accion'] ?? '') === 'iniciar') {
    $r = [
        'cliente'     => (string)($d['cliente'] ?? ''),
        'profesional' => (string)($d['profesional'] ?? ''),
        'servicio'    => mb_substr(trim((string)($d['servicio'] ?? '')), 0, 120),
        'fecha'       => (string)($d['fecha'] ?? ''),
        'inicio'      => substr((string)($d['inicio'] ?? ''), 0, 8),
        'fin'         => substr((string)($d['fin'] ?? ''), 0, 8),
        'precio'      => mb_substr(trim((string)($d['precio'] ?? '')), 0, 20),
        'nota'        => mb_substr(trim((string)($d['nota'] ?? '')), 0, 500),
        'nombre'      => mb_substr(trim((string)($d['nombre'] ?? '')), 0, 80),
        'fono'        => mb_substr(trim((string)($d['fono'] ?? '')), 0, 30),
    ];
    if (!preg_match($UUID, $r['profesional']) || ($r['cliente'] !== '' && !preg_match($UUID, $r['cliente']))
        || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['fecha']) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $r['inicio'])
        || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $r['fin']) || $r['servicio'] === '' || mb_strlen($r['nombre']) < 2)
        responder(400, ['error' => 'Faltan datos de la reserva; vuelve a intentarlo']);
    if ($r['cliente'] !== '' && !cr_supa('GET', 'clients?select=id&id=eq.' . rawurlencode($r['cliente'])))
        responder(400, ['error' => 'No encontramos tu ficha; vuelve a intentarlo']);

    $cfg = abono_config();
    if (!$cfg['activo']) responder(200, ['ok' => true, 'sinAbono' => true]);

    if ($motivo = abono_probar_hora($r)) responder(409, ['error' => $motivo . '. Elige otra hora, por favor.']);

    $monto = abono_monto(abono_como_cita($r), $cfg);
    try {
        $p = abono_crear_pago($r, null, $monto, correo_para_flow($r['cliente'], $d['correo'] ?? ''), $cfg['minutos'] * 60);
    } catch (Exception $e) {
        error_log('abono-web iniciar: ' . $e->getMessage());
        responder(502, ['error' => $errorPago]);
    }
    responder(200, ['ok' => true, 'paymentUrl' => $p['url'], 'orden' => $p['orden'], 'monto' => $monto,
                    'minutos' => $cfg['minutos'], 'vuelve' => vuelve_cliente($r['cliente'])]);
}

/* ════════ por número de orden: consultar o reintentar ════════ */
if (!empty($d['orden'])) {
    $co = preg_replace('/[^A-Za-z0-9\-]/', '', (string)$d['orden']);
    $f = __DIR__ . '/orders/' . $co . '.json';
    $o = (strpos($co, 'AB-') === 0 && is_file($f)) ? json_decode((string)file_get_contents($f), true) : null;
    if (!is_array($o) || ($o['tipo'] ?? '') !== 'abono_web') responder(404, ['error' => 'No encontramos ese pago']);
    $r = $o['reserva'] ?? null;
    $cita = !empty($o['appointment_id']) ? abono_reserva($o['appointment_id']) : null;
    $base = $cita ?: ($r ? abono_como_cita($r) : []);
    $clienteId = $cita['client_id'] ?? ($r['cliente'] ?? '');
    $estado = ($o['status'] ?? '') === 'paid'
        ? ((($o['resultado'] ?? '') === 'perdida') ? 'perdida' : 'confirmada')
        : (in_array($o['status'] ?? '', ['rejected', 'cancelled', 'no_creada', 'amount_mismatch'], true) ? 'rechazado' : 'pendiente');
    $info = ['estado' => $estado, 'servicio' => $base['service_name'] ?? '', 'fecha' => $base['appt_date'] ?? '',
             'hora' => substr((string)($base['start_time'] ?? ''), 0, 5), 'nombre' => explode(' ', trim((string)($base['client_name'] ?? '')))[0] ?? '',
             'vuelve' => vuelve_cliente($clienteId, $o['appointment_id'] ?? ''), 'id' => $o['appointment_id'] ?? null];
    if (!empty($d['consultar'])) responder(200, ['ok' => true] + $info);

    if (($d['accion'] ?? '') === 'reintentar') {
        if ($estado === 'confirmada' || $estado === 'perdida') responder(200, ['ok' => true] + $info);
        if (!$r) responder(410, ['error' => 'Ese pago ya no se puede reintentar. Vuelve a reservar, por favor.']);
        $cfg = abono_config();
        if ($motivo = abono_probar_hora($r)) responder(409, ['error' => $motivo . '. Vuelve a elegir una hora, por favor.']);
        try {
            $p = abono_crear_pago($r, null, (int)$o['amount'], correo_para_flow($r['cliente'] ?? '', $o['email'] ?? ''), $cfg['minutos'] * 60);
        } catch (Exception $e) {
            error_log('abono-web reintentar: ' . $e->getMessage());
            responder(502, ['error' => $errorPago]);
        }
        responder(200, ['ok' => true, 'paymentUrl' => $p['url'], 'orden' => $p['orden'], 'monto' => (int)$o['amount'], 'minutos' => $cfg['minutos']]);
    }
    responder(400, ['error' => 'acción no válida']);
}

/* ════════ camino anterior: por reserva ya creada ════════ */
$id = (string)($d['id'] ?? '');
if (!preg_match($UUID, $id)) responder(400, ['error' => 'Reserva no válida']);

$a = abono_reserva($id);
if (!$a) responder(404, ['error' => 'No encontramos esa reserva']);
/* ¿ya vino antes? (para darle la bienvenida de quien vuelve; el id de la reserva
   solo lo conoce quien la acaba de hacer) */
$vuelve = cr_visitas($a) > 0;
$pila = explode(' ', trim((string)$a['client_name']))[0] ?? '';
if (!empty($d['consultar'])) {
    $estado = ['reserved' => 'confirmada', 'confirmed' => 'confirmada', 'pago_pendiente' => 'pendiente', 'cancelled' => 'liberada'][$a['status'] ?? ''] ?? 'confirmada';
    responder(200, ['ok' => true, 'estado' => $estado, 'servicio' => $a['service_name'], 'fecha' => $a['appt_date'],
                    'hora' => substr((string)$a['start_time'], 0, 5), 'nombre' => $pila, 'vuelve' => $vuelve]);
}
/* ya confirmada (el abono estaba apagado): el sitio manda el correo como siempre */
if (($a['status'] ?? '') === 'reserved') responder(200, ['ok' => true, 'confirmada' => true, 'yaEstaba' => true, 'vuelve' => $vuelve]);
if (($a['status'] ?? '') !== 'pago_pendiente') {
    responder(410, ['error' => 'El plazo para pagar el abono de esa hora terminó y la hora se liberó. Vuelve a reservar, por favor.']);
}
$cfg = abono_config();
$creada = strtotime((string)$a['created_at']) ?: 0;
$quedan = $creada + $cfg['minutos'] * 60 - time();
if ($quedan <= 0) {
    responder(410, ['error' => 'El plazo para pagar el abono de esa hora terminó y la hora se liberó. Vuelve a reservar, por favor.']);
}

$confirmar = function () use ($a, $vuelve) {
    cr_supa('PATCH', 'appointments?id=eq.' . rawurlencode($a['id']) . '&status=eq.pago_pendiente', ['status' => 'reserved']);
    enviar_correo_reserva($a['id']);
    responder(200, ['ok' => true, 'confirmada' => true, 'vuelve' => $vuelve]);
};

/* ── con gift card: ya está pagada, no hay abono ── */
if (!empty($d['gc'])) {
    $codigo = strtoupper(trim((string)$d['gc']));
    if (!preg_match('/^GC-[A-Z0-9]{6,12}$/', $codigo)) responder(400, ['error' => 'Código de gift card no válido']);
    if (strpos((string)$a['notes'], $codigo) === false) responder(400, ['error' => 'Esa reserva no se hizo con esa gift card']);
    $orden = null;
    foreach (glob(__DIR__ . '/orders/SI-*.json') ?: [] as $f) {
        $o = json_decode(@file_get_contents($f), true);
        if (is_array($o) && strtoupper((string)($o['data']['code'] ?? '')) === $codigo) { $orden = $o; break; }
    }
    if (!$orden || ($orden['status'] ?? '') !== 'paid') responder(400, ['error' => 'No encontramos esa gift card pagada']);
    $pagada = strtotime((string)($orden['paidAt'] ?? $orden['createdAt'] ?? 'now')) ?: time();
    if (date('Y-m-d') > date('Y-m-d', strtotime('+45 days', $pagada))) responder(400, ['error' => 'Esa gift card ya venció']);
    $confirmar();
}

/* ── el abono se desactivó mientras reservaba ── */
if (!$cfg['activo']) $confirmar();

/* ── el pago en Flow de una reserva en 'pago_pendiente' ── */
try {
    $p = abono_crear_pago(null, $a['id'], abono_monto($a, $cfg), correo_para_flow($a['client_id'] ?? '', $d['correo'] ?? ''), $quedan);
    responder(200, ['ok' => true, 'paymentUrl' => $p['url'], 'orden' => $p['orden'], 'monto' => abono_monto($a, $cfg),
                    'minutos' => (int)ceil($quedan / 60), 'vuelve' => $vuelve]);
} catch (Exception $e) {
    error_log('abono-web: Flow create failed: ' . $e->getMessage());
    /* sin pago no queda una hora a medias: se libera en el acto */
    cr_supa('PATCH', 'appointments?id=eq.' . rawurlencode($a['id']) . '&status=eq.pago_pendiente', [
        'status' => 'cancelled',
        'notes'  => trim((string)$a['notes']) . ' · No se pudo iniciar el pago en Flow: la hora se liberó',
    ]);
    responder(502, ['error' => $errorPago, 'liberada' => true]);
}
