<?php
/**
 * IDENTIDAD DEL CLIENTE — parte del servidor
 *
 * Copiar este archivo a api/cliente-config.php y poner los datos del
 * negocio. Es el gemelo de config-cliente.js: los mismos valores, para
 * los scripts que corren en el servidor (pagos, recordatorios, bot).
 *
 * cliente-config.php SÍ se sube al servidor pero NO va a git, porque
 * cada cliente tiene el suyo.
 */
return [

    /* ── Base de datos ── */
    'supabaseUrl' => 'https://XXXXXXXXXXXX.supabase.co',
    // Llave anónima (Settings → API). No es secreta.
    'supabaseAnon' => 'eyJ...',

    /* ── El negocio ── */
    'nombre'    => 'Nombre del negocio',
    'dominio'   => 'https://midominio.cl',
    'correo'    => 'contacto@midominio.cl',
    'telefono'  => '+56 9 0000 0000',
    'whatsapp'  => '56900000000',   // solo dígitos
    'direccion' => 'Calle 123, oficina 45, Santiago',
];
