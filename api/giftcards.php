<?php
/**
 * Listado de Gift Cards vendidas — Spa Infinity
 * Lee las órdenes de Flow (api/orders/*.json) y devuelve las gift cards.
 * GET /api/giftcards.php?key=SECRET
 */
header('Content-Type: application/json; charset=UTF-8');

$cfg = file_exists(__DIR__.'/bot-config.php') ? require __DIR__.'/bot-config.php' : [];
require_once __DIR__ . '/require-auth.php';
/* cualquier cuenta del panel: la recepcion cobra con gift card y necesita
   comprobar el codigo y el saldo (su rol ya incluye la pantalla Gift Cards) */
require_panel_user(false);

$dir = __DIR__ . '/orders';
$out = [];
foreach (glob($dir.'/*.json') as $f) {
    $o = json_decode(@file_get_contents($f), true);
    if (!is_array($o) || empty($o['data']['code'])) continue;
    $d = $o['data'];
    $svc = $d['services'][0]['name'] ?? 'Gift Card';
    $out[] = [
        'id'        => $o['commerceOrder'] ?? basename($f, '.json'),
        'code'      => $d['code'],
        'name'      => $svc,
        /* una gift card es un vale por estos servicios (uno o varios) */
        'services'  => array_values(array_map(fn($s) => ['name' => (string)($s['name'] ?? ''), 'price' => (string)($s['price'] ?? '')],
                                              array_filter((array)($d['services'] ?? []), 'is_array'))),
        'amount'    => (int)($o['amount'] ?? 0),
        'from'      => $d['from'] ?? '',
        'fromEmail' => $d['fromEmail'] ?? '',
        'to'        => $d['to'] ?? '',
        'toEmail'   => $d['toEmail'] ?? '',
        'status'    => $o['status'] ?? 'pending',      // paid | pending
        'soldAt'    => $o['paidAt'] ?? ($o['createdAt'] ?? null),
        'media'     => $o['flowData']['paymentData']['media'] ?? '',
    ];
}
usort($out, fn($a,$b) => strcmp($b['soldAt'] ?? '', $a['soldAt'] ?? ''));
echo json_encode(['ok'=>true, 'giftcards'=>$out], JSON_UNESCAPED_UNICODE);
