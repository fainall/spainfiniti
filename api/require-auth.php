<?php
/**
 * Control de acceso de los endpoints del panel.
 *
 * Antes se usaba una clave compartida escrita en el HTML (cualquiera podía leerla).
 * Ahora el panel envía el token de la sesión del usuario y aquí se verifica
 * contra Supabase que la sesión sea válida y que la persona tenga permiso.
 *
 * Uso:  require_once __DIR__ . '/require-auth.php';  $yo = require_panel_user();
 */
require_once __DIR__ . '/supa-key.php';

function _bearer_token() {
    $h = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION']))            $h = $_SERVER['HTTP_AUTHORIZATION'];
    elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) $h = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    elseif (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) if (strtolower($k) === 'authorization') $h = $v;
    }
    if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return trim($m[1]);
    return '';
}

function _http_get($url, $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_HTTPHEADER=>$headers]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($body, true)];
}

/**
 * Devuelve el perfil del usuario que llama, o corta con 401/403.
 * @param bool $adminOnly  true = solo administradores
 */
function require_panel_user($adminOnly = true) {
    $token = _bearer_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Necesitas iniciar sesión en el panel']);
        exit;
    }

    // 1. ¿la sesión es válida?
    $anon = supa_anon();
    list($code, $user) = _http_get(supa_url() . '/auth/v1/user', [
        'apikey: ' . $anon,
        'Authorization: Bearer ' . $token,
    ]);
    if ($code !== 200 || empty($user['id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Tu sesión expiró; vuelve a entrar al panel']);
        exit;
    }

    // 2. ¿tiene perfil activo en el panel?
    $ruta = supa_url() . '/rest/v1/panel_users?select=id,name,email,role,active&id=eq.' . urlencode($user['id']);
    list($c2, $rows) = _http_get($ruta, ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key()]);
    $perfil = (is_array($rows) && count($rows)) ? $rows[0] : null;

    /* Sin la llave de servicio configurada, supa_key() es la llave publica y
       las reglas de la base no dejan ver ningun perfil: nadie entraria. En ese
       caso se pregunta con la sesion de la propia persona, que si puede leer
       la suya. La sesion ya quedo verificada contra Supabase en el paso 1. */
    if (!$perfil) {
        list($c3, $rows2) = _http_get($ruta, ['apikey: ' . $anon, 'Authorization: Bearer ' . $token]);
        $perfil = (is_array($rows2) && count($rows2)) ? $rows2[0] : null;
    }
    if (!$perfil || $perfil['active'] === false) {
        http_response_code(403);
        echo json_encode(['error' => 'Tu cuenta no tiene acceso al panel']);
        exit;
    }
    if ($adminOnly && ($perfil['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Esta acción es solo para administradores']);
        exit;
    }
    return $perfil;
}
