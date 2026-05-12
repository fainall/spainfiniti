<?php
/**
 * Preview del email Gift Card.
 * https://spainfinity.cl/api/preview-giftcard-email.php
 */
require_once __DIR__ . '/email-template.php';

$data = [
    'from'      => 'Carolina Pérez',
    'to'        => 'María González',
    'fromEmail' => 'carolina@example.com',
    'toEmail'   => 'maria@example.com',
    'message'   => "¡Feliz cumpleaños, mi amiga querida!\nQue disfrutes este momento de relajación 100%, te lo mereces. Con todo mi cariño.",
    'code'      => 'GC-7K2M9-Q4P',
    'total'     => '$48.500',
    'services'  => [
        ['name' => 'Masaje Descontracturante 60 min', 'price' => '$28.500', 'duration' => '60 min'],
        ['name' => 'Limpieza Facial Profunda',         'price' => '$20.000', 'duration' => '45 min'],
    ],
];

header('Content-Type: text/html; charset=UTF-8');
echo buildGiftCardEmail($data);
