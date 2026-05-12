<?php
/**
 * Email template — Gift Card
 * Reutilizable: flow-confirm.php y preview-giftcard-email.php usan esto.
 */

function buildGiftCardEmail(array $d): string
{
    $from     = htmlspecialchars($d['from']);
    $toName   = htmlspecialchars($d['to']);
    $message  = trim($d['message'] ?? '');
    $services = $d['services'];
    $total    = htmlspecialchars($d['total']);
    $code     = htmlspecialchars($d['code']);

    // Servicios — mostrar nombre del primero o concat si son varios
    $svcDisplay = (count($services) === 1)
        ? htmlspecialchars($services[0]['name'])
        : htmlspecialchars($services[0]['name']) . ' + ' . (count($services) - 1) . ' más';

    // Lista de servicios para la tabla de abajo
    $svcRows = '';
    foreach ($services as $s) {
        $svcRows .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee;font-size:14px;color:#555">' . htmlspecialchars($s['name']) . '</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee;text-align:right;font-size:14px;color:#1a2436;font-weight:600">' . htmlspecialchars($s['price']) . '</td>'
            . '</tr>';
    }

    $msgBlock = '';
    if ($message) {
        $msgEsc = nl2br(htmlspecialchars($message));
        $msgBlock = <<<MSG
<table style="width:100%;border-collapse:collapse;margin:24px 0">
  <tr>
    <td style="background:#f9f6ef;border-left:3px solid #c5a467;padding:16px 20px;font-style:italic;color:#555;font-size:14px;line-height:1.6;font-family:'Georgia',serif">
      "{$msgEsc}"
    </td>
  </tr>
</table>
MSG;
    }

    // Fecha hoy + 45 días
    $expiryDate = date('d/m/Y', strtotime('+45 days'));

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tu Gift Card · Spa Infinity</title>
</head>
<body style="margin:0;padding:0;background:#e8e4d8;font-family:Arial,Helvetica,sans-serif;color:#1a2436">

<!-- Wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#e8e4d8">
  <tr>
    <td align="center" style="padding:32px 16px">

      <!-- Container -->
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 32px rgba(140,115,50,0.12)">

        <!-- HEADER -->
        <tr>
          <td style="background:#1a2436;padding:36px 24px;text-align:center">
            <div style="font-family:Georgia,serif;font-size:11px;letter-spacing:4px;color:#c5a467;font-weight:600;margin-bottom:6px">SPA INFINITY</div>
            <div style="font-family:Georgia,serif;font-style:italic;font-size:28px;color:#fff;line-height:1.1">Tienes un regalo<br><span style="color:#c5a467">esperándote</span></div>
          </td>
        </tr>

        <!-- INTRO -->
        <tr>
          <td style="padding:32px 28px 8px">
            <p style="margin:0 0 4px;font-size:16px;color:#1a2436"><strong>{$toName}</strong>, alguien especial pensó en ti:</p>
            <p style="margin:0;font-size:13px;color:#888">De parte de <strong style="color:#1a2436">{$from}</strong></p>
          </td>
        </tr>

        <!-- ✨ GIFT CARD VISUAL ✨ -->
        <tr>
          <td style="padding:24px 28px 16px">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#c5a050;background:linear-gradient(135deg,#d4b96a 0%,#c9a84e 20%,#e2cb7d 40%,#c5a050 55%,#b8943f 70%,#d4b868 85%,#c0993a 100%);border-radius:14px">
              <tr>
                <td style="padding:28px 28px 22px;color:#ffffff">

                  <!-- Top row: logo + label -->
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin-bottom:36px">
                    <tr>
                      <td valign="top" style="width:64px">
                        <img src="https://spainfinity.cl/logo.webp" alt="Spa Infinity" width="56" height="56" style="display:block;border-radius:50%;background:rgba(255,255,255,0.9);padding:4px;border:2px solid rgba(255,255,255,0.4)" />
                      </td>
                      <td valign="top" align="right">
                        <div style="font-size:9px;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.7);font-weight:600;margin-bottom:4px">TARJETA DE REGALO</div>
                        <div style="font-family:Georgia,serif;font-size:22px;font-weight:700;line-height:1;color:#ffffff">Gift Card</div>
                        <div style="font-family:Georgia,serif;font-style:italic;font-size:14px;color:rgba(255,255,255,0.85);margin-top:2px">Virtual</div>
                      </td>
                    </tr>
                  </table>

                  <!-- Bottom: service + amount -->
                  <div style="font-family:Georgia,serif;font-size:16px;font-weight:700;color:#ffffff;line-height:1.2;margin-bottom:4px">{$svcDisplay}</div>
                  <div style="font-family:Georgia,serif;font-size:32px;font-weight:700;color:#ffffff;margin-bottom:14px;letter-spacing:0.5px">{$total}</div>

                  <!-- Names row -->
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-top:1px solid rgba(255,255,255,0.25);padding-top:10px">
                    <tr>
                      <td valign="top" style="padding-top:10px">
                        <div style="font-family:Georgia,serif;font-style:italic;font-size:9px;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:1px">De</div>
                        <div style="font-family:Georgia,serif;font-size:12px;font-weight:700;color:#ffffff;margin-top:2px">{$from}</div>
                      </td>
                      <td valign="top" style="padding-top:10px" align="right">
                        <div style="font-family:Georgia,serif;font-style:italic;font-size:9px;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:1px">Para</div>
                        <div style="font-family:Georgia,serif;font-size:12px;font-weight:700;color:#ffffff;margin-top:2px">{$toName}</div>
                      </td>
                    </tr>
                  </table>

                  <!-- Footer of card -->
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin-top:14px;border-top:1px solid rgba(255,255,255,0.15);padding-top:8px">
                    <tr>
                      <td style="font-size:9px;color:rgba(255,255,255,0.55);letter-spacing:0.5px;font-weight:600">spainfinity.cl</td>
                      <td align="right" style="font-size:8px;color:rgba(255,255,255,0.45);text-align:right">Válido hasta {$expiryDate}</td>
                    </tr>
                  </table>

                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- MENSAJE PERSONAL -->
        <tr>
          <td style="padding:0 28px">
            {$msgBlock}
          </td>
        </tr>

        <!-- CÓDIGO DE CANJE -->
        <tr>
          <td style="padding:8px 28px 24px">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#1a2436;border-radius:10px">
              <tr>
                <td style="padding:22px;text-align:center">
                  <div style="font-size:10px;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,0.6);margin-bottom:8px">CÓDIGO DE CANJE</div>
                  <div style="font-family:'Courier New',monospace;font-size:24px;font-weight:700;letter-spacing:4px;color:#c5a467">{$code}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- INCLUYE -->
        <tr>
          <td style="padding:8px 28px 24px">
            <div style="font-family:Georgia,serif;font-size:14px;font-weight:700;color:#1a2436;text-transform:uppercase;letter-spacing:2px;margin-bottom:8px">Incluye</div>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%">
              {$svcRows}
              <tr>
                <td style="padding:14px 0 0;font-weight:700;font-size:15px;color:#1a2436">Total</td>
                <td style="padding:14px 0 0;font-weight:700;font-size:18px;text-align:right;color:#c5a467">{$total}</td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- CTA -->
        <tr>
          <td style="padding:0 28px 32px">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f9f6ef;border-radius:8px">
              <tr>
                <td style="padding:20px;text-align:center">
                  <div style="font-family:Georgia,serif;font-size:15px;color:#1a2436;font-weight:700;margin-bottom:8px">¿Cómo canjeo mi Gift Card?</div>
                  <div style="font-size:13px;color:#555;line-height:1.6;margin-bottom:14px">Reserva tu cita por WhatsApp indicando tu código de canje.</div>
                  <a href="https://wa.me/56986688771?text=Hola,%20quiero%20agendar%20mi%20Gift%20Card.%20Código:%20{$code}" style="display:inline-block;background:#25D366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:13px;font-weight:600;letter-spacing:0.5px">💬 Reservar por WhatsApp</a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- FOOTER -->
        <tr>
          <td style="padding:24px;background:#f5f3ed;border-top:1px solid #eee;text-align:center">
            <div style="font-family:Georgia,serif;font-size:11px;letter-spacing:3px;color:#c5a467;font-weight:600;margin-bottom:4px">SPA INFINITY</div>
            <div style="font-size:11px;color:#999;line-height:1.6">
              Santo Domingo 1083 · Santiago Centro<br>
              <a href="https://spainfinity.cl" style="color:#c5a467;text-decoration:none">spainfinity.cl</a>
            </div>
            <div style="font-size:10px;color:#bbb;margin-top:12px">Válida 45 días desde la fecha de compra. No reembolsable.</div>
          </td>
        </tr>

      </table>
      <!-- /Container -->

    </td>
  </tr>
</table>

</body>
</html>
HTML;
}
