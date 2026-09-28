-- ════════════════════════════════════════════════════════════════
-- Abono web: la hora se reserva recién al pagar (pedido de Luis, 28-sep-2026)
--
-- Antes la reserva se creaba en 'pago_pendiente' y apartaba la hora mientras
-- el cliente pagaba. Ahora el sitio solo revisa que la hora se pueda tomar,
-- va a Flow, y la reserva se crea cuando llega el pago (api/abono-lib.php).
--
-- probar_hora revisa una hora con exactamente las mismas reglas de
-- reservar_hora (horario, descansos, choques, plazo, tope por teléfono): la
-- reserva de prueba se hace dentro de un bloque que se deshace en el acto.
-- Devuelve null si la hora se puede tomar, o el motivo si no.
-- Solo la usa el servidor (llave de servicio).
-- ════════════════════════════════════════════════════════════════

create or replace function public.probar_hora(p_profesional uuid, p_servicio text, p_fecha date,
                                              p_inicio time, p_fin time, p_nombre text default 'Prueba', p_fono text default null)
returns text language plpgsql security definer set search_path = '' as $$
begin
  begin
    perform public.reservar_hora(p_profesional, null, coalesce(nullif(trim(p_nombre), ''), 'Prueba'), p_fono, p_servicio,
                                 p_fecha, p_inicio, p_fin, null, null, 'web');
    -- se deshace a propósito: solo se quería saber si se podía
    raise exception using errcode = 'P0099', message = 'hora disponible';
  exception when others then
    if sqlstate = 'P0099' then return null; end if;
    return sqlerrm;
  end;
end $$;

revoke all on function public.probar_hora(uuid, text, date, time, time, text, text) from public, anon, authenticated;
grant execute on function public.probar_hora(uuid, text, date, time, time, text, text) to service_role;

-- que la API vea la función nueva de inmediato
notify pgrst, 'reload schema';
