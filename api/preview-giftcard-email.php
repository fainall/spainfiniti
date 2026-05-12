<?php
/**
 * Preview del email Gift Card — solo para revisar el diseño.
 * Genera el mismo HTML que se envía al destinatario.
 * Acceder en: https://spainfinity.cl/api/preview-giftcard-email.php
 */

// Datos de ejemplo
$order = [
    'data' => [
        'from'      => 'Carolina Pérez',
        'to'        => 'María González',
        'fromEmail' => 'carolina@example.com',
        'toEmail'   => 'maria@example.com',
        'message'   => "¡Feliz cumpleaños, mi amiga querida!\nQue disfrutes este momento de relajación 100%, te lo mereces. Con todo mi cariño.",
        'code'      => 'GC-7K2M9-Q4P',
        'total'     => '$48.500',
        'services'  => [
            ['name' => 'Masaje Descontracturante 60 min', 'price' => '$28.500', 'duration' => '60 min'],
            ['name' => 'Limpieza Facial Profunda',         'price' => '$20.000', 'duration' => '45 min'],
        ],
    ],
];

$d        = $order['data'];
$to       = $d['toEmail'];
$from     = $d['from'];
$toName   = $d['to'];
$message  = $d['message'];
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

// Output as HTML so browser renders it
header('Content-Type: text/html; charset=UTF-8');

echo <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Preview Email — Gift Card</title>
<style>
  body { background: #e8e4d8; padding: 40px 20px; margin: 0; font-family: system-ui, sans-serif; }
  .preview-label { max-width: 600px; margin: 0 auto 16px; background: #fff; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #c5a467; font-size: 13px; color: #555; }
  .preview-label strong { color: #1a2436; }
  .preview-label .meta { font-size: 11px; color: #999; margin-top: 4px; }
</style>
</head>
<body>
  <div class="preview-label">
    📧 <strong>Preview del email Gift Card</strong> — esto es lo que ve el destinatario cuando recibe su regalo.
    <div class="meta">Asunto: 🎁 Recibiste una Gift Card de Spa Infinity · De: Spa Infinity &lt;noreply@spainfinity.cl&gt;</div>
  </div>

<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:0;background:#f5f3ed">
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
</div>
</body></html>
HTML;
