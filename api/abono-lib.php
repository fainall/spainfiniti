<?php
/**
 * Abono al reservar por el sitio web — Spa Infinity (pedido de Luis, 26-sep-2026)
 *
 * Desde el 28-sep (Luis): la hora NO se toma antes de pagar. El sitio revisa que
 * la hora se pueda reservar (probar_hora), lleva al cliente a Flow con los datos
 * de la reserva guardados en la orden, y la reserva se crea cuando llega el
 * pago. Si mientras pagaba otra persona tomó la hora, el pago queda registrado
 * y se avisa al spa para reagendar o devolver el abono.
 *
 * Lo que sigue describe también el camino anterior, que quedó para las
 * reservas en 'pago_pendiente' ya existentes y para las de gift card:
 * con el abono activado (panel → Administración → Pago en línea), una reserva
 * hecha en el sitio quedaba en 'pago_pendiente' hasta que se pagaba el abono en
 * Flow, la misma pasarela de las gift cards. Al pagarse:
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

/* "Abono de tu hora: Tratamiento con Ácido Nítrico+Alta Frecuencia-Tipo 1 · mié 30 sep, 10:00 hrs"
   Flow lo muestra como la descripción del pago, bajo "Estás realizando un pago a
   SPA INFINITY" (por eso no repite el nombre del spa). Admite hasta 100 caracteres. */
function abono_asunto($a, $monto) {
    $completo = abono_a_numero($a['price'] ?? '') > 0 && $monto >= abono_a_numero($a['price']);
    $inicio = $completo ? 'Pago de tu hora' : 'Abono de tu hora';
    $t = strtotime((string)$a['appt_date']);
    $cuando = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][(int)date('w', $t)] . ' ' . date('j', $t) . ' '
        . ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][(int)date('n', $t) - 1]
        . ', ' . substr((string)$a['start_time'], 0, 5) . ' hrs';
    $servicio = trim(preg_replace('/\s+/', ' ', (string)$a['service_name']));
    $cabe = 100 - mb_strlen("$inicio:  · $cuando");
    if (mb_strlen($servicio) > $cabe) $servicio = rtrim(mb_substr($servicio, 0, $cabe - 1)) . '…';
    return "$inicio: $servicio · $cuando";
}

/* la reserva (que todavía no existe) vista como una fila de appointments, para
   reutilizar el asunto, el monto y los correos */
function abono_como_cita(array $r) {
    return ['id' => '', 'client_id' => $r['cliente'] ?? null, 'client_name' => $r['nombre'] ?? '', 'client_phone' => $r['fono'] ?? '',
            'professional_id' => $r['profesional'] ?? null, 'service_name' => $r['servicio'] ?? '', 'appt_date' => $r['fecha'] ?? '',
            'start_time' => $r['inicio'] ?? '', 'end_time' => $r['fin'] ?? '', 'price' => $r['precio'] ?? ''];
}

/* ¿se puede reservar esta hora ahora? null si sí, el motivo si no (mismas reglas que reservar_hora) */
function abono_probar_hora(array $r) {
    $m = cr_supa('POST', 'rpc/probar_hora', ['p_profesional' => $r['profesional'], 'p_servicio' => $r['servicio'], 'p_fecha' => $r['fecha'],
        'p_inicio' => $r['inicio'], 'p_fin' => $r['fin'], 'p_nombre' => $r['nombre'], 'p_fono' => $r['fono']]);
    if (is_array($m) && isset($m['code'])) return 'No pudimos revisar la agenda en este momento';
    return (is_string($m) && $m !== '') ? $m : null;
}

/**
 * Crea la orden (archivo en api/orders) y el pago en Flow. $reserva son los datos
 * de la hora que se creará al pagar; $citaId, en el camino anterior, la reserva
 * en 'pago_pendiente' ya creada. Si Flow rechaza el correo del cliente ("The
 * userEmail: … is not valid", aunque esté bien escrito) se usa el del spa.
 * Devuelve ['url', 'orden'] o lanza el error de Flow.
 */
