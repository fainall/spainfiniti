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

    // Lista de servicios para la sección "Incluye" — sin precios (es un regalo)
    $svcRows = '';
    foreach ($services as $s) {
        $duration = !empty($s['duration']) ? ' · <span style="color:#999;font-weight:400">' . htmlspecialchars($s['duration']) . '</span>' : '';
        $svcRows .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee;font-size:14px;color:#1a2436;font-weight:600">'
            . '<span style="color:#c5a467;margin-right:8px">✦</span>'
            . htmlspecialchars($s['name']) . $duration
            . '</td>'
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

        <!-- ✨ GIFT CARD VISUAL (image) ✨ -->
        <tr>
          <td style="padding:24px 28px 8px;text-align:center">
            <img src="https://spainfinity.cl/images/gift-card.png?v=3" alt="Gift Card Spa Infinity" width="540" style="display:block;width:100%;max-width:540px;height:auto;margin:0 auto;border-radius:10px;box-shadow:0 8px 24px rgba(140,115,50,0.18)" />
          </td>
        </tr>

        <!-- DATOS DEL REGALO -->
        <tr>
          <td style="padding:16px 28px 8px">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f9f6ef;border-radius:10px">
              <tr>
                <td style="padding:18px 22px">
                  <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%">
                    <tr>
                      <td valign="top" style="width:50%;padding-right:8px">
                        <div style="font-family:Georgia,serif;font-style:italic;font-size:10px;color:#999;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px">De</div>
                        <div style="font-family:Georgia,serif;font-size:14px;font-weight:700;color:#1a2436">{$from}</div>
                      </td>
                      <td valign="top" style="width:50%;padding-left:8px;border-left:1px solid #e8e4d8">
                        <div style="font-family:Georgia,serif;font-style:italic;font-size:10px;color:#999;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px;padding-left:12px">Para</div>
                        <div style="font-family:Georgia,serif;font-size:14px;font-weight:700;color:#1a2436;padding-left:12px">{$toName}</div>
                      </td>
                    </tr>
                  </table>
                  <div style="border-top:1px solid #e8e4d8;margin:14px 0 12px"></div>
                  <div style="font-family:Georgia,serif;font-style:italic;font-size:10px;color:#999;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px">Tu Experiencia</div>
                  <div style="font-family:Georgia,serif;font-size:16px;font-weight:700;color:#c5a467">{$svcDisplay}</div>
                  <div style="font-size:11px;color:#aaa;margin-top:8px;letter-spacing:0.5px">Válido hasta {$expiryDate}</div>
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

        <!-- INCLUYE (sin precios — es un regalo) -->
        <tr>
          <td style="padding:8px 28px 24px">
            <div style="font-family:Georgia,serif;font-size:13px;font-weight:700;color:#1a2436;text-transform:uppercase;letter-spacing:2px;margin-bottom:10px">Tu regalo incluye</div>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%">
              {$svcRows}
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
