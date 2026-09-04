<?php
require_once __DIR__ . '/cliente.php';
/**
 * POST /api/cobro-link.php
 * Crea un link de pago en Flow para cobrar una reserva.
 * Lo usa la sección Ventas → Cobros del panel.
 *
 * Body: { appointment_id, client_name, client_email, service_name, amount }
 * Devuelve: { success, paymentUrl, commerceOrder }
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/* solo alguien con sesión en el panel puede generar cobros */
require_once __DIR__ . '/require-auth.php';
require_panel_user(false);

require_once __DIR__ . '/flow-lib.php';
$config = @include __DIR__ . '/flow-config.php';
if (!is_array($config)) {
    http_response_code(500);
    echo json_encode(['error' => 'Flow no está configurado en el servidor (falta flow-config.php)']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Body inválido']);
    exit;
}

$amount = (int) preg_replace('/[^0-9]/', '', (string)($data['amount'] ?? '0'));
if ($amount < 350) {
    http_response_code(400);
    echo json_encode(['error' => 'El monto mínimo que acepta Flow es $350']);
    exit;
}

$cliente = trim((string)($data['client_name'] ?? 'Cliente'));
$servicio = trim((string)($data['service_name'] ?? 'Servicio'));
$email = trim((string)($data['client_email'] ?? ''));
/* Flow exige un correo; si el cliente no tiene, se usa el del local */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = $config['contactEmail'] ?? cliente_correo();
}

$commerceOrder = 'RES-' . date('YmdHis') . '-' . substr(uniqid(), -5);
$siteUrl = rtrim($config['siteUrl'], '/');

try {
    $flow = new FlowClient($config);

    /* se guarda la pre-orden para que el webhook sepa qué reserva marcar como pagada */
    if (!is_dir(__DIR__ . '/orders')) mkdir(__DIR__ . '/orders', 0755, true);
    file_put_contents(__DIR__ . '/orders/' . $commerceOrder . '.json', json_encode([
        'commerceOrder'  => $commerceOrder,
        'tipo'           => 'reserva',
        'appointment_id' => $data['appointment_id'] ?? null,
        'client_id'      => $data['client_id'] ?? null,
        'amount'         => $amount,
        'service_name'   => $servicio,
        'client_name'    => $cliente,
        'createdAt'      => date('c'),
        'status'         => 'pending',
    ], JSON_UNESCAPED_UNICODE));

    $result = $flow->createPayment([
        'commerceOrder'   => $commerceOrder,
        'subject'         => mb_substr($servicio . ' — ' . $cliente, 0, 80),
        'amount'          => $amount,
        'email'           => $email,
        'urlConfirmation' => $siteUrl . '/api/flow-confirm.php',
        'urlReturn'       => $siteUrl . '/api/flow-return.php',
        'optional'        => [
            'appointmentId' => (string)($data['appointment_id'] ?? ''),
        ],
    ]);

    echo json_encode([
        'success'       => true,
        'paymentUrl'    => $flow->paymentRedirectUrl($result),
        'commerceOrder' => $commerceOrder,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log('cobro-link failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
