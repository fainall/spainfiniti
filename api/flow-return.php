<?php
/**
 * URL de retorno de Flow (donde el cliente vuelve después de pagar).
 * Flow envía POST con 'token'. Verificamos el status y redirigimos a giftcard.html
 * con ?status=success | failed | error.
 */
require_once __DIR__ . '/flow-lib.php';
$config = @include __DIR__ . '/flow-config.php';

$base = $config['siteUrl'] ?? 'https://spainfinity.cl';

if (!is_array($config) || empty($_POST['token'])) {
    header('Location: ' . $base . '/giftcard.html?status=error');
    exit;
}

try {
    $flow   = new FlowClient($config);
    $status = $flow->getPaymentStatus($_POST['token']);
    $code   = (int)($status['status'] ?? 0);

    if ($code === 2) {
        header('Location: ' . $base . '/giftcard.html?status=success');
    } elseif ($code === 3 || $code === 4) {
        header('Location: ' . $base . '/giftcard.html?status=failed');
    } else {
        header('Location: ' . $base . '/giftcard.html?status=pending');
    }
} catch (Exception $e) {
    error_log('Flow return: ' . $e->getMessage());
    header('Location: ' . $base . '/giftcard.html?status=error');
}