function abono_crear_pago($reserva, $citaId, $monto, $correo, $segundos) {
    require_once __DIR__ . '/flow-lib.php';
    $config = @include __DIR__ . '/flow-config.php';
    if (!is_array($config)) throw new Exception('Flow no está configurado');
    $siteUrl = rtrim($config['siteUrl'], '/');
    if (!is_dir(__DIR__ . '/orders')) mkdir(__DIR__ . '/orders', 0755, true);
    $cita = $citaId ? abono_reserva($citaId) : abono_como_cita($reserva);
    $intento = function ($correo) use ($reserva, $citaId, $cita, $monto, $segundos, $siteUrl, $config) {
        $commerceOrder = 'AB-' . date('YmdHis') . '-' . substr(uniqid(), -5);
        $archivo = __DIR__ . '/orders/' . $commerceOrder . '.json';
        $orden = [
            'commerceOrder'  => $commerceOrder,
            'tipo'           => 'abono_web',
            'amount'         => $monto,
            'appointment_id' => $citaId ?: null,
            'reserva'        => $reserva,          // la hora que se crea al pagar
            'client_name'    => $cita['client_name'] ?? '',
            'service_name'   => $cita['service_name'] ?? '',
            'price'          => $cita['price'] ?? '',
            'email'          => $correo,
            'createdAt'      => date('c'),
            'status'         => 'pending',
        ];
        file_put_contents($archivo, json_encode($orden, JSON_UNESCAPED_UNICODE));
        try {
            $flow = new FlowClient($config);
            $r = $flow->createPayment([
                'commerceOrder'   => $commerceOrder,
                'subject'         => abono_asunto($cita, $monto),
                'amount'          => $monto,
                'email'           => $correo,
                'urlConfirmation' => $siteUrl . '/api/flow-confirm.php',
                'urlReturn'       => $siteUrl . '/api/abono-retorno.php',
                'timeout'         => max(120, (int)$segundos),
            ]);
            return ['url' => $flow->paymentRedirectUrl($r), 'orden' => $commerceOrder];
        } catch (Exception $e) {
            $orden['status'] = 'no_creada';
            $orden['error'] = mb_substr($e->getMessage(), 0, 300);
            file_put_contents($archivo, json_encode($orden, JSON_UNESCAPED_UNICODE));
            throw $e;
        }
    };
    try {
        return $intento($correo);
    } catch (Exception $e) {
        if ($correo !== cliente_correo() && preg_match('/email/i', $e->getMessage())) {
            error_log('abono: Flow rechazó el correo del cliente, se usa el del spa: ' . $e->getMessage());
            return $intento(cliente_correo());
        }
        throw $e;
    }
}

/* Pagó, pero la hora ya no se pudo crear (otra persona la tomó mientras pagaba):
   el pago queda registrado como venta y se avisa al spa para reagendar o devolver */
function abono_sin_hora(array $order, $motivo) {
    $r = $order['reserva'] ?? [];
    $monto = (int)($order['amount'] ?? 0);
    $orden = (string)($order['commerceOrder'] ?? '');
    $ya = cr_supa('GET', 'sales?select=id&notes=like.*' . rawurlencode($orden) . '*');
    if (!$ya) {
        cr_supa('POST', 'sales', [
            'client_id'       => $r['cliente'] ?? null,
            'client_name'     => $r['nombre'] ?? '',
            'professional_id' => $r['profesional'] ?? null,
            'sale_date'       => date('Y-m-d'),
            'items'           => [['name' => $r['servicio'] ?? 'Servicio', 'qty' => 1, 'price' => abono_a_numero($r['precio'] ?? '') ?: $monto]],
            'total'           => $monto,
            'payment_method'  => 'Pago en línea (Flow)',
            'status'          => 'partial',
            'notes'           => 'Abono de ' . abono_pesos($monto) . ' sobre ' . abono_pesos(abono_a_numero($r['precio'] ?? '') ?: $monto)
                                 . '. Pagado en línea (Flow, orden ' . $orden . '). OJO: la hora ya no estaba disponible al pagar, hay que reagendar o devolver',
        ]);
    }
    $e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
    abono_aviso_spa('Abono pagado de una hora que se ocupó: ' . ($r['nombre'] ?? ''),
        '<p><strong>' . $e($r['nombre'] ?? '') . '</strong> pagó ' . $e(abono_pesos($monto)) . ' de abono en línea para '
        . $e($r['servicio'] ?? '') . ' el ' . $e(cr_dia($r['fecha'] ?? '')) . ' a las ' . $e(substr((string)($r['inicio'] ?? ''), 0, 5))
        . ' hrs, pero mientras pagaba esa hora dejó de estar disponible (' . $e($motivo) . ').</p>'
        . '<p>La reserva no se creó. Hay que contactarla para agendar otra hora o devolver el abono. Teléfono: +'
        . $e(ltrim((string)($r['fono'] ?? ''), '+')) . '<br>Orden Flow: ' . $e($orden) . '</p>');
    return 'perdida';
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
function abono_confirmar(array &$order) {
    /* camino nuevo: la hora se crea ahora que llegó el pago (como servidor: queda Reservada) */
    if (empty($order['appointment_id']) && !empty($order['reserva'])) {
        $r = $order['reserva'];
        $nueva = cr_supa('POST', 'rpc/reservar_hora', [
            'p_profesional' => $r['profesional'], 'p_cliente' => $r['cliente'] ?: null, 'p_nombre' => $r['nombre'], 'p_fono' => $r['fono'],
            'p_servicio' => $r['servicio'], 'p_fecha' => $r['fecha'], 'p_inicio' => $r['inicio'], 'p_fin' => $r['fin'],
            'p_precio' => $r['precio'] ?: null, 'p_nota' => $r['nota'] ?: null, 'p_origen' => 'web']);
        if (!is_string($nueva) || !preg_match('/^[0-9a-f-]{36}$/i', $nueva)) {
            $motivo = is_array($nueva) ? (string)($nueva['message'] ?? 'no se pudo reservar') : 'no se pudo reservar';
            error_log('abono: pagó pero no se pudo crear la hora (' . ($order['commerceOrder'] ?? '') . '): ' . $motivo);
            return abono_sin_hora($order, $motivo);
        }
        $order['appointment_id'] = $nueva;
    }
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
