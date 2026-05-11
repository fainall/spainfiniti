<?php
/**
 * Form submission handler — Spa Infinity
 * Recibe POST del formulario de contacto y envía email a reservainfinity@spainfinity.cl
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Honeypot anti-spam: si el campo oculto tiene contenido, es bot
if (!empty($_POST['_honey'] ?? '')) {
    echo json_encode(['success' => true]); // Mentir al bot
    exit;
}

// Sanitizar inputs
function clean($s) {
    return trim(strip_tags($s ?? ''));
}

$name    = clean($_POST['nombre']   ?? '');
$email   = clean($_POST['email']    ?? '');
$phone   = clean($_POST['telefono'] ?? '');
$subject = clean($_POST['asunto']   ?? 'Mensaje desde spainfinity.cl');
$message = clean($_POST['mensaje']  ?? '');
$source  = clean($_POST['_source']  ?? 'desconocido');

// Validar
if (!$name || !$email || !$message) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos obligatorios (nombre, email y mensaje).']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido.']);
    exit;
}
if (strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Mensaje demasiado largo.']);
    exit;
}

// Destinatario
$to = 'reservainfinity@spainfinity.cl';
$mailSubject = '[Web] ' . $subject;

// Construir cuerpo HTML elegante
$bodyHTML = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333">';
$bodyHTML .= '<div style="background:linear-gradient(135deg,#c5a467 0%,#8a7344 100%);color:#fff;padding:24px;border-radius:8px 8px 0 0">';
$bodyHTML .= '<h2 style="margin:0;font-family:Georgia,serif">Nuevo mensaje desde Spa Infinity</h2>';
$bodyHTML .= '<p style="margin:8px 0 0;opacity:0.9;font-size:14px">' . date('d/m/Y H:i') . '</p>';
$bodyHTML .= '</div>';
$bodyHTML .= '<div style="background:#fff;border:1px solid #e0e0e0;border-top:none;padding:24px;border-radius:0 0 8px 8px">';
$bodyHTML .= '<table style="width:100%;border-collapse:collapse;font-size:14px">';
$bodyHTML .= '<tr><td style="padding:8px 0;font-weight:bold;width:120px">Nombre:</td><td>' . htmlspecialchars($name) . '</td></tr>';
$bodyHTML .= '<tr><td style="padding:8px 0;font-weight:bold">Email:</td><td><a href="mailto:' . htmlspecialchars($email) . '" style="color:#8a7344">' . htmlspecialchars($email) . '</a></td></tr>';
if ($phone) {
    $bodyHTML .= '<tr><td style="padding:8px 0;font-weight:bold">Teléfono:</td><td>' . htmlspecialchars($phone) . '</td></tr>';
}
$bodyHTML .= '<tr><td style="padding:8px 0;font-weight:bold">Asunto:</td><td>' . htmlspecialchars($subject) . '</td></tr>';
$bodyHTML .= '<tr><td style="padding:8px 0;font-weight:bold;vertical-align:top">Mensaje:</td><td style="white-space:pre-wrap">' . nl2br(htmlspecialchars($message)) . '</td></tr>';
$bodyHTML .= '<tr><td style="padding:8px 0;font-weight:bold">Origen:</td><td style="color:#999;font-size:12px">' . htmlspecialchars($source) . '</td></tr>';
$bodyHTML .= '</table>';
$bodyHTML .= '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e0e0e0;font-size:12px;color:#999">Responder este email contactará directamente a ' . htmlspecialchars($email) . '</div>';
$bodyHTML .= '</div></body></html>';

// Headers del email
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/html; charset=UTF-8';
$headers[] = 'From: Spa Infinity Web <noreply@spainfinity.cl>';
$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$sent = @mail($to, $mailSubject, $bodyHTML, implode("\r\n", $headers));

if ($sent) {
    echo json_encode(['success' => true, 'message' => '¡Mensaje enviado!']);
} else {
    error_log('Form mail failed: to=' . $to . ' from=' . $email);
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo enviar el mensaje. Por favor escríbenos por WhatsApp.']);
}
