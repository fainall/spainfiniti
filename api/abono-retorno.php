<?php
/**
 * Adonde vuelve el cliente después de pagar el abono en Flow (POST con token).
 * Confirma el pago aquí mismo si Flow todavía no avisó a flow-confirm.php
 * (los dos pueden llegar en cualquier orden) y lleva a /pago-reserva.html con
 * el número de orden (desde el 28-sep la reserva se crea recién al pagar).
 */
require_once __DIR__ . '/abono-lib.php';
require_once __DIR__ . '/flow-lib.php';
$config = @include __DIR__ . '/flow-config.php';
$base = rtrim(is_array($config) ? ($config['siteUrl'] ?? '') : '', '/') ?: 'https://spainfinity.cl';
$ir = function ($estado, $orden = '', $id = '') use ($base) {
    header('Location: ' . $base . '/pago-reserva.html?estado=' . $estado
        . ($orden ? '&orden=' . rawurlencode($orden) : '') . ($id ? '&id=' . rawurlencode($id) : ''));
    exit;
};

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
if (!is_array($config) || $token === '') $ir('error');

try {
    $flow = new FlowClient($config);
    $status = $flow->getPaymentStatus($token);
} catch (Exception $e) {
    error_log('abono-retorno: ' . $e->getMessage());
    $ir('error');
}

$commerceOrder = preg_replace('/[^A-Za-z0-9\-]/', '', (string)($status['commerceOrder'] ?? ''));
$orderFile = __DIR__ . '/orders/' . $commerceOrder . '.json';
if (strpos($commerceOrder, 'AB-') !== 0 || !is_file($orderFile)) $ir('error');

$estado = abono_procesar($orderFile, $status, $token);
if ($estado === 'paid') {
    /* si el aviso de Flow llegó primero, puede que todavía se esté creando la
       reserva: se espera un momento a que quede anotado el resultado */
    for ($i = 0; $i < 10; $i++) {
        $order = json_decode((string)file_get_contents($orderFile), true) ?: [];
        if (!empty($order['resultado'])) break;
        usleep(700000);
    }
    $ir(($order['resultado'] ?? '') === 'perdida' ? 'tarde' : 'ok', $commerceOrder, (string)($order['appointment_id'] ?? ''));
}
if ($estado === 'rejected' || $estado === 'cancelled') $ir('rechazado', $commerceOrder);
$ir('pendiente', $commerceOrder);
