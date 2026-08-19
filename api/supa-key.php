<?php
/**
 * Llave de acceso a Supabase para los scripts del servidor.
 *
 * Devuelve la llave de servicio (service_role) si está configurada en bot-config.php;
 * si no, cae a la llave pública (anon) para no romper nada.
 *
 * La llave de servicio salta todas las reglas de seguridad de la base, por eso vive
 * solo en el servidor (bot-config.php está fuera de git y con permisos 600) y NUNCA
 * debe aparecer en el navegador ni en el HTML.
 */
function supa_key() {
    static $key = null;
    if ($key !== null) return $key;

    $anon = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA';

    $cfgFile = __DIR__ . '/bot-config.php';
    if (file_exists($cfgFile)) {
        $cfg = require $cfgFile;
        if (!empty($cfg['serviceKey'])) { $key = $cfg['serviceKey']; return $key; }
    }
    $key = $anon;
    return $key;
}

function supa_url() { return 'https://bxwamppamqxtncvfdycy.supabase.co'; }
