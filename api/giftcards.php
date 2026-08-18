<?php
/**
 * Listado de Gift Cards vendidas — Spa Infinity
 * Lee las órdenes de Flow (api/orders/*.json) y devuelve las gift cards.
 * GET /api/giftcards.php?key=SECRET
 */
header('Content-Type: application/json; charset=UTF-8');

$cfg = file_exists(__DIR__.'/bot-config.php') ? require __DIR__.'/bot-config.php' : [];
$secret = $cfg['campaignKey'] ?? 'spa-camp-2026';
if (($_GET['key'] ?? '') !== $secret) { http_response_code(403); echo json_encode(['error'=>'forbidden']); exit; }

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
