-- ════════════════════════════════════════════════════════════════
-- Abono al reservar por el sitio web (pedido de Luis, 26-sep-2026)
--
-- Con el abono activado (Administración → Local), las reservas que entran
-- por el sitio quedan en 'pago_pendiente' hasta que se paga el abono en
-- Flow. Si no se paga dentro del plazo, la hora se libera sola. Mariet
-- (WhatsApp) y el panel no cambian. La configuración vive en
-- bot_config.local_info.abonoWeb = { activo, monto, minutos }.
-- ════════════════════════════════════════════════════════════════

-- la configuración, con valores por omisión
create or replace function public.abono_web_config()
returns table (activo boolean, monto int, minutos int)
language sql stable security definer set search_path = '' as $$
  select coalesce((b.local_info->'abonoWeb'->>'activo')::boolean, false),
         greatest(coalesce(nullif(b.local_info->'abonoWeb'->>'monto', '')::int, 10000), 350),
         greatest(coalesce(nullif(b.local_info->'abonoWeb'->>'minutos', '')::int, 20), 5)
    from public.bot_config b where b.id = 1
  union all
  select false, 10000, 20 where not exists (select 1 from public.bot_config where id = 1)
$$;
grant execute on function public.abono_web_config() to anon, authenticated;

-- libera las horas web cuyo abono no se pagó a tiempo (se puede llamar
-- sin sesión: solo cancela las vencidas)
create or replace function public.liberar_abonos_vencidos()
returns int language plpgsql security definer set search_path = '' as $$
declare v_min int; v_n int;
begin
  select c.minutos into v_min from public.abono_web_config() c;
  update public.appointments
     set status = 'cancelled',
         notes = concat_ws(' · ', nullif(notes, ''), 'No se pagó el abono a tiempo: la hora se liberó sola')
   where status = 'pago_pendiente'
     and created_at < now() - make_interval(mins => coalesce(v_min, 20));
  get diagnostics v_n = row_count;
  return v_n;
end $$;
grant execute on function public.liberar_abonos_vencidos() to anon, authenticated;

-- las horas ocupadas que ve el sitio: una hora esperando pago se muestra
-- ocupada solo mientras corre su plazo
create or replace view public.public_busy as
select a.professional_id, a.appt_date, a.start_time, a.end_time, a.service_name, a.status
  from public.appointments a
 where a.status is distinct from 'cancelled'
   and a.appt_date >= (current_date - '1 day'::interval)
   and not (a.status = 'pago_pendiente'
            and a.created_at < now() - make_interval(mins => (select c.minutos from public.abono_web_config() c)));

-- la reserva: igual que antes, con las dos diferencias marcadas
create or replace function public.reservar_hora(p_profesional uuid, p_cliente uuid, p_nombre text, p_fono text, p_servicio text, p_fecha date,
  p_inicio time without time zone, p_fin time without time zone, p_precio text default null::text, p_nota text default null::text,
  p_origen text default 'web'::text)
returns uuid language plpgsql security definer set search_path to 'public' as $function$
declare
  v_id uuid; v_max int; v_hoy date; v_ahora time; v_dow int; v_dias jsonb; v_ws time; v_we time;
  v_hpd jsonb; v_dia jsonb; v_b jsonb; v_dur int; v_svc_dur int; v_fono text; v_n int;
