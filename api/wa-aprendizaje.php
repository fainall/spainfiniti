<?php
/**
 * Lo que la IA aprendió de las respuestas del equipo — Spa Infinity
 *
 *   GET                              lista de ejemplos
 *   POST {accion:'activo', id, activo}   usar o dejar de usar un ejemplo
 *   POST {accion:'editar', id, respuesta}  corregir la respuesta guardada
 *   POST {accion:'borrar', id}
 */
require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/wa-pausas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local'], true)) {
    http_response_code(403); echo json_encode(['error' => 'Solo administración revisa lo que aprende el asistente']); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['ejemplos' => array_reverse(wa_aprende_leer())], JSON_UNESCAPED_UNICODE); exit;
}
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$l = wa_aprende_leer();
$id = (string)($in['id'] ?? '');
$pos = null;
foreach ($l as $i => $e) if (($e['id'] ?? '') === $id) { $pos = $i; break; }
if ($pos === null) { http_response_code(404); echo json_encode(['error' => 'No encontré ese ejemplo']); exit; }
switch ($in['accion'] ?? '') {
    case 'activo':  $l[$pos]['activo'] = !empty($in['activo']); break;
    case 'editar':
        $r = trim((string)($in['respuesta'] ?? ''));
        if (mb_strlen($r) < 5) { http_response_code(400); echo json_encode(['error' => 'La respuesta está vacía']); exit; }
        $l[$pos]['respuesta'] = mb_substr(wa_aprende_limpiar($r), 0, 1200);
        $l[$pos]['editado'] = trim((string)($yo['name'] ?? $yo['email'] ?? ''));
        break;
    case 'borrar':  array_splice($l, $pos, 1); break;
    default: http_response_code(400); echo json_encode(['error' => 'Acción no válida']); exit;
}
wa_aprende_guardar($l);
echo json_encode(['ok' => true, 'ejemplos' => array_reverse(wa_aprende_leer())], JSON_UNESCAPED_UNICODE);
