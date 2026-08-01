<?php
/**
 * Recordatorio de cita por email — Spa Infinity
 * Recibe POST JSON { email, name, message } y envía el recordatorio al cliente.
 */
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$email   = trim($data['email'] ?? '');
$name    = trim(strip_tags($data['name'] ?? ''));
$message = trim($data['message'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400); echo json_encode(['error' => 'Email inválido']); exit;
}
if (!$message) {
    http_response_code(400); echo json_encode(['error' => 'Falta el mensaje']); exit;
}
if (strlen($message) > 3000) {
    http_response_code(400); echo json_encode(['error' => 'Mensaje demasiado largo']); exit;
}

$subject = 'Recordatorio de tu cita — Spa Infinity';

$bodyHTML  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333">';
$bodyHTML .= '<div style="background:linear-gradient(135deg,#c5a467 0%,#8a7344 100%);color:#fff;padding:26px;border-radius:8px 8px 0 0;text-align:center">';
$bodyHTML .= '<h2 style="margin:0;font-family:Georgia,serif">Spa Infinity</h2>';
$bodyHTML .= '<p style="margin:6px 0 0;opacity:.9;font-size:13px;letter-spacing:2px;text-transform:uppercase">Recordatorio de tu cita</p>';
$bodyHTML .= '</div>';
$bodyHTML .= '<div style="background:#fff;border:1px solid #e0e0e0;border-top:none;padding:26px;border-radius:0 0 8px 8px;font-size:15px;line-height:1.6">';
$bodyHTML .= '<p style="white-space:pre-wrap;margin:0">' . nl2br(htmlspecialchars($message)) . '</p>';
$bodyHTML .= '<div style="margin-top:24px;padding-top:16px;border-top:1px solid #eee;font-size:12px;color:#999">Spa Infinity · Centro Podológico &amp; Spa · Santo Domingo 1083, Santiago Centro</div>';
$bodyHTML .= '</div></body></html>';

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'From: Spa Infinity <reservainfinity@spainfinity.cl>';
$headers[] = 'Reply-To: reservainfinity@spainfinity.cl';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$sent = @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyHTML, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true]);
} else {
    error_log('Reminder mail failed: to=' . $email);
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo enviar el email']);
}
