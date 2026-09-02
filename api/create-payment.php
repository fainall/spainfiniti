<?php
/**
 * POST /api/create-payment.php
 * Recibe los datos de la gift card desde el sitio, crea el pago en Flow y
 * devuelve la URL a la que redirigir.
 *
 * El precio NO se toma de lo que manda el navegador: se recalcula aquí a
 * partir del catálogo (tabla services). Antes cualquiera podía pagar $350
 * y recibir por correo una gift card oficial de un masaje de $33.600.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/flow-lib.php';
require_once __DIR__ . '/supa-key.php';
$config = @include __DIR__ . '/flow-config.php';
if (!is_array($config)) {
    http_response_code(500);
    echo json_encode(['error' => 'Flow no está configurado en el servidor (falta flow-config.php)']);
    exit;
}

function fallar($code, $msg) { http_response_code($code); echo json_encode(['error' => $msg]); exit; }

// Leer JSON body
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) fallar(400, 'Body inválido');

$required = ['from', 'fromEmail', 'to', 'toEmail', 'services', 'code'];
foreach ($required as $f) {
    if (empty($data[$f])) fallar(400, "Falta el campo: $f");
}
if (!filter_var($data['fromEmail'], FILTER_VALIDATE_EMAIL) || !filter_var($data['toEmail'], FILTER_VALIDATE_EMAIL)) {
    fallar(400, 'Email inválido');
}
if (!is_array($data['services']) || !count($data['services']) || count($data['services']) > 10) {
    fallar(400, 'Servicios inválidos');
}
/* el código lo genera el sitio, pero con un formato fijo y sin repetir uno ya usado */
$code = strtoupper(trim((string)$data['code']));
if (!preg_match('/^GC-[A-Z0-9]{6,12}$/', $code)) fallar(400, 'Código inválido');
foreach (glob(__DIR__ . '/orders/SI-*.json') ?: [] as $f) {
    $o = json_decode(@file_get_contents($f), true);
    if (($o['data']['code'] ?? '') === $code && in_array($o['status'] ?? '', ['paid', 'pending'], true)) {
        fallar(409, 'Ese código ya está en uso; vuelve a intentarlo');
    }
}

/* ── el precio sale del catálogo, no del navegador ── */
$ch = curl_init(supa_url() . '/rest/v1/services?select=name,price,gc_discount_enabled,gc_price');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12,
    CURLOPT_HTTPHEADER=>['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key()]]);
$catalogo = json_decode(curl_exec($ch), true); curl_close($ch);
if (!is_array($catalogo) || !count($catalogo)) fallar(503, 'No pudimos leer el catálogo; inténtalo en un momento');

$porNombre = [];
foreach ($catalogo as $s) $porNombre[mb_strtolower(trim((string)($s['name'] ?? '')))] = $s;
$money = fn($p) => (int)preg_replace('/[^0-9]/', '', (string)$p);

$servicios = [];
$amountInt = 0;
foreach ($data['services'] as $req) {
    $nombre = mb_strtolower(trim((string)($req['name'] ?? '')));
    if ($nombre === '' || !isset($porNombre[$nombre])) fallar(400, 'Servicio no reconocido: ' . mb_substr((string)($req['name'] ?? ''), 0, 60));
    $s = $porNombre[$nombre];
    $precio = (!empty($s['gc_discount_enabled']) && $money($s['gc_price'] ?? '') > 0) ? $money($s['gc_price']) : $money($s['price']);
    if ($precio <= 0) fallar(400, 'Ese servicio no tiene precio para gift card');
    $servicios[] = ['name' => $s['name'], 'price' => '$' . number_format($precio, 0, ',', '.')];
    $amountInt += $precio;
}
if ($amountInt < 350) fallar(400, 'El monto mínimo que acepta Flow es $350');

$enviado = $money($data['total'] ?? '');
if ($enviado && $enviado !== $amountInt) {
    error_log("create-payment: total enviado $enviado distinto del calculado $amountInt para $code");
}

/* lo que se guarda es lo verificado, no lo recibido */
$limpio = [
    'services'  => $servicios,
    'total'     => '$' . number_format($amountInt, 0, ',', '.'),
    'from'      => mb_substr(trim((string)$data['from']), 0, 80),
    'fromEmail' => $data['fromEmail'],
    'to'        => mb_substr(trim((string)$data['to']), 0, 80),
    'toEmail'   => $data['toEmail'],
    'message'   => mb_substr(trim((string)($data['message'] ?? '')), 0, 500),
    'code'      => $code,
];

$commerceOrder = 'SI-' . date('YmdHis') . '-' . substr(uniqid(), -5);
$subject  = 'Gift Card: ' . mb_substr(implode(', ', array_column($servicios, 'name')), 0, 80);
$siteUrl = rtrim($config['siteUrl'], '/');

try {
    $flow = new FlowClient($config);

    $orderFile = __DIR__ . '/orders/' . $commerceOrder . '.json';
    if (!is_dir(__DIR__ . '/orders')) mkdir(__DIR__ . '/orders', 0755, true);
    file_put_contents($orderFile, json_encode([
        'commerceOrder' => $commerceOrder,
        'tipo'          => 'giftcard',
        'amount'        => $amountInt,
        'data'          => $limpio,
        'createdAt'     => date('c'),
        'status'        => 'pending',
    ], JSON_UNESCAPED_UNICODE));

    $paymentParams = [
        'commerceOrder'   => $commerceOrder,
        'subject'         => $subject,
        'amount'          => $amountInt,
        'email'           => $limpio['fromEmail'],
        'urlConfirmation' => $siteUrl . '/api/flow-confirm.php',
        'urlReturn'       => $siteUrl . '/api/flow-return.php',
        'optional'        => ['gcCode' => $code, 'to' => $limpio['to']],
    ];
    if (!empty($data['paymentMethod']) && in_array((int)$data['paymentMethod'], [1,3,5,9,15,17])) {
        $paymentParams['paymentMethod'] = (int)$data['paymentMethod'];
    }
    $result = $flow->createPayment($paymentParams);

    echo json_encode([
        'success'       => true,
        'paymentUrl'    => $flow->paymentRedirectUrl($result),
        'commerceOrder' => $commerceOrder,
        'total'         => $limpio['total'],
    ]);
} catch (Exception $e) {
    error_log('Flow create-payment failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'No pudimos iniciar el pago. Inténtalo de nuevo en un momento.']);
}