begin
  /* las horas web que no se pagaron a tiempo quedan libres antes de revisar */
  perform public.liberar_abonos_vencidos();

  /* la hora de Chile, no la del servidor */
  v_hoy := (now() at time zone 'America/Santiago')::date;
  v_ahora := (now() at time zone 'America/Santiago')::time;

  if p_fecha < v_hoy then
    raise exception 'No se puede reservar en una fecha pasada';
  end if;
  if p_origen <> 'panel' and p_fecha = v_hoy and p_inicio < v_ahora then
    raise exception 'Esa hora ya paso';
  end if;
  if p_fin <= p_inicio then
    raise exception 'La hora de termino debe ser posterior a la de inicio';
  end if;
  if length(trim(coalesce(p_nombre,''))) < 2 then
    raise exception 'Falta el nombre';
  end if;

  /* plazo maximo de anticipacion, solo para lo que entra por el sitio */
  if p_origen <> 'panel' then
    select nullif(local_info->>'maxDias','')::int into v_max from bot_config where id = 1;
    v_max := greatest(coalesce(v_max, 14), 1);
    if p_fecha > v_hoy + v_max then
      raise exception 'Por ahora solo se puede reservar hasta % dias por delante. Escribenos si necesitas una fecha mas lejana.', v_max;
    end if;
  end if;

  /* duracion razonable, y la del servicio si se conoce */
  v_dur := (extract(epoch from (p_fin - p_inicio)) / 60)::int;
  if v_dur < 10 or v_dur > 240 then
    raise exception 'Duracion no valida';
  end if;
  select duracion_min(duration) into v_svc_dur
    from services where lower(name) = lower(trim(coalesce(p_servicio,''))) limit 1;
  if not found then
    raise exception 'Ese servicio no existe';
  end if;
  if v_svc_dur is not null and v_svc_dur > 0 and v_dur <> v_svc_dur then
    raise exception 'La duracion no corresponde al servicio';
  end if;

  /* el profesional y su horario de ese dia */
  select work_days, work_start, work_end into v_dias, v_ws, v_we
    from professionals where id = p_profesional and active is distinct from false;
  if not found then
    raise exception 'Ese profesional no esta disponible';
  end if;
  v_dow := extract(dow from p_fecha)::int; -- 0 = domingo
  /* el panel guarda el domingo como 0; un gestor antiguo lo guardaba como 7 */
  if v_dias is not null and jsonb_typeof(v_dias) = 'array'
     and not (v_dias @> to_jsonb(v_dow) or (v_dow = 0 and v_dias @> '7'::jsonb)) then
    raise exception 'El profesional no atiende ese dia';
  end if;
  select prof_meta -> p_profesional::text -> 'horasPorDia' -> v_dow::text into v_hpd
    from bot_config where id = 1;
  if v_hpd is not null and jsonb_typeof(v_hpd) = 'array' and coalesce(v_hpd->>0,'') <> '' then
    v_ws := (v_hpd->>0)::time; v_we := (v_hpd->>1)::time;
  end if;
  if v_ws is not null and v_we is not null and (p_inicio < v_ws or p_fin > v_we) then
    raise exception 'Esa hora esta fuera del horario del profesional';
  end if;

  /* sus descansos */
  for v_b in
    select value from jsonb_array_elements(coalesce(
      (select prof_meta -> p_profesional::text -> 'breaks' -> v_dow::text from bot_config where id = 1),
      '[]'::jsonb))
  loop
    if jsonb_typeof(v_b) = 'array' and coalesce(v_b->>0,'') <> ''
       and p_inicio < (v_b->>1)::time and p_fin > (v_b->>0)::time then
      raise exception 'Esa hora cae en un descanso';
    end if;
  end loop;

  /* y el horario del local */
  select local_info -> 'hours' -> v_dow::text into v_dia from bot_config where id = 1;
  if v_dia is not null and jsonb_typeof(v_dia) = 'array' then
    if (v_dia -> 2)::text = 'false' then
      raise exception 'El local esta cerrado ese dia';
    end if;
    if coalesce(v_dia->>0,'') <> '' and (p_inicio < (v_dia->>0)::time or p_fin > (v_dia->>1)::time) then
      raise exception 'Esa hora esta fuera del horario del local';
    end if;
  end if;

  /* Freno al abuso: tope de reservas pendientes por telefono desde fuera.
     Se lee de bot_config.local_info.maxPendientes. Vacio o 0 = sin tope. */
  select nullif(local_info->>'maxPendientes','')::int into v_max from bot_config where id = 1;
  v_fono := regexp_replace(coalesce(p_fono,''), '\D', '', 'g');
  if coalesce(v_max, 0) > 0 and p_origen <> 'panel' and length(v_fono) >= 8 then
    select count(*) into v_n from appointments
     where regexp_replace(coalesce(client_phone,''), '\D', '', 'g') like '%' || right(v_fono, 8)
       and appt_date >= v_hoy
       and status is distinct from 'cancelled'
       and origen in ('web','bot');
    if v_n >= v_max then
      raise exception 'Ya tienes % reservas pendientes. Escribenos por WhatsApp para agendar otra.', v_max;
    end if;
  end if;

  /* candado por profesional y dia: dos reservas a la vez ya no se cuelan */
  perform pg_advisory_xact_lock(hashtext(p_profesional::text || '|' || p_fecha::text));

  if exists (select 1 from appointments
              where professional_id = p_profesional
                and appt_date = p_fecha
                and status is distinct from 'cancelled'
                and start_time < p_fin
                and end_time > p_inicio) then
    raise exception 'Esa hora ya esta tomada';
  end if;

  insert into appointments (professional_id, client_id, client_name, client_phone,
                            service_name, appt_date, start_time, end_time,
                            status, price, notes, origen)
  values (p_profesional, p_cliente, trim(p_nombre), p_fono,
          p_servicio, p_fecha, p_inicio, p_fin,
          /* desde el sitio web, con el abono activado, la hora espera el pago. Lo
             decide quién llama, no p_origen (que lo manda el navegador): solo el
             equipo del panel o el servidor reservan sin abono */
          case when (select c.activo from public.abono_web_config() c)
                and coalesce(auth.role(), 'anon') <> 'service_role' and not public.is_panel_user()
               then 'pago_pendiente' else 'reserved' end,
          p_precio, coalesce(p_nota, 'Reservado desde el sitio web'),
          case when p_origen in ('web','bot','panel') then p_origen else 'web' end)
  returning id into v_id;

  return v_id;
