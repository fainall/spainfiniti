<?php
/**
 * Webhook server-to-server de Flow.
 * Flow POSTea aquí con el token cuando el pago se completa (éxito o falla).
 * No retornamos contenido a Flow; solo HTTP 200.
 *
 * Atiende dos tipos de orden:
 *   - giftcard (SI-…): manda la tarjeta al destinatario y avisa al negocio
 *   - reserva  (RES-…): marca pagado el link de cobro en la base y avisa
 * Antes las de reserva reventaban aquí (se intentaba armar un correo de gift
 * card con datos que no tenían) y el pago quedaba sin registrar.
 * Cada orden se atiende una sola vez, aunque Flow repita el aviso.
 */
require_once __DIR__ . '/flow-lib.php';
require_once __DIR__ . '/email-template.php';
require_once __DIR__ . '/supa-key.php';
$config = @include __DIR__ . '/flow-config.php';
if (!is_array($config)) { http_response_code(500); exit('flow-config missing'); }

if (empty($_POST['token'])) { http_response_code(400); exit('no token'); }
$token = $_POST['token'];

try {
    $flow   = new FlowClient($config);
    $status = $flow->getPaymentStatus($token);
} catch (Exception $e) {
    error_log('Flow confirm: getStatus failed: ' . $e->getMessage());
    http_response_code(500);
    exit('status error');
}

// status['status'] según Flow: 1 = pendiente, 2 = pagada, 3 = rechazada, 4 = anulada
$estado = (int)($status['status'] ?? 0);
$commerceOrder = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($status['commerceOrder'] ?? ''));

$orderFile = __DIR__ . '/orders/' . $commerceOrder . '.json';
$order = ($commerceOrder !== '' && is_file($orderFile)) ? json_decode(file_get_contents($orderFile), true) : null;

if (!$order) { http_response_code(200); exit('ok'); }

/* ya atendida: no se vuelve a mandar nada */
if (($order['status'] ?? '') === 'paid') { http_response_code(200); exit('ok'); }

if ($estado === 3 || $estado === 4) {
    $order['status']   = $estado === 3 ? 'rejected' : 'cancelled';
    $order['flowData'] = $status;
    file_put_contents($orderFile, json_encode($order, JSON_UNESCAPED_UNICODE));
    http_response_code(200); exit('ok');
}
if ($estado !== 2) { http_response_code(200); exit('ok'); }

/* el monto que pagó Flow tiene que ser el de la orden */
$pagado = (int)($status['amount'] ?? 0);
if ($pagado > 0 && $pagado !== (int)($order['amount'] ?? 0)) {
    error_log("Flow confirm: monto pagado $pagado distinto de la orden {$order['amount']} ($commerceOrder)");
    $order['status']   = 'amount_mismatch';
    $order['flowData'] = $status;
    file_put_contents($orderFile, json_encode($order, JSON_UNESCAPED_UNICODE));
    http_response_code(200); exit('ok');
}

$order['status']    = 'paid';
$order['flowToken'] = $token;
$order['paidAt']    = date('c');
$order['flowData']  = $status;
file_put_contents($orderFile, json_encode($order, JSON_UNESCAPED_UNICODE));

if (($order['tipo'] ?? 'giftcard') === 'reserva') {
    marcarLinkPagado($order);
    notificarCobroReserva($order, $config);
} else {
    sendGiftCardEmail($order, $config);
    sendBusinessNotification($order, $config);
}

http_response_code(200);
echo 'ok';

// ============== Helpers ==============

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* el link de cobro de esa reserva pasa a pagado en la base */
function marcarLinkPagado(array $order): void
{
    $apptId = (string)($order['appointment_id'] ?? '');
    if ($apptId === '') return;
    $url = supa_url() . '/rest/v1/payment_links?appointment_id=eq.' . urlencode($apptId) . '&status=eq.sent';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(),
                               'Content-Type: application/json', 'Prefer: return=minimal'],
        CURLOPT_POSTFIELDS => json_encode(['status' => 'paid', 'paid_at' => date('c')]),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        error_log("Flow confirm: no se pudo marcar el link de la reserva $apptId como pagado (HTTP $code). Revisa serviceKey en bot-config.php");
    }
}

function notificarCobroReserva(array $order, array $config): void
{
    $to = $config['notifyEmail'] ?? 'reservainfinity@spainfinity.cl';
    $monto = '$' . number_format((int)($order['amount'] ?? 0), 0, ',', '.');
    $body = "<h2>Pago recibido por link de cobro</h2>"
        . "<p><strong>Cliente:</strong> " . h($order['client_name'] ?? '') . "</p>"
        . "<p><strong>Servicio:</strong> " . h($order['service_name'] ?? '') . "</p>"
        . "<p><strong>Monto:</strong> " . h($monto) . "</p>"
        . "<p><strong>Orden Flow:</strong> " . h($order['commerceOrder'] ?? '') . "</p>"
        . "<p><strong>Fecha:</strong> " . h($order['paidAt'] ?? '') . "</p>"
        . "<p>En el panel, la reserva aparece como cobrada por link. Si no, revísala en Ventas → Cobros.</p>";
    $headers = ['MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'From: noreply@spainfinity.cl'];
    @mail($to, '[Cobro] Pago recibido: ' . mb_substr((string)($order['client_name'] ?? ''), 0, 40), $body, implode("\r\n", $headers));
}

function sendGiftCardEmail(array $order, array $config): void
{
    $d = $order['data'] ?? null;
    if (!is_array($d) || empty($d['toEmail'])) return;
    $to   = $d['toEmail'];
    $body = buildGiftCardEmail($d);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Spa Infinity <noreply@spainfinity.cl>',
        'Reply-To: ' . ($config['notifyEmail'] ?? 'reservainfinity@spainfinity.cl'),
    ];
    @mail($to, '🎁 Recibiste una Gift Card de Spa Infinity', $body, implode("\r\n", $headers));
}

function sendBusinessNotification(array $order, array $config): void
{
    $d = $order['data'] ?? [];
    $to = $config['notifyEmail'] ?? 'reservainfinity@spainfinity.cl';
    $svcNames = array_map(fn($s) => (string)($s['name'] ?? ''), (array)($d['services'] ?? []));

    $body = "<h2>Nueva Gift Card vendida</h2>"
        . "<p><strong>Total:</strong> " . h($d['total'] ?? '') . "</p>"
        . "<p><strong>Código:</strong> " . h($d['code'] ?? '') . "</p>"
        . "<p><strong>De:</strong> " . h($d['from'] ?? '') . " (" . h($d['fromEmail'] ?? '') . ")</p>"
        . "<p><strong>Para:</strong> " . h($d['to'] ?? '') . " (" . h($d['toEmail'] ?? '') . ")</p>"
        . "<p><strong>Servicios:</strong> " . h(implode(', ', $svcNames)) . "</p>"
        . "<p><strong>Orden Flow:</strong> " . h($order['commerceOrder'] ?? '') . "</p>"
        . "<p><strong>Fecha:</strong> " . h($order['paidAt'] ?? '') . "</p>";

    $headers = ['MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'From: noreply@spainfinity.cl'];
    @mail($to, '[Venta] Nueva Gift Card vendida', $body, implode("\r\n", $headers));
}
