<?php
/* ═══════════════════════════════════════════
   SPA INFINITY — Google Reviews (con caché)
   Consulta Places API (New) máx. 1 vez cada 24h.
   Config real en api/reviews-config.php (NO en git):
     return ['apiKey' => '...', 'placeId' => '...'];
   ═══════════════════════════════════════════ */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$cacheFile = __DIR__ . '/cache/reviews.json';
$cacheTtl  = 86400; // 24 horas

/* 1. Servir caché si está fresco */
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    readfile($cacheFile);
    exit;
}

/* 2. Config */
$configFile = __DIR__ . '/reviews-config.php';
if (!file_exists($configFile)) {
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}
$cfg = require $configFile;
$apiKey  = $cfg['apiKey']  ?? '';
$placeId = $cfg['placeId'] ?? '';

if (!$apiKey || !$placeId) {
    echo json_encode(['ok' => false, 'error' => 'not_configured']);
    exit;
}

/* 3. Consultar Google Places API (New) */
$url = 'https://places.googleapis.com/v1/places/' . rawurlencode($placeId)
     . '?languageCode=es&regionCode=CL';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'X-Goog-Api-Key: ' . $apiKey,
        'X-Goog-FieldMask: rating,userRatingCount,reviews,googleMapsUri',
    ],
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$serveStale = function () use ($cacheFile) {
    if (file_exists($cacheFile)) { readfile($cacheFile); }
    else { echo json_encode(['ok' => false, 'error' => 'api_error']); }
    exit;
};

if ($code !== 200 || !$resp) { $serveStale(); }

$data = json_decode($resp, true);
if (!$data || !isset($data['reviews'])) { $serveStale(); }

/* 4. Normalizar */
$reviews = [];
foreach ($data['reviews'] as $r) {
    $text = $r['text']['text'] ?? ($r['originalText']['text'] ?? '');
    if (!$text) continue;
    $reviews[] = [
        'name'   => $r['authorAttribution']['displayName'] ?? 'Cliente',
        'rating' => $r['rating'] ?? 5,
        'time'   => $r['relativePublishTimeDescription'] ?? '',
        'text'   => $text,
    ];
}

$out = json_encode([
    'ok'      => true,
    'rating'  => $data['rating'] ?? null,
    'total'   => $data['userRatingCount'] ?? null,
    'mapsUrl' => $data['googleMapsUri'] ?? '',
    'reviews' => $reviews,
], JSON_UNESCAPED_UNICODE);

/* 5. Guardar caché */
@mkdir(__DIR__ . '/cache', 0755, true);
@file_put_contents($cacheFile, $out);

echo $out;