end $function$;



-- una hora que espera el abono no se cambia (se paga o se suelta)
create or replace function public.mi_cita_para_cambiar(p_id uuid)
returns public.appointments language plpgsql security definer set search_path = '' as $$
declare a public.appointments;
begin
  if auth.uid() is null then raise exception 'Necesitas ingresar a tu cuenta'; end if;
  select * into a from public.appointments where id = p_id and user_id = auth.uid() for update;
  if not found then raise exception 'No encontramos esa hora en tu cuenta'; end if;
  if a.status = 'cancelled' then raise exception 'Esa hora ya estaba cancelada'; end if;
  if a.status = 'pago_pendiente' then
    raise exception 'Esa hora todavía espera el pago del abono: págalo, o cancélala y reserva otra';
  end if;
  if not public.cita_cambiable(a) then
    raise exception 'Faltan menos de % horas para tu cita: para cancelarla o cambiarla escríbenos por WhatsApp', public.horas_para_cambiar();
  end if;
  return a;
end $$;
revoke all on function public.mi_cita_para_cambiar(uuid) from public, anon, authenticated;

-- soltar una hora que espera el abono se puede siempre: nunca se confirmó
create or replace function public.cancelar_mi_cita(p_id uuid)
returns uuid language plpgsql security definer set search_path = '' as $$
declare a public.appointments;
begin
  if auth.uid() is null then raise exception 'Necesitas ingresar a tu cuenta'; end if;
  update public.appointments
     set status = 'cancelled',
         notes = concat_ws(' · ', nullif(notes, ''), 'Soltada por el cliente antes de pagar el abono')
   where id = p_id and user_id = auth.uid() and status = 'pago_pendiente'
  returning * into a;
  if found then return a.id; end if;

  a := public.mi_cita_para_cambiar(p_id);
  update public.appointments
     set status = 'cancelled',
         notes = concat_ws(' · ', nullif(notes, ''), 'Cancelada por el cliente desde su cuenta el '
                 || to_char(now() at time zone 'America/Santiago', 'DD/MM HH24:MI'))
   where id = a.id;
  return a.id;
end $$;
revoke all on function public.cancelar_mi_cita(uuid) from public, anon;
grant execute on function public.cancelar_mi_cita(uuid) to authenticated;

-- al cambiar la hora desde la cuenta, la nueva no vuelve a cobrar abono
-- (ya se pagó o no correspondía) y el abono pagado pasa a la hora nueva
create or replace function public.reprogramar_mi_cita(p_id uuid, p_profesional uuid, p_fecha date,
                                                       p_inicio time, p_fin time, p_precio text default null)
returns uuid language plpgsql security definer set search_path = '' as $$
declare a public.appointments; v_nueva uuid;
begin
  a := public.mi_cita_para_cambiar(p_id);
  -- se libera la hora anterior primero: así se puede mover dentro del mismo tramo
  update public.appointments set status = 'cancelled' where id = a.id;
  v_nueva := public.reservar_hora(p_profesional, a.client_id, a.client_name, a.client_phone, a.service_name,
                                  p_fecha, p_inicio, p_fin, coalesce(p_precio, a.price),
                                  'Cambiada por el cliente desde su cuenta (antes: '
                                    || to_char(a.appt_date, 'DD/MM') || ' ' || to_char(a.start_time, 'HH24:MI') || ')', 'web');
  update public.appointments set status = 'reserved' where id = v_nueva and status = 'pago_pendiente';
  update public.sales set appointment_id = v_nueva where appointment_id = a.id;
  update public.appointments
     set notes = concat_ws(' · ', nullif(notes, ''), 'Cambiada por el cliente a '
                 || to_char(p_fecha, 'DD/MM') || ' ' || to_char(p_inicio, 'HH24:MI'))
   where id = a.id;
  return v_nueva;
end $$;
revoke all on function public.reprogramar_mi_cita(uuid, uuid, date, time, time, text) from public, anon;
grant execute on function public.reprogramar_mi_cita(uuid, uuid, date, time, time, text) to authenticated;
