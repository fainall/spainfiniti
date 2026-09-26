<?php
/**
 * Abono al reservar por el sitio web — Spa Infinity (pedido de Luis, 26-sep-2026)
 *
 * Con el abono activado (panel → Administración → Local), una reserva hecha en
 * el sitio queda en 'pago_pendiente' hasta que se paga el abono en Flow, la
 * misma pasarela de las gift cards. Al pagarse:
 *   · la reserva pasa a 'reserved';
 *   · se registra el abono como venta parcial ('partial', "Abono de $X sobre $Y"),
 *     igual que un abono cobrado en el panel: la reserva se ve abonada y al
 *     cobrar el resto en el local el panel descuenta lo pagado;
 *   · sale el correo de confirmación, al cliente y al spa.
 * Si no se paga dentro del plazo, la base libera la hora sola
 * (liberar_abonos_vencidos). Si el pago llega tarde y la hora sigue libre, se
 * recupera; si ya la tomó otra persona, se avisa al spa para devolverlo.
 *
 * Lo usan abono-web.php (crea el pago), flow-confirm.php (aviso de Flow) y
 * abono-retorno.php (cuando el cliente vuelve de pagar).
 */
require_once __DIR__ . '/correo-reserva-lib.php';
date_default_timezone_set('America/Santiago');

const ABONO_FLOW_MINIMO = 350;   // lo menos que acepta Flow

/* la configuración del panel, con los mismos valores por omisión que la base */
function abono_config() {
    $r = cr_supa('POST', 'rpc/abono_web_config', new stdClass);
    $c = (is_array($r) && isset($r[0])) ? $r[0] : [];
    return [
        'activo'  => !empty($c['activo']),
        'monto'   => max(ABONO_FLOW_MINIMO, (int)($c['monto'] ?? 10000)),
        'minutos' => max(5, (int)($c['minutos'] ?? 20)),
    ];
}

function abono_pesos($n) { return '$' . number_format((int)$n, 0, ',', '.'); }
function abono_a_numero($t) { return (int)preg_replace('/[^0-9]/', '', (string)$t); }

/* lo que se cobra: el abono, o el servicio completo si cuesta menos */
function abono_monto($a, $cfg) {
    $precio = abono_a_numero($a['price'] ?? '');
    $monto = ($precio > 0) ? min($cfg['monto'], $precio) : $cfg['monto'];
    return max(ABONO_FLOW_MINIMO, $monto);
}

function abono_reserva($id) {
    return cr_supa('GET', 'appointments?select=*&id=eq.' . rawurlencode($id))[0] ?? null;
}

/* ¿sigue libre la hora de esta reserva con su profesional? (para recuperar un pago tardío) */
function abono_hora_libre($a) {
    $otras = cr_supa('GET', 'appointments?select=id,start_time,end_time,status'
        . '&professional_id=eq.' . rawurlencode($a['professional_id'])
        . '&appt_date=eq.' . rawurlencode($a['appt_date'])
        . '&id=neq.' . rawurlencode($a['id'])
        . '&status=neq.cancelled') ?: [];
    foreach ($otras as $o) {
        if ($o['start_time'] < $a['end_time'] && $o['end_time'] > $a['start_time']) return false;
    }
    return true;
}

function abono_aviso_spa($asunto, $html) {
    $cab = ['MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8', 'From: Spa Infinity <' . cliente_correo() . '>'];
    @mail(cliente_correo(), '=?UTF-8?B?' . base64_encode($asunto) . '?=', $html, implode("\r\n", $cab), '-f' . cliente_correo());
}

/**
 * Deja la reserva confirmada con su abono. Se puede llamar más de una vez con
 * la misma orden: la venta y el correo salen una sola vez.
 * Devuelve 'confirmada' | 'recuperada' | 'perdida' | 'sin_reserva'.
 */
