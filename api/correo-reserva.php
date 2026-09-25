<?php
/**
 * Correos de una reserva — Spa Infinity
 *
 *   POST {id}                         confirmación de una reserva recién creada (sitio web y panel)
 *   POST {id, reenviar:1}             desde el panel, con sesión: vuelve a mandarla
 *   POST {id, tipo:'cancelacion'}     el cliente canceló desde su cuenta
 *   POST {id, tipo:'cambio', anterior} el cliente cambió su hora desde su cuenta
 *
 * Quién puede pedir qué:
 *   · equipo del panel (con sesión): cualquier reserva;
 *   · cliente con cuenta (con sesión): solo reservas de su cuenta;
 *   · sin sesión (el sitio web): solo la confirmación de una reserva creada hace
 *     menos de 15 minutos y que todavía no tenga correo.
 * Así nadie puede usarlo para mandar correos a su antojo. Los correos siempre van
 * a la dirección de la ficha o de la cuenta del cliente y a la del spa, nunca a
 * una que venga en la petición.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/correo-reserva-lib.php';
require_once __DIR__ . '/require-auth.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);
$d = json_decode((string)file_get_contents('php://input'), true) ?: [];
$id = (string)($d['id'] ?? '');
$tipo = (string)($d['tipo'] ?? 'confirmacion');
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
if (!preg_match($uuid, $id)) responder(400, ['error' => 'reserva no válida']);
if (!in_array($tipo, ['confirmacion', 'cancelacion', 'cambio'], true)) responder(400, ['error' => 'tipo no válido']);

$quien = sesion_de_quien_llama();
if (_bearer_token() !== '' && !$quien) responder(401, ['error' => 'Tu sesión expiró; vuelve a ingresar']);
$esEquipo = $quien && $quien['panel'];
$cliente = ($quien && !$quien['panel']) ? $quien['usuario']['id'] : null;

/* un cliente solo toca reservas de su cuenta */
$deLaCuenta = function ($idReserva) use ($cliente) {
    $a = cr_supa('GET', 'appointments?select=user_id&id=eq.' . rawurlencode($idReserva))[0] ?? null;
    return $a && !empty($a['user_id']) && $a['user_id'] === $cliente;
};

if ($tipo === 'cancelacion' || $tipo === 'cambio') {
    if (!$esEquipo && !$cliente) responder(401, ['error' => 'Necesitas ingresar a tu cuenta']);
    if ($cliente && !$deLaCuenta($id)) responder(403, ['error' => 'Esa reserva no es de tu cuenta']);
    if ($tipo === 'cancelacion') responder(200, enviar_correo_cancelacion($id));
    $anterior = (string)($d['anterior'] ?? '');
    if (!preg_match($uuid, $anterior)) responder(400, ['error' => 'falta la reserva anterior']);
    if ($cliente && !$deLaCuenta($anterior)) responder(403, ['error' => 'Esa reserva no es de tu cuenta']);
    responder(200, enviar_correo_cambio($id, $anterior));
}

/* confirmación */
if ($cliente && !$deLaCuenta($id)) responder(403, ['error' => 'Esa reserva no es de tu cuenta']);
if (!$esEquipo) {
    $a = cr_supa('GET', 'appointments?select=created_at,correo_confirmacion_at&id=eq.' . rawurlencode($id))[0] ?? null;
    if (!$a) responder(404, ['error' => 'no existe']);
    if (!empty($a['correo_confirmacion_at'])) responder(200, ['ok' => true, 'ya_enviado' => true]);
    if (strtotime($a['created_at']) < time() - 15 * 60) responder(403, ['error' => 'fuera de plazo']);
}
responder(200, enviar_correo_reserva($id, $esEquipo && !empty($d['reenviar'])));
