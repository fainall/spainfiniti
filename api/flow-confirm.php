<?php
/**
 * Webhook server-to-server de Flow.
 * Flow POSTea aquí con el token cuando el pago se completa (éxito o falla).
 * No retornamos contenido a Flow; solo HTTP 200.
 */
require_once __DIR__ . '/flow-lib.php';
require_once __DIR__ . '/email-template.php';
$config = @include __DIR__ . '/flow-config.php';
if (!is_array($config)) {
    http_response_code(500);
    exit('flow-config missing');
}

if (empty($_POST['token'])) {
    http_response_code(400);
    exit('no token');
}
$token = $_POST['token'];

try {
    $flow   = new FlowClient($config);
    $status = $flow->getPaymentStatus($token);
} catch (Exception $e) {
    error_log('Flow confirm: getStatus failed: ' . $e->getMessage());
    http_response_code(500);
    exit('status error');
}

// status['status'] codes según Flow:
//  1 = pendiente, 2 = pagada, 3 = rechazada, 4 = anulada
$paid = ((int)($status['status'] ?? 0)) === 2;
$commerceOrder = $status['commerceOrder'] ?? '';

// Recuperar la pre-orden guardada en create-payment
$orderFile = __DIR__ . '/orders/' . $commerceOrder . '.json';
$order = is_file($orderFile) ? json_decode(file_get_contents($orderFile), true) : null;

if ($paid && $order) {
    // Marcar como pagada
    $order['status']    = 'paid';
    $order['flowToken'] = $token;
    $order['paidAt']    = date('c');
    $order['flowData']  = $status;
    file_put_contents($orderFile, json_encode($order));

    // Enviar email con la Gift Card al destinatario
    sendGiftCardEmail($order, $config);

    // Notificar internamente al negocio
    sendBusinessNotification($order, $config);
}

http_response_code(200);
echo 'ok';

// ============== Helpers ==============

function sendGiftCardEmail(array $order, array $config): void
{
    $to   = $order['data']['toEmail'];
    $body = buildGiftCardEmail($order['data']);

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
    $d     = $order['data'];
    $to    = $config['notifyEmail'] ?? 'reservainfinity@spainfinity.cl';
    $total = $d['total'];
    $code  = $d['code'];
    $svcNames = array_map(fn($s) => $s['name'], $d['services']);

    $body = "<h2>Nueva Gift Card vendida</h2>"
        . "<p><strong>Total:</strong> $total</p>"
        . "<p><strong>Código:</strong> $code</p>"
        . "<p><strong>De:</strong> {$d['from']} ({$d['fromEmail']})</p>"
        . "<p><strong>Para:</strong> {$d['to']} ({$d['toEmail']})</p>"
        . "<p><strong>Servicios:</strong> " . implode(', ', $svcNames) . "</p>"
        . "<p><strong>Orden Flow:</strong> {$order['commerceOrder']}</p>"
        . "<p><strong>Fecha:</strong> {$order['paidAt']}</p>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: noreply@spainfinity.cl',
    ];
    @mail($to, '[Venta] Nueva Gift Card vendida', $body, implode("\r\n", $headers));
}
