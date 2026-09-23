<?php
/**
 * Confirmación por correo de una reserva recién creada — Spa Infinity
 *
 *   POST {id}              la llama el sitio web y el panel apenas se crea la reserva
 *   POST {id, reenviar:1}  desde el panel, con sesión: vuelve a mandarla
 *
 * Sin sesión (el sitio web) solo sirve para una reserva creada hace menos de
 * 15 minutos y que todavía no tenga correo: así nadie puede usarla para mandar
 * correos a su antojo. Los correos siempre van a la dirección guardada en la
 * ficha del cliente y a la del spa, nunca a una que venga en la petición.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/correo-reserva-lib.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'solo POST']); exit; }
$d = json_decode((string)file_get_contents('php://input'), true) ?: [];
$id = (string)($d['id'] ?? '');
if (!preg_match('/^[0-9a-f-]{36}$/i', $id)) { http_response_code(400); echo json_encode(['error' => 'reserva no válida']); exit; }

$conSesion = false;
if (!empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    require_once __DIR__ . '/require-auth.php';
    require_panel_user(false);           // corta con 401 si la sesión no sirve
    $conSesion = true;
}
if (!$conSesion) {
    $a = cr_supa('GET', 'appointments?select=created_at,correo_confirmacion_at&id=eq.' . rawurlencode($id))[0] ?? null;
    if (!$a) { http_response_code(404); echo json_encode(['error' => 'no existe']); exit; }
    if (!empty($a['correo_confirmacion_at'])) { echo json_encode(['ok' => true, 'ya_enviado' => true]); exit; }
    if (strtotime($a['created_at']) < time() - 15 * 60) { http_response_code(403); echo json_encode(['error' => 'fuera de plazo']); exit; }
}
echo json_encode(enviar_correo_reserva($id, $conSesion && !empty($d['reenviar'])), JSON_UNESCAPED_UNICODE);
