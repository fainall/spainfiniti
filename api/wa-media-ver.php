<?php
/**
 * Entrega una foto o un audio que mandó un cliente por WhatsApp, para verlo en
 * la bandeja del panel. Los archivos viven fuera de la web (bot-sessions), así
 * que solo se pueden ver desde aquí y con sesión de panel.
 */
require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/wa-media.php';

$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'recepcion'], true)) {
    http_response_code(403); exit('no');
}
$f = basename((string)($_GET['f'] ?? ''));
if (!preg_match('/^[0-9]{8,15}-[A-Za-z0-9]{1,24}\.[a-z0-9]{2,4}$/', $f)) { http_response_code(400); exit('no'); }
$ruta = WA_MEDIA_DIR . '/' . $f;
if (!is_file($ruta)) { http_response_code(404); exit('no'); }

$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
$tipos = ['jpg'=>'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp', 'gif'=>'image/gif',
          'ogg'=>'audio/ogg', 'mp3'=>'audio/mpeg', 'm4a'=>'audio/mp4', 'aac'=>'audio/aac',
          'amr'=>'audio/amr', 'wav'=>'audio/wav', 'mp4'=>'video/mp4', '3gp'=>'video/3gpp', 'pdf'=>'application/pdf'];
header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . $f . '"');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
