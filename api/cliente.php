<?php
/**
 * Datos del negocio para los scripts del servidor.
 *
 * Salen de api/cliente-config.php, que es lo unico que cambia al montar
 * el sistema para otro cliente. Si ese archivo no existe se usan los
 * valores de siempre, para que nada deje de funcionar.
 */
function cliente($clave, $porDefecto = '') {
    static $cfg = null;
    if ($cfg === null) {
        $f = __DIR__ . '/cliente-config.php';
        $cfg = file_exists($f) ? (require $f) : [];
        if (!is_array($cfg)) $cfg = [];
    }
    $v = $cfg[$clave] ?? '';
    return ($v !== '' && $v !== null) ? $v : $porDefecto;
}

/** Numero de WhatsApp, solo digitos */
function cliente_whatsapp() { return cliente('whatsapp', '56986688771'); }
/** Correo del local */
function cliente_correo()   { return cliente('correo', 'reservainfinity@spainfinity.cl'); }
/** Nombre del negocio */
function cliente_nombre()   { return cliente('nombre', 'Spa Infinity'); }
/** Dominio, sin barra final */
function cliente_dominio()  { return rtrim(cliente('dominio', 'https://spainfinity.cl'), '/'); }
