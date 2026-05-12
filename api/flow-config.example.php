<?php
/**
 * Configuración Flow.cl — RENOMBRAR a flow-config.php y completar
 *
 * NO subir flow-config.php a Git (está en .gitignore).
 * Para credenciales reales editar la copia en el servidor.
 */

return [
    // Credenciales — obtener en flow.cl → Mi Cuenta → Credenciales
    'apiKey'    => 'TU_API_KEY',
    'secretKey' => 'TU_SECRET_KEY',

    // Modo: 'sandbox' para pruebas, 'production' para pagos reales
    'mode'      => 'sandbox',

    // URL base del sitio (para que Flow nos devuelva)
    'siteUrl'   => 'https://spainfinity.cl',

    // Email donde llegan notificaciones internas de cada compra
    'notifyEmail' => 'reservainfinity@spainfinity.cl',
];
