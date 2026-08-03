<?php
/* Copia este archivo como api/bot-config.php (NO subir a git) y completa.
   - anthropicKey: API key de Anthropic (https://console.anthropic.com) → habilita el bot.
   - Los campos de WhatsApp se completan cuando conectes Meta (fase en vivo). */
return [
    // ── IA (obligatorio para que el bot piense) ──
    'openaiKey'     => 'sk-proj-xxxxxxxx',   // API key de OpenAI
    'model'         => 'gpt-4o-mini',

    // ── WhatsApp Cloud API (para la conexión en vivo) ──
    'waToken'       => '',                       // token permanente de Meta
    'waPhoneId'     => '',                       // Phone Number ID del número
    'waVerifyToken' => 'spa-infinity-verify',    // token para verificar el webhook en Meta

    // ── Datos del negocio que el bot conoce ──
    'businessName'  => 'Spa Infinity',
    'address'       => 'Santo Domingo 1083, Of. 502, Santiago Centro',
    'bookingBase'   => 'https://spainfinity.cl',
];
