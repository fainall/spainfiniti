<?php
/* Análisis de ventas con OpenAI — recibe los totales ya agregados del panel */
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Método no permitido']); exit; }

$cfgFile = __DIR__ . '/bot-config.php';
if (!file_exists($cfgFile)) { echo json_encode(['error'=>'Falta la configuración del asistente']); exit; }
$cfg = require $cfgFile;
$KEY = $cfg['openaiKey'] ?? '';
if (!$KEY) { echo json_encode(['error'=>'Falta la API key de OpenAI']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$cur = $in['actual'] ?? []; $prev = $in['previo'] ?? [];
$money = function($n){ return '$' . number_format((float)$n, 0, ',', '.'); };
$lista = function($arr) use ($money) {
    if (!is_array($arr) || !count($arr)) return 'sin datos';
    arsort($arr); $out = [];
    foreach ($arr as $k => $v) { $out[] = "$k: " . $money($v); }
    return implode(', ', array_slice($out, 0, 8));
};

$datos = "Período analizado: {$in['periodo']}\n"
  . "Total vendido: " . $money($cur['total'] ?? 0) . " en " . ($cur['n'] ?? 0) . " ventas (promedio " . $money($cur['avg'] ?? 0) . ")\n"
  . "Por tipo de ítem: " . $lista($cur['tipos'] ?? []) . "\n"
  . "Por categoría: " . $lista($cur['cats'] ?? []) . "\n"
  . "Por servicio: " . $lista($cur['svcs'] ?? []) . "\n\n"
  . "Período anterior ({$in['anterior']}): " . $money($prev['total'] ?? 0) . " en " . ($prev['n'] ?? 0) . " ventas (promedio " . $money($prev['avg'] ?? 0) . ")\n"
  . "Por tipo de ítem período anterior: " . $lista($prev['tipos'] ?? []);

if (!empty($in['debug'])) { echo json_encode(['datos'=>$datos], JSON_UNESCAPED_UNICODE); exit; }

$system = "Eres el analista de negocio de Spa Infinity, un centro podológico y spa en Santiago Centro, Chile. "
  . "Analiza los datos de ventas y responde en español de Chile, tuteando, en 4 a 6 líneas cortas separadas por saltos de línea. "
  . "Cada línea: un hallazgo concreto con el número que lo respalda, o una recomendación accionable para el spa. "
  . "No inventes datos que no estén en el resumen. No uses markdown ni viñetas.";

$payload = json_encode([
  'model' => $cfg['model'] ?? 'gpt-4o-mini',
  'messages' => [
    ['role'=>'system','content'=>$system],
    ['role'=>'user','content'=>$datos],
  ],
  'temperature' => 0.4,
  'max_tokens' => 400,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 40,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => $payload,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $KEY],
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) { echo json_encode(['error'=>'El asistente no respondió (HTTP ' . $code . ')']); exit; }
$j = json_decode($res, true);
$text = $j['choices'][0]['message']['content'] ?? '';
if (!$text) { echo json_encode(['error'=>'Respuesta vacía del asistente']); exit; }
echo json_encode(['text'=>$text], JSON_UNESCAPED_UNICODE);
