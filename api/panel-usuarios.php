<?php
/**
 * Cuentas del equipo del panel — Spa Infinity
 *
 * Antes el panel creaba las cuentas del equipo con el registro público de
 * Supabase (/auth/v1/signup). Eso obligaba a tener apagada la confirmación de
 * correo, y con las cuentas de clientes del sitio esa confirmación tiene que
 * estar encendida (si no, cualquiera podría registrarse con el correo de otra
 * persona y quedarse con su historial). Ahora las crea el servidor con la llave
 * de servicio: la cuenta nace ya confirmada, porque la crea un administrador.
 *
 *   POST {accion:'crear', email, password, name, role, professional_id}
 *        solo un administrador del panel. Si el correo ya tiene una cuenta (por
 *        ejemplo, de cliente del sitio), responde {existe:true} y no toca nada;
 *        con {usar_existente:true} le da acceso al panel a esa misma cuenta,
 *        sin cambiarle la contraseña.
 *
 *   POST {accion:'primer_admin', email, password, name}
 *        sin sesión, y SOLO si todavía no existe ningún administrador (instalación
 *        nueva). Después de eso responde 403 siempre.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/supa-key.php';
require_once __DIR__ . '/require-auth.php';

function responder($code, $j) { http_response_code($code); echo json_encode($j, JSON_UNESCAPED_UNICODE); exit; }

function admin_api($metodo, $ruta, $cuerpo = null) {
    $ch = curl_init(supa_url() . '/auth/v1/admin' . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(), 'Content-Type: application/json']]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$r, true)];
}
function rest($metodo, $ruta, $cuerpo = null) {
    $ch = curl_init(supa_url() . '/rest/v1/' . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(), 'Content-Type: application/json', 'Prefer: return=representation']]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$r, true)];
}
/* busca una cuenta por correo (el equipo y los clientes del spa son pocos miles) */
function cuenta_por_correo($email) {
    for ($pag = 1; $pag <= 20; $pag++) {
        [$c, $j] = admin_api('GET', '/users?page=' . $pag . '&per_page=500');
        $users = $j['users'] ?? [];
        foreach ($users as $u) if (strtolower($u['email'] ?? '') === $email) return $u;
        if (count($users) < 500) break;
    }
    return null;
}

/* sin la llave de servicio no se puede crear nada: mejor decirlo claro */
$cfgFile = __DIR__ . '/bot-config.php';
$cfg = file_exists($cfgFile) ? (require $cfgFile) : [];
if (empty($cfg['serviceKey'])) responder(500, ['error' => 'Falta la llave de servicio en el servidor (bot-config.php)']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['error' => 'solo POST']);
$d = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($d)) responder(400, ['error' => 'Los datos llegaron ilegibles; vuelve a intentarlo']);
$accion = (string)($d['accion'] ?? '');
$email = strtolower(trim((string)($d['email'] ?? '')));
$nombre = trim((string)($d['name'] ?? ''));
$clave = (string)($d['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) responder(400, ['error' => 'El correo no es válido']);
if ($nombre === '') responder(400, ['error' => 'Falta el nombre']);

const ROLES_PANEL = ['admin', 'admin_local', 'recepcion', 'recepcion_ro', 'pro', 'pro_ro', 'publicidad'];

if ($accion === 'primer_admin') {
    [$c, $hay] = rest('GET', 'panel_users?select=id&role=eq.admin&active=eq.true&limit=1');
    if (!is_array($hay) || count($hay)) responder(403, ['error' => 'La cuenta ya tiene administrador. Pídele a esa persona que te cree una cuenta.']);
    $rol = 'admin'; $prof = null; $usarExistente = false;
} elseif ($accion === 'crear') {
    require_panel_user(true);                         // solo un administrador (corta con 401/403)
    $rol = (string)($d['role'] ?? 'pro');
    if (!in_array($rol, ROLES_PANEL, true)) responder(400, ['error' => 'Rol no válido']);
    $prof = $d['professional_id'] ?? null;
    if ($prof !== null && !preg_match('/^[0-9a-f-]{36}$/i', (string)$prof)) responder(400, ['error' => 'Profesional no válido']);
    $usarExistente = !empty($d['usar_existente']);
} else {
    responder(400, ['error' => 'Acción no válida']);
}

/* ¿el correo ya tiene cuenta? (de cliente del sitio, o del equipo desactivada) */
$existente = cuenta_por_correo($email);
if ($existente) {
    [$c, $pu] = rest('GET', 'panel_users?select=id,active&id=eq.' . rawurlencode($existente['id']));
    if (is_array($pu) && count($pu)) responder(409, ['error' => 'Ese correo ya tiene una cuenta del panel. Edítala desde la lista.']);
    if (!$usarExistente) responder(200, ['existe' => true,
        'mensaje' => 'Ese correo ya tiene una cuenta en el sitio (probablemente de cliente). Puedes darle acceso al panel a esa misma cuenta: seguirá entrando con su contraseña de siempre.']);
    $uid = $existente['id'];
    /* si no había confirmado su correo, al darle acceso lo confirma el administrador */
    if (empty($existente['email_confirmed_at'])) admin_api('PUT', '/users/' . rawurlencode($uid), ['email_confirm' => true]);
} else {
    if (strlen($clave) < 8) responder(400, ['error' => 'La contraseña inicial debe tener al menos 8 caracteres']);
    [$c, $u] = admin_api('POST', '/users', ['email' => $email, 'password' => $clave, 'email_confirm' => true,
                                            'user_metadata' => ['nombre' => $nombre, 'equipo' => true]]);
    if ($c >= 300 || empty($u['id'])) {
        $m = (string)($u['msg'] ?? $u['message'] ?? $u['error_description'] ?? '');
        responder(400, ['error' => preg_match('/password/i', $m) ? 'La contraseña inicial es muy débil: usa al menos 8 caracteres, mezclando letras y números'
                                 : ($m !== '' ? 'Supabase no aceptó la cuenta: ' . $m : 'No se pudo crear la cuenta')]);
    }
    $uid = $u['id'];
}

[$c, $fila] = rest('POST', 'panel_users', ['id' => $uid, 'email' => $email, 'name' => $nombre, 'role' => $rol,
                                           'professional_id' => $prof, 'active' => true]);
if ($c >= 300) {
    /* si la cuenta la acabamos de crear y el perfil del panel falló, no la dejamos a medias */
    if (!$existente) admin_api('DELETE', '/users/' . rawurlencode($uid));
    responder(500, ['error' => 'No se pudo guardar el perfil del panel']);
}
responder(200, ['ok' => true, 'id' => $uid, 'usada_existente' => (bool)$existente]);
