-- ════════════════════════════════════════════════════════════════
-- El tope de reservas pendientes por telefono pasa a ser configurable,
-- y queda apagado.
--
-- reservar_hora() rechazaba la cuarta reserva desde la web con el mismo
-- telefono si ya habia tres pendientes. Es un freno razonable contra quien
-- quiera llenar la agenda de reservas falsas, pero mientras el equipo
-- prueba el sistema molesta: Luis hacia tres pruebas y la cuarta "no se
-- realizaba".
--
-- Ahora el tope se lee de bot_config.local_info.maxPendientes. Vacio o 0
-- (como queda ahora) = sin tope. Para volver a encenderlo mas adelante:
--   update bot_config set local_info = local_info || '{"maxPendientes": 3}' where id = 1;
-- ════════════════════════════════════════════════════════════════

create or replace function reservar_hora(
  p_profesional uuid,
  p_cliente     uuid,
  p_nombre      text,
  p_fono        text,
  p_servicio    text,
  p_fecha       date,
  p_inicio      time,
  p_fin         time,
  p_precio      text default null,
  p_nota        text default null,
  p_origen      text default 'web'
) returns uuid
language plpgsql security definer set search_path = public as $$
declare
  v_id      uuid;
  v_max     int;
  v_hoy     date;
  v_ahora   time;
  v_dow     int;
  v_dias    jsonb;
  v_ws      time;
  v_we      time;
  v_hpd     jsonb;
  v_dia     jsonb;
  v_b       jsonb;
  v_dur     int;
  v_svc_dur int;
  v_fono    text;
  v_n       int;
begin
  /* la hora de Chile, no la del servidor */
  v_hoy   := (now() at time zone 'America/Santiago')::date;
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
  v_dow := extract(dow from p_fecha)::int;              -- 0 = domingo
  /* el panel guarda el domingo como 0; un gestor antiguo lo guardaba como 7 */
  if v_dias is not null and jsonb_typeof(v_dias) = 'array'
     and not (v_dias @> to_jsonb(v_dow) or (v_dow = 0 and v_dias @> '7'::jsonb)) then
    raise exception 'El profesional no atiende ese dia';
  end if;
  select prof_meta -> p_profesional::text -> 'horasPorDia' -> v_dow::text into v_hpd
    from bot_config where id = 1;
  if v_hpd is not null and jsonb_typeof(v_hpd) = 'array' and coalesce(v_hpd->>0,'') <> '' then
    v_ws := (v_hpd->>0)::time;  v_we := (v_hpd->>1)::time;
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
     Se lee de bot_config.local_info.maxPendientes. Vacio o 0 = sin tope.
     Mientras el equipo prueba el sistema queda apagado: con el tope en 3,
     Luis hacia tres reservas de prueba y la cuarta se rechazaba. */
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
                and end_time   > p_inicio) then
    raise exception 'Esa hora ya esta tomada';
  end if;

  insert into appointments (professional_id, client_id, client_name, client_phone,
                            service_name, appt_date, start_time, end_time,
                            status, price, notes, origen)
  values (p_profesional, p_cliente, trim(p_nombre), p_fono,
          p_servicio, p_fecha, p_inicio, p_fin,
          'reserved', p_precio, coalesce(p_nota, 'Reservado desde el sitio web'),
          case when p_origen in ('web','bot','panel') then p_origen else 'web' end)
  returning id into v_id;

  return v_id;
end $$;

grant execute on function reservar_hora(uuid,uuid,text,text,text,date,time,time,text,text,text)
  to anon, authenticated;


-- Apagado: sin tope mientras dure el periodo de pruebas
update bot_config set local_info = local_info - 'maxPendientes' where id = 1;

notify pgrst, 'reload schema';
