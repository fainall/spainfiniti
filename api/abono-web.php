<?php
/**
 * POST /api/abono-web.php — el sitio lo llama justo después de reservar.
 *
 *   {id}              reserva recién creada que espera el abono: crea el pago en
 *                     Flow y devuelve { paymentUrl } para llevar al cliente a pagar
 *   {id, gc}          reserva pagada con gift card: no paga abono, se confirma
 *   {id, correo?}     el correo del cliente para el comprobante de Flow, si su
 *                     ficha no tiene (solo se usa para el pago)
 *   {id, consultar:1} solo dice en qué está la reserva (la página de vuelta del pago)
 *
 * No recibe montos: el abono sale de la configuración del panel y del precio
 * guardado en la reserva. Solo atiende reservas en 'pago_pendiente' dentro de
 * su plazo, así que no sirve para confirmar horas ajenas ni viejas.
 * Si el abono se desactivó mientras el cliente reservaba, la hora se confirma.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/abono-lib.php';
require_once __DIR__ . '/flow-lib.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);
$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d)) responder(400, ['error' => 'Los datos llegaron ilegibles; vuelve a intentarlo']);
$id = (string)($d['id'] ?? '');
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) responder(400, ['error' => 'Reserva no válida']);

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

/* "Abono de tu hora: Tratamiento con Ácido Nítrico+Alta Frecuencia-Tipo 1 · mié 30 sep, 10:00 hrs"
   Flow lo muestra como la descripción del pago, bajo "Estás realizando un pago a
   SPA INFINITY" (por eso no repite el nombre del spa). Admite hasta 100 caracteres. */
function abono_asunto($a, $monto) {
    $completo = abono_a_numero($a['price'] ?? '') > 0 && $monto >= abono_a_numero($a['price']);
    $inicio = $completo ? 'Pago de tu hora' : 'Abono de tu hora';
    $t = strtotime((string)$a['appt_date']);
    $cuando = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][(int)date('w', $t)] . ' ' . date('j', $t) . ' '
        . ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][(int)date('n', $t) - 1]
        . ', ' . substr((string)$a['start_time'], 0, 5) . ' hrs';
    $servicio = trim(preg_replace('/\s+/', ' ', (string)$a['service_name']));
    $cabe = 100 - mb_strlen("$inicio:  · $cuando");
    if (mb_strlen($servicio) > $cabe) $servicio = rtrim(mb_substr($servicio, 0, $cabe - 1)) . '…';
    return "$inicio: $servicio · $cuando";
}

/* ── el pago en Flow ── */
$config = @include __DIR__ . '/flow-config.php';
if (!is_array($config)) responder(500, ['error' => 'El pago en línea no está configurado. Escríbenos por WhatsApp para confirmar tu hora.']);

$monto = abono_monto($a, $cfg);
$correo = cr_correo_cliente($a);
if ($correo === '' && filter_var((string)($d['correo'] ?? ''), FILTER_VALIDATE_EMAIL)) $correo = (string)$d['correo'];
if ($correo === '') $correo = cliente_correo();   // Flow exige un correo; el comprobante llega al spa

$commerceOrder = 'AB-' . date('YmdHis') . '-' . substr(uniqid(), -5);
$siteUrl = rtrim($config['siteUrl'], '/');
if (!is_dir(__DIR__ . '/orders')) mkdir(__DIR__ . '/orders', 0755, true);
file_put_contents(__DIR__ . '/orders/' . $commerceOrder . '.json', json_encode([
    'commerceOrder'  => $commerceOrder,
    'tipo'           => 'abono_web',
    'amount'         => $monto,
    'appointment_id' => $a['id'],
    'client_name'    => $a['client_name'],
    'service_name'   => $a['service_name'],
    'price'          => $a['price'],
    'email'          => $correo,
    'createdAt'      => date('c'),
    'status'         => 'pending',
], JSON_UNESCAPED_UNICODE));

try {
    $flow = new FlowClient($config);
    $r = $flow->createPayment([
        'commerceOrder'   => $commerceOrder,
        /* lo que el cliente lee en la página de Flow: claro y sin códigos internos */
        'subject'         => abono_asunto($a, $monto),
        'amount'          => $monto,
        'email'           => $correo,
        'urlConfirmation' => $siteUrl . '/api/flow-confirm.php',
        'urlReturn'       => $siteUrl . '/api/abono-retorno.php',
        /* la orden de Flow vence con la hora: después ya no se puede pagar */
        'timeout'         => max(120, $quedan),
    ]);
    responder(200, ['ok' => true, 'paymentUrl' => $flow->paymentRedirectUrl($r), 'monto' => $monto, 'minutos' => (int)ceil($quedan / 60), 'vuelve' => $vuelve]);
} catch (Exception $e) {
    error_log('abono-web: Flow create failed: ' . $e->getMessage());
    responder(502, ['error' => 'No pudimos iniciar el pago. Inténtalo de nuevo en un momento.']);
}
