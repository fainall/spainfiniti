<?php
/**
 * Combinar dos fichas de cliente — la lógica, compartida por el panel
 * (combinar-clientes.php) y por Mariet (bot-reply.php).
 *
 * A la ficha que queda pasan las reservas, ventas, fichas clínicas, antes y
 * después, consentimientos, planes, links de pago e insumos de la otra; se le
 * completan los datos que le falten, y la otra se borra SOLO cuando ya no le
 * queda nada. Se puede repetir sin riesgo. Antes de tocar nada se guarda un
 * respaldo fuera de la carpeta web.
 */
require_once __DIR__ . '/supa-key.php';

/* todo lo que cuelga de un cliente; las que guardan además su nombre lo actualizan */
const CMB_TABLAS = [
    'appointments'       => ['Reservas', true],
    'sales'              => ['Ventas', true],
    'client_records'     => ['Fichas clínicas', false],
    'client_comparisons' => ['Antes y después', false],
    'client_consents'    => ['Consentimientos', false],
    'plans'              => ['Planes', true],
    'payment_links'      => ['Links de pago', true],
    'consumos'           => ['Insumos registrados', false],
];

function cmb_rest($metodo, $ruta, $cuerpo = null, $prefer = 'return=representation') {
    $ch = curl_init(supa_url() . '/rest/v1/' . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['apikey: ' . supa_key(), 'Authorization: Bearer ' . supa_key(), 'Content-Type: application/json', 'Prefer: ' . $prefer]]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    /* un corte de red o un error pasajero del servidor: se intenta una vez más
       (todo lo que se hace aquí da lo mismo hacerlo dos veces) */
    if ($code === 0 || $code >= 500) { usleep(400000); $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); }
    curl_close($ch);
    return [$code, json_decode((string)$r, true)];
}
function cmb_ficha($id) {
    [$c, $r] = cmb_rest('GET', 'clients?select=*&id=eq.' . $id);
    return ($c === 200 && is_array($r) && count($r)) ? $r[0] : null;
}
/* cuánto tiene una ficha en cada tabla; null si no se pudo leer */
function cmb_cuantos($id) {
    $n = [];
    foreach (CMB_TABLAS as $t => $_) {
        [$c, $r] = cmb_rest('GET', $t . '?select=client_id&client_id=eq.' . $id);
        if ($c !== 200 || !is_array($r)) return null;
        $n[$t] = count($r);
    }
    return $n;
}

/* ── ¿son la misma persona, con seguridad? (para combinar sin preguntar) ──
   Mismo teléfono Y mismo nombre. El teléfono solo no basta: lo comparten una
   madre y su hija, o un acompañante. Cuentan como el mismo nombre: nombre y
   apellido iguales (sin tildes ni mayúsculas); uno más completo que el otro
   ("Keidy Fuenmayor" y "Keidy Fuenmayor Rojas"); o el mismo primer nombre con
   el mismo correo. Dos RUT distintos, o dos cuentas distintas del sitio, no. */
function cmb_plano($t) {
    $t = mb_strtolower(trim((string)$t), 'UTF-8');
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', $t)));
}
function cmb_tel($t) { $d = preg_replace('/\D/', '', (string)$t); return strlen($d) >= 8 ? substr($d, -8) : ''; }
function cmb_misma_persona($a, $b) {
    $ta = cmb_tel($a['phone'] ?? ''); $tb = cmb_tel($b['phone'] ?? '');
    if ($ta === '' || $ta !== $tb) return false;
    $ra = preg_replace('/[^0-9k]/i', '', (string)($a['rut'] ?? '')); $rb = preg_replace('/[^0-9k]/i', '', (string)($b['rut'] ?? ''));
    if ($ra !== '' && $rb !== '' && strcasecmp($ra, $rb) !== 0) return false;
    if (!empty($a['user_id']) && !empty($b['user_id']) && $a['user_id'] !== $b['user_id']) return false;
    $na = cmb_plano($a['name'] ?? ''); $nb = cmb_plano($b['name'] ?? '');
    if ($na === '' || $nb === '') return false;
    if ($na === $nb) return true;
    $wa = explode(' ', $na); $wb = explode(' ', $nb);
    [$corto, $largo] = count($wa) <= count($wb) ? [$wa, $wb] : [$wb, $wa];
    if (count($corto) >= 2 && array_slice($largo, 0, count($corto)) === $corto) return true;
    $ma = strtolower(trim((string)($a['email'] ?? ''))); $mb = strtolower(trim((string)($b['email'] ?? '')));
    return $wa[0] === $wb[0] && $ma !== '' && $ma === $mb;
}
/* cuál se conserva: la que tiene cuenta del sitio o más historia; si empatan, la más antigua */
function cmb_cual_queda($a, $b, $ca, $cb) {
    $pa = array_sum($ca) + (!empty($a['user_id']) ? 1000 : 0); $pb = array_sum($cb) + (!empty($b['user_id']) ? 1000 : 0);
    if ($pa !== $pb) return $pa > $pb ? $a['id'] : $b['id'];
    return strcmp((string)$a['created_at'], (string)$b['created_at']) <= 0 ? $a['id'] : $b['id'];
}

