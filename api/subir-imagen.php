<?php
/**
 * Recibe una foto del panel y la guarda como archivo en /images/subidas/.
 *
 * Antes el panel metía la imagen entera, como texto, dentro de la fila del
 * servicio en la base. Con 16 fotos el catálogo pesaba 1,2 MB y en el celular
 * la descarga se cortaba: el sitio quedaba sin catálogo y mostraba precios
 * viejos. Ahora la base guarda solo la dirección del archivo, que pesa 40
 * bytes, y el navegador se queda la foto en caché un año.
 *
 * Entrada (JSON):  { imagen: "data:image/jpeg;base64,...", nombre: "atencion-podologica" }
 * Salida  (JSON):  { url: "/images/subidas/atencion-podologica-20260904-181530-a1b2.jpg" }
 *
 * Solo para administradores con sesión del panel. Los archivos viven en el
 * servidor y no van en git: si alguna vez se monta el sitio en otro servidor,
 * hay que copiar también la carpeta /images/subidas/.
 */
require_once __DIR__ . '/require-auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['error' => 'method'])); }

$yo = require_panel_user(true);

$in = json_decode(file_get_contents('php://input'), true);
$imagen = (string)($in['imagen'] ?? '');
$nombre = (string)($in['nombre'] ?? 'foto');

if (!preg_match('#^data:(image/(jpeg|jpg|png|webp));base64,(.+)$#s', $imagen, $m)) {
    http_response_code(400); exit(json_encode(['error' => 'La imagen no viene en un formato reconocido']));
}
/* 6 MB de base64 ≈ 4,5 MB de imagen; el panel ya la achica antes de mandarla */
if (strlen($m[3]) > 6 * 1024 * 1024) {
    http_response_code(413); exit(json_encode(['error' => 'La imagen es demasiado pesada']));
}
$bytes = base64_decode($m[3], true);
if ($bytes === false) { http_response_code(400); exit(json_encode(['error' => 'La imagen viene dañada'])); }

/* que sea de verdad una imagen, no solo un archivo con ese nombre */
$info = @getimagesizefromstring($bytes);
if (!$info || empty($info['mime'])) { http_response_code(400); exit(json_encode(['error' => 'El archivo no es una imagen'])); }
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']] ?? null;
if (!$ext) { http_response_code(400); exit(json_encode(['error' => 'Formato de imagen no permitido'])); }

/* nombre de archivo limpio: solo letras, numeros y guiones */
$slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre), '-'));
if ($slug === '') $slug = 'foto';
$slug = substr($slug, 0, 60);
$archivo = $slug . '-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 4) . '.' . $ext;

$dir = dirname(__DIR__) . '/images/subidas';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    http_response_code(500); exit(json_encode(['error' => 'No se pudo crear la carpeta de fotos']));
}
if (@file_put_contents($dir . '/' . $archivo, $bytes) === false) {
    http_response_code(500); exit(json_encode(['error' => 'No se pudo guardar la foto']));
}

echo json_encode(['url' => '/images/subidas/' . $archivo, 'kb' => (int)round(strlen($bytes) / 1024)]);
