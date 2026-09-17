<?php
/**
 * Control del asistente de WhatsApp desde el panel — Spa Infinity
 *
 *   GET                          estado: pausas activas, minutos de pausa, números de prueba
 *   POST {accion:'pausar',  tel, minutos}   pausa un chat (minutos 0 = hasta reactivar)
 *   POST {accion:'reanudar', tel}           el asistente vuelve a responder en ese chat
 *   POST {accion:'config', pausaMin, pruebas}  solo administradores
 */
require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/wa-pausas.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$yo = require_panel_user(false);
$esAdmin = ($yo['role'] ?? '') === 'admin';
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'recepcion'], true)) {
    http_response_code(403); echo json_encode(['error' => 'Solo administración y recepción manejan el asistente']); exit;
}

function estado() {
    $c = wa_control_leer();
    $pausas = [];
    foreach ($c['pausas'] as $tel => $p) {
        if (($p['hasta'] ?? 0) !== 0 && $p['hasta'] < time()) continue;
        $pausas[] = ['tel' => (string)$tel, 'hasta' => $p['hasta'] ? date('c', $p['hasta']) : null,
                     'desde' => date('c', $p['desde'] ?? time()), 'motivo' => $p['motivo'] ?? ''];
    }
    usort($pausas, fn($a, $b) => strcmp($b['desde'], $a['desde']));
    return ['pausaMin' => $c['pausaMin'], 'pruebas' => $c['pruebas'], 'pausas' => $pausas];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') { echo json_encode(estado()); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$accion = $in['accion'] ?? '';

if ($accion === 'pausar') {
    $tel = wa_tel($in['tel'] ?? '');
    if (strlen($tel) < 8) { http_response_code(400); echo json_encode(['error' => 'Escribe un teléfono válido']); exit; }
    wa_pausar($tel, max(0, (int)($in['minutos'] ?? 0)), 'Pausado desde el panel por ' . ($yo['name'] ?? $yo['email'] ?? ''));
} elseif ($accion === 'reanudar') {
    wa_reanudar($in['tel'] ?? '');
} elseif ($accion === 'config') {
    if (!$esAdmin) { http_response_code(403); echo json_encode(['error' => 'Solo un administrador cambia esta configuración']); exit; }
    $c = wa_control_leer();
    if (isset($in['pausaMin'])) $c['pausaMin'] = max(0, min(10080, (int)$in['pausaMin']));
    if (isset($in['pruebas']))  $c['pruebas']  = array_values(array_unique(array_filter(array_map('wa_tel', (array)$in['pruebas']), fn($t) => strlen($t) >= 8)));
    wa_control_guardar($c);
} else {
    http_response_code(400); echo json_encode(['error' => 'Acción no válida']); exit;
}
echo json_encode(estado());
