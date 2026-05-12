<?php
/**
 * Webhook server-to-server de Flow.
 * Flow POSTea aquí con el token cuando el pago se completa (éxito o falla).
 * No retornamos contenido a Flow; solo HTTP 200.
 */
require_once __DIR__ . '/flow-lib.php';
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
    $d        = $order['data'];
    $to       = $d['toEmail'];
    $from     = $d['from'];
    $toName   = $d['to'];
    $message  = $d['message'] ?? '';
    $services = $d['services'];
    $total    = $d['total'];
    $code     = $d['code'];

    $svcRows = '';
    foreach ($services as $s) {
        $svcRows .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #eee">' . htmlspecialchars($s['name']) . '</td><td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:right">' . htmlspecialchars($s['price']) . '</td></tr>';
    }

    $msgBlock = $message
        ? '<div style="background:#f9f6ef;border-left:3px solid #c5a467;padding:14px 18px;margin:20px 0;font-style:italic;color:#555">"' . nl2br(htmlspecialchars($message)) . '"</div>'
        : '';

    $body = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:0;background:#f5f3ed">
  <div style="background:linear-gradient(135deg,#c5a467 0%,#8a7344 100%);color:#fff;padding:32px 24px;text-align:center">
    <div style="font-family:Georgia,serif;font-size:14px;letter-spacing:2px;opacity:0.85">TARJETA DE REGALO</div>
    <h1 style="margin:8px 0 0;font-family:Georgia,serif;font-size:32px;font-weight:normal">Spa Infinity</h1>
  </div>
  <div style="background:#fff;padding:32px 28px">
    <p style="font-size:16px;color:#555;margin:0 0 8px"><strong>{$toName}</strong>, recibiste un regalo:</p>
    <p style="font-size:14px;color:#888;margin:0 0 24px">De parte de <strong>{$from}</strong></p>
    {$msgBlock}
    <h3 style="font-family:Georgia,serif;color:#8a7344;margin:24px 0 12px;font-size:18px">Tu Gift Card incluye:</h3>
    <table style="width:100%;border-collapse:collapse;font-size:14px;margin-bottom:24px">
      {$svcRows}
      <tr><td style="padding:12px;font-weight:bold;font-size:16px">Total</td><td style="padding:12px;font-weight:bold;font-size:18px;text-align:right;color:#8a7344">{$total}</td></tr>
    </table>
    <div style="background:#1a2436;color:#c5a467;padding:24px;border-radius:8px;text-align:center;margin:24px 0">
      <div style="font-size:11px;letter-spacing:3px;opacity:0.7;margin-bottom:8px">CÓDIGO DE CANJE</div>
      <div style="font-family:'Courier New',monospace;font-size:26px;font-weight:bold;letter-spacing:4px;color:#fff">{$code}</div>
    </div>
    <p style="font-size:13px;color:#666;line-height:1.7">
      <strong>¿Cómo canjear?</strong><br>
      Reservá tu cita por WhatsApp al <a href="https://wa.me/56986688771" style="color:#8a7344">+56 9 8668 8771</a> indicando el código de arriba.
    </p>
    <p style="font-size:12px;color:#999;margin-top:24px;border-top:1px solid #eee;padding-top:16px">
      Válida 45 días desde la fecha de compra. No reembolsable.
    </p>
  </div>
  <div style="text-align:center;padding:16px;color:#999;font-size:11px">
    Spa Infinity · Santiago Centro · <a href="https://spainfinity.cl" style="color:#8a7344">spainfinity.cl</a>
  </div>
</body></html>
HTML;

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
