<?php
/**
 * Galería de "Antes y después" para la cuenta de publicidad (pedido de Luis, 15-oct).
 *
 * Entrega SOLO las fotos de las fichas clínicas y de las comparaciones guardadas,
 * con su fecha y el tipo de ficha, agrupadas por paciente. El paciente va con un
 * código (P-3F9A2C), nunca con su nombre, teléfono, correo, RUT ni el texto
 * clínico de la ficha: la ficha clínica es reservada (Ley 20.584) y la agencia
 * es externa al spa. Luis eligió esta opción a propósito.
 *
 *   POST {}  → { pacientes: [{ codigo, fichas:[{fecha, plantilla, fotos:[url]}], comparaciones:[…] }] }
 *
 * Pueden pedirla publicidad, administración y administración local.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/combinar-lib.php';      // cmb_rest: lectura con la llave de servicio
require_once __DIR__ . '/require-auth.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);
$yo = require_panel_user(false);
if (!in_array($yo['role'] ?? '', ['admin', 'admin_local', 'publicidad'], true)) responder(403, ['error' => 'Tu perfil no ve esta galería']);

/* se arma una vez cada 10 minutos: leer todas las fichas en cada visita gastaría
   la cuota de transferencia de Supabase */
$cache = dirname(__DIR__, 2) . '/cache-privado/fotos-publicidad.json';   // fuera de la carpeta web
if (is_file($cache) && time() - filemtime($cache) < 600) { echo file_get_contents($cache); exit; }

/* el código del paciente: siempre el mismo para la misma ficha, imposible de revertir al cliente */
$sal = hash('sha256', (string)(supa_key()) . '|fotos-publicidad');
$codigo = fn($id) => 'P-' . strtoupper(substr(hash('sha256', $sal . $id), 0, 6));
$esFoto = fn($v) => is_string($v) && strpos($v, '/object/public/fichas/') !== false;
$dia = fn($f) => $f ? substr(date('c', strtotime((string)$f)), 0, 10) : null;

$pac = [];
for ($o = 0; $o < 50000; $o += 1000) {
    [$c, $filas] = cmb_rest('GET', 'client_records?select=client_id,template_name,record_date,created_at,data&order=id&limit=1000&offset=' . $o);
    if ($c !== 200 || !is_array($filas)) responder(500, ['error' => 'No se pudieron leer las fichas']);
    foreach ($filas as $r) {
        $d = is_string($r['data']) ? (json_decode($r['data'], true) ?: []) : ($r['data'] ?: []);
        $fotos = [];
        array_walk_recursive($d, function ($v) use (&$fotos, $esFoto) { if ($esFoto($v)) $fotos[] = $v; });
        if (!$fotos || empty($r['client_id'])) continue;
        $k = $codigo($r['client_id']);
        $pac[$k]['codigo'] = $k;
        $pac[$k]['fichas'][] = ['fecha' => $dia($r['record_date'] ?: $r['created_at']), 'plantilla' => (string)($r['template_name'] ?? ''), 'fotos' => array_values(array_unique($fotos))];
    }
    if (count($filas) < 1000) break;
}
[$c, $comps] = cmb_rest('GET', 'client_comparisons?select=client_id,titulo,antes_url,antes_fecha,despues_url,despues_fecha,created_at&order=created_at.desc&limit=2000');
foreach ((is_array($comps) ? $comps : []) as $cp) {
    if (empty($cp['client_id']) || (!$esFoto($cp['antes_url'] ?? null) && !$esFoto($cp['despues_url'] ?? null))) continue;
    $k = $codigo($cp['client_id']);
    $pac[$k]['codigo'] = $k;
    $pac[$k]['comparaciones'][] = ['titulo' => (string)($cp['titulo'] ?? 'Antes y después'),
        'antes_url' => $cp['antes_url'], 'antes_fecha' => $dia($cp['antes_fecha'] ?: $cp['created_at']),
        'despues_url' => $cp['despues_url'], 'despues_fecha' => $dia($cp['despues_fecha'] ?: $cp['created_at'])];
}
/* cada paciente con sus fichas en orden de atención; primero los atendidos más recientemente */
foreach ($pac as &$p) {
    $p['fichas'] = $p['fichas'] ?? []; $p['comparaciones'] = $p['comparaciones'] ?? [];
    usort($p['fichas'], fn($a, $b) => strcmp((string)$a['fecha'], (string)$b['fecha']));
    $p['ultima'] = $p['fichas'] ? end($p['fichas'])['fecha'] : ($p['comparaciones'][0]['despues_fecha'] ?? '');
}
unset($p);
$lista = array_values($pac);
usort($lista, fn($a, $b) => strcmp((string)$b['ultima'], (string)$a['ultima']));
$json = json_encode(['ok' => true, 'pacientes' => $lista], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_dir(dirname($cache))) @mkdir(dirname($cache), 0755, true);
@file_put_contents($cache, $json);
echo $json;