/* Pasa todo lo de $sobra a $queda y borra $sobra. Devuelve ['ok'=>true, 'movidos'=>…, 'completados'=>…]
   o ['ok'=>false, 'error'=>…, 'codigo'=>…] (y en ese caso no se borró nada). */
function cmb_combinar($queda, $sobra, $quien = '') {
    if ($queda === $sobra) return ['ok' => false, 'codigo' => 400, 'error' => 'Elige dos fichas distintas'];
    $a = cmb_ficha($queda); $b = cmb_ficha($sobra);
    if (!$a || !$b) return ['ok' => false, 'codigo' => 404, 'error' => 'Una de las dos fichas ya no existe. Actualiza la lista de clientes.'];
    /* dos cuentas del sitio distintas no se pueden fundir en una */
    if (!empty($a['user_id']) && !empty($b['user_id']) && $a['user_id'] !== $b['user_id'])
        return ['ok' => false, 'codigo' => 409, 'error' => 'Cada ficha está ligada a una cuenta distinta del sitio web. Avísale a soporte para revisarlo antes de combinarlas.'];

    /* respaldo, fuera de la carpeta pública: la ficha que se va y qué filas tenía */
    $respaldo = ['cuando' => date('c'), 'quien' => $quien, 'queda' => $a, 'sobra' => $b, 'filas' => []];
    foreach (CMB_TABLAS as $t => $_) { [$c, $r] = cmb_rest('GET', $t . '?select=id&client_id=eq.' . $sobra); $respaldo['filas'][$t] = is_array($r) ? array_column($r, 'id') : []; }
    $dir = dirname(__DIR__, 2) . '/respaldos-combinar';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($dir . '/' . date('Ymd-His') . '-' . substr($sobra, 0, 8) . '.json', json_encode($respaldo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    /* 1. todo lo de la ficha que sobra pasa a la que queda */
    $movidos = [];
    foreach (CMB_TABLAS as $t => [$rotulo, $llevaNombre]) {
        $n = count($respaldo['filas'][$t]);
        if (!$n) continue;
        $cambio = ['client_id' => $queda]; if ($llevaNombre) $cambio['client_name'] = $a['name'];
        [$c] = cmb_rest('PATCH', $t . '?client_id=eq.' . $sobra, $cambio, 'return=minimal');
        if ($c >= 300) return ['ok' => false, 'codigo' => 500, 'error' => 'No se pudo pasar: ' . $rotulo . '. No se borró nada; vuelve a intentarlo.'];
        $movidos[$rotulo] = $n;
    }
    /* avisos de "posible misma persona" que estaban en la ficha que sobra */
    [$c, $vp] = cmb_rest('GET', 'vinculos_pendientes?select=user_id&client_id=eq.' . $sobra);
    foreach ((is_array($vp) ? $vp : []) as $v) {
        [$c2] = cmb_rest('PATCH', 'vinculos_pendientes?client_id=eq.' . $sobra . '&user_id=eq.' . $v['user_id'], ['client_id' => $queda], 'return=minimal');
        if ($c2 >= 300) cmb_rest('DELETE', 'vinculos_pendientes?client_id=eq.' . $sobra . '&user_id=eq.' . $v['user_id'], null, 'return=minimal');
    }

    /* 2. a la ficha que queda se le completa lo que le falte */
    $vacio = fn($v) => $v === null || trim((string)$v) === '';
    $completa = [];
    foreach (['phone', 'email', 'rut', 'gender', 'birthday'] as $k) if ($vacio($a[$k] ?? null) && !$vacio($b[$k] ?? null)) $completa[$k] = $b[$k];
    /* el nombre más completo ("Keidy Fuenmayor Rojas" antes que "Keidy Fuenmayor") */
    $na = cmb_plano($a['name'] ?? ''); $nb = cmb_plano($b['name'] ?? '');
    if ($nb !== $na && strpos($nb . ' ', $na . ' ') === 0) $completa['name'] = trim((string)$b['name']);
    $ta = trim((string)($a['notes'] ?? '')); $tb = trim((string)($b['notes'] ?? ''));
    $nota = $ta;
    if ($tb !== '' && stripos($tb, 'Creado por el asistente de WhatsApp') !== 0 && stripos($ta, $tb) === false) $nota = trim($nota . ($nota !== '' ? "\n" : '') . $tb);
    /* si cada ficha tenía un correo distinto, el que no queda se anota para no perderlo */
    $ma = strtolower(trim((string)($a['email'] ?? ''))); $mb = strtolower(trim((string)($b['email'] ?? '')));
    if ($ma !== '' && $mb !== '' && $ma !== $mb && stripos($nota, $mb) === false) $nota = trim($nota . ($nota !== '' ? "\n" : '') . 'Otro correo: ' . trim((string)$b['email']));
    if ($nota !== $ta) $completa['notes'] = $nota;
    if ($vacio($a['user_id'] ?? null) && !$vacio($b['user_id'] ?? null)) {
        cmb_rest('PATCH', 'clients?id=eq.' . $sobra, ['user_id' => null], 'return=minimal');
        $completa['user_id'] = $b['user_id'];
    }
    if ($completa) {
        [$c] = cmb_rest('PATCH', 'clients?id=eq.' . $queda, $completa, 'return=minimal');
        if ($c >= 300) return ['ok' => false, 'codigo' => 500, 'error' => 'Se pasó el historial, pero no se pudieron completar los datos de la ficha. No se borró nada; vuelve a intentarlo.'];
    }

    /* 3. la ficha que sobra se borra SOLO si ya no le queda nada (sus fichas clínicas se irían con ella) */
    $restan = cmb_cuantos($sobra);
    if ($restan === null || array_sum($restan) > 0) return ['ok' => false, 'codigo' => 500, 'error' => 'Quedaron registros sin pasar. No se borró nada; vuelve a intentarlo.'];
    [$c] = cmb_rest('DELETE', 'clients?id=eq.' . $sobra, null, 'return=minimal');
    if ($c >= 300 || cmb_ficha($sobra)) return ['ok' => false, 'codigo' => 500, 'error' => 'Todo quedó en una sola ficha, pero no se pudo borrar la duplicada. Bórrala desde la lista de clientes.'];

    unset($completa['user_id']);
    return ['ok' => true, 'movidos' => $movidos, 'completados' => array_keys($completa), 'queda' => $queda, 'nombre' => $completa['name'] ?? $a['name']];
}

/* De una lista de fichas, las parejas que son la misma persona con seguridad: [[queda, sobra], …].
   Cada ficha entra una sola vez por pasada (tres fichas iguales se resuelven en dos pasadas). */
function cmb_parejas_seguras(array $fichas) {
    $porTel = [];
    foreach ($fichas as $f) { $t = cmb_tel($f['phone'] ?? ''); if ($t !== '') $porTel[$t][] = $f; }
    $parejas = [];
    foreach ($porTel as $grupo) {
        if (count($grupo) < 2) continue;
        $usadas = [];
        for ($i = 0; $i < count($grupo); $i++) for ($j = $i + 1; $j < count($grupo); $j++) {
            $a = $grupo[$i]; $b = $grupo[$j];
            if (isset($usadas[$a['id']]) || isset($usadas[$b['id']]) || !cmb_misma_persona($a, $b)) continue;
            $usadas[$a['id']] = $usadas[$b['id']] = true;
            $parejas[] = [$a, $b];
        }
    }
    return $parejas;
}
/* Combina sola una pareja segura. Devuelve lo de cmb_combinar, con qué ficha quedó. */
function cmb_combinar_pareja($a, $b, $quien) {
    $ca = cmb_cuantos($a['id']); $cb = cmb_cuantos($b['id']);
    if ($ca === null || $cb === null) return ['ok' => false, 'codigo' => 500, 'error' => 'No se pudo leer el historial'];
    $queda = cmb_cual_queda($a, $b, $ca, $cb);
    return cmb_combinar($queda, $queda === $a['id'] ? $b['id'] : $a['id'], $quien);
}
