<?php
/**
 * POST /api/create-payment.php
 * Recibe orderData del frontend, crea pago en Flow, retorna URL para redirigir.
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
$config = @include __DIR__ . '/flow-config.php';
if (!is_array($config)) {
    http_response_code(500);
    echo json_encode(['error' => 'Flow no está configurado en el servidor (falta flow-config.php)']);
    exit;
}

// Leer JSON body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Body inválido']);
    exit;
}

// Validar campos requeridos
$required = ['total', 'from', 'fromEmail', 'to', 'toEmail', 'services', 'code'];
foreach ($required as $f) {
    if (empty($data[$f])) {
        http_response_code(400);
        echo json_encode(['error' => "Falta el campo: $f"]);
        exit;
    }
}

// Convertir total "$22.400" a entero 22400
$amountInt = (int) preg_replace('/[^0-9]/', '', $data['total']);
if ($amountInt < 100) { // Flow rechaza < $350 pero validamos algo razonable
    http_response_code(400);
    echo json_encode(['error' => 'Monto inválido']);
    exit;
}

// Validar emails
if (!filter_var($data['fromEmail'], FILTER_VALIDATE_EMAIL) || !filter_var($data['toEmail'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit;
}

// Generar orden única (commerceOrder debe ser único en tu cuenta Flow)
$commerceOrder = 'SI-' . date('YmdHis') . '-' . substr(uniqid(), -5);

// Subject: nombre del/los servicios
$svcNames = array_map(fn($s) => $s['name'] ?? '', $data['services']);
$subject  = 'Gift Card: ' . substr(implode(', ', $svcNames), 0, 80);

$siteUrl = rtrim($config['siteUrl'], '/');

try {
    $flow = new FlowClient($config);

    // Guardar pre-orden en archivo temporal (para que el confirm webhook pueda recuperarla)
    $orderFile = __DIR__ . '/orders/' . $commerceOrder . '.json';
    if (!is_dir(__DIR__ . '/orders')) mkdir(__DIR__ . '/orders', 0755, true);
    file_put_contents($orderFile, json_encode([
        'commerceOrder' => $commerceOrder,
        'amount'        => $amountInt,
        'data'          => $data,
        'createdAt'     => date('c'),
        'status'        => 'pending',
    ]));

    $paymentParams = [
        'commerceOrder'   => $commerceOrder,
        'subject'         => $subject,
        'amount'          => $amountInt,
        'email'           => $data['fromEmail'],
        'urlConfirmation' => $siteUrl . '/api/flow-confirm.php',
        'urlReturn'       => $siteUrl . '/api/flow-return.php',
        'optional'        => [
            'gcCode' => $data['code'],
            'to'     => $data['to'],
        ],
    ];
    // Si vino un paymentMethod válido lo agregamos (1=Webpay, 3=Khipu, 9=Todos, 15=MACH/BCI)
    if (!empty($data['paymentMethod']) && in_array((int)$data['paymentMethod'], [1,3,5,9,15,17])) {
        $paymentParams['paymentMethod'] = (int)$data['paymentMethod'];
    }
    $result = $flow->createPayment($paymentParams);

    echo json_encode([
        'success'    => true,
        'paymentUrl' => $flow->paymentRedirectUrl($result),
        'commerceOrder' => $commerceOrder,
    ]);
} catch (Exception $e) {
    error_log('Flow create-payment failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