function abono_confirmar(array $order) {
    $a = abono_reserva((string)($order['appointment_id'] ?? ''));
    if (!$a) return 'sin_reserva';
    $monto = (int)($order['amount'] ?? 0);
    $resultado = 'confirmada';

    if (($a['status'] ?? '') === 'pago_pendiente') {
        $hecho = cr_supa('PATCH', 'appointments?id=eq.' . rawurlencode($a['id']) . '&status=eq.pago_pendiente', ['status' => 'reserved']);
        /* justo se venció entre la lectura y el cambio: se trata como pago tardío */
        if (!$hecho) $a = abono_reserva($a['id']) ?: $a;
    }
    if (($a['status'] ?? '') === 'pago_pendiente') {
        // ya quedó confirmada arriba
    } elseif (($a['status'] ?? '') === 'cancelled') {
        /* pagó después del plazo: si nadie tomó la hora, se recupera */
        if (strpos((string)$a['notes'], 'abono a tiempo') !== false && abono_hora_libre($a)) {
            cr_supa('PATCH', 'appointments?id=eq.' . rawurlencode($a['id']) . '&status=eq.cancelled', [
                'status' => 'reserved',
                'notes'  => trim((string)$a['notes']) . ' · El abono se pagó después del plazo y la hora seguía libre: se recuperó',
            ]);
            $resultado = 'recuperada';
        } else {
            $resultado = 'perdida';
        }
    }

    /* el abono queda como venta, una sola vez por orden */
    $orden = (string)($order['commerceOrder'] ?? '');
    $ya = cr_supa('GET', 'sales?select=id&appointment_id=eq.' . rawurlencode($a['id']) . '&notes=like.*' . rawurlencode($orden) . '*');
    if (!$ya) {
        $precio = abono_a_numero($a['price'] ?? '');
        $completo = $precio > 0 && $monto >= $precio;
        cr_supa('POST', 'sales', [
            'client_id'       => $a['client_id'] ?: null,
            'client_name'     => $a['client_name'] ?? '',
            'professional_id' => $a['professional_id'] ?: null,
            'appointment_id'  => $a['id'],
            'sale_date'       => date('Y-m-d'),
            'items'           => [['name' => $a['service_name'] ?: 'Servicio', 'qty' => 1, 'price' => $precio > 0 ? $precio : $monto]],
            'total'           => $monto,
            'payment_method'  => 'Pago en línea (Flow)',
            'status'          => $completo ? 'paid' : 'partial',
            'notes'           => ($completo ? 'Pagado completo en línea al reservar' : 'Abono de ' . abono_pesos($monto) . ' sobre ' . abono_pesos($precio) . '. Pagado en línea al reservar')
                                 . ' (Flow, orden ' . $orden . ')'
                                 . ($resultado === 'perdida' ? '. OJO: la hora ya no estaba disponible, hay que reagendar o devolver' : ''),
        ]);
    }

    if ($resultado === 'perdida') {
        $e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        abono_aviso_spa('Abono pagado de una hora que ya no estaba: ' . ($a['client_name'] ?? ''),
            '<p><strong>' . $e($a['client_name']) . '</strong> pagó ' . $e(abono_pesos($monto)) . ' de abono en línea para '
            . $e($a['service_name']) . ' el ' . $e(cr_dia($a['appt_date'])) . ' a las ' . $e(substr($a['start_time'], 0, 5))
            . ' hrs, pero el pago llegó después del plazo y esa hora ya la tomó otra persona.</p>'
            . '<p>Hay que contactarla para reagendar o devolver el abono. Teléfono: +' . $e(ltrim((string)$a['client_phone'], '+'))
            . '<br>Orden Flow: ' . $e($orden) . '</p>');
        return $resultado;
    }

    enviar_correo_reserva($a['id'], false, ['abono' => $monto]);
    return $resultado;
}

/**
 * Atiende el estado que informa Flow para una orden de abono. Lo usan el aviso
 * de Flow y la vuelta del cliente, que pueden llegar en cualquier orden: el
 * archivo de la orden se bloquea para que no se procese dos veces.
 * Devuelve el estado de la orden: 'paid' | 'rejected' | 'cancelled' | 'pending' | …
 */
function abono_procesar($orderFile, array $status, $token) {
    $fp = @fopen($orderFile, 'c+');
    if (!$fp) return 'error';
    flock($fp, LOCK_EX);
    $order = json_decode(stream_get_contents($fp), true);
    $guardar = function ($o) use ($fp) { ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($o, JSON_UNESCAPED_UNICODE)); fflush($fp); };
    $fin = function ($estado) use ($fp) { flock($fp, LOCK_UN); fclose($fp); return $estado; };
    if (!is_array($order)) return $fin('error');
    if (($order['status'] ?? '') === 'paid') return $fin('paid');

    $flowEstado = (int)($status['status'] ?? 0);   // 1 pendiente, 2 pagada, 3 rechazada, 4 anulada
    if ($flowEstado === 3 || $flowEstado === 4) {
        $order['status'] = $flowEstado === 3 ? 'rejected' : 'cancelled';
        $order['flowData'] = $status;
        $guardar($order);
        return $fin($order['status']);
    }
    if ($flowEstado !== 2) return $fin('pending');

    $pagado = (int)($status['amount'] ?? 0);
    if ($pagado > 0 && $pagado !== (int)($order['amount'] ?? 0)) {
        error_log("abono: monto pagado $pagado distinto de la orden {$order['amount']} ({$order['commerceOrder']})");
        $order['status'] = 'amount_mismatch';
        $order['flowData'] = $status;
        $guardar($order);
        return $fin('amount_mismatch');
    }

    $order['status'] = 'paid';
    $order['flowToken'] = $token;
    $order['paidAt'] = date('c');
    $order['flowData'] = $status;
    $guardar($order);
    flock($fp, LOCK_UN); fclose($fp);

    $order['resultado'] = abono_confirmar($order);
    file_put_contents($orderFile, json_encode($order, JSON_UNESCAPED_UNICODE));
    return 'paid';
}
