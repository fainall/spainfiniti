-- ════════════════════════════════════════════════════════════════
-- Dos fallos graves de seguridad en la base.
--
-- 1. Las tablas clinicas (fichas, consentimientos, links de pago)
--    dejaban leer y borrar a cualquier usuario "autenticado". Como el
--    registro de cuentas esta abierto (el panel lo usa para crear al
--    equipo), cualquiera podia crearse una cuenta y bajarse las fichas.
--    Ahora solo entra quien tiene perfil activo en panel_users.
--
-- 2. reservar_hora(), que crea las reservas del sitio, aceptaba
--    cualquier hora y duracion: con la llave publica se podia bloquear
--    la agenda entera. Y sin bloqueo, dos reservas simultaneas de la
--    misma hora pasaban las dos. Ahora valida horario del profesional
--    y del local, descansos, servicio y duracion, limita las reservas
--    pendientes por telefono, y toma un candado por profesional y dia.
-- ════════════════════════════════════════════════════════════════

-- ── 1. quien es del panel ───────────────────────────────────────
create or replace function is_panel_user() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users where id = auth.uid() and active)
$$;

do $$
declare t text;
begin
  foreach t in array array['record_templates','client_records','consents','client_consents','payment_links'] loop
    execute format('drop policy if exists "panel_lee_%s" on %I', t, t);
    execute format('drop policy if exists "panel_escribe_%s" on %I', t, t);
    execute format('create policy "panel_lee_%s" on %I for select to authenticated using (is_panel_user())', t, t);
    execute format('create policy "panel_escribe_%s" on %I for all to authenticated using (is_panel_user()) with check (is_panel_user())', t, t);
  end loop;
end $$;

-- subir archivos a las carpetas clinicas: solo el equipo
insert into storage.buckets (id, name, public) values ('fichas','fichas', true) on conflict (id) do nothing;
drop policy if exists "consent_sube" on storage.objects;
create policy "consent_sube" on storage.objects for insert to authenticated
  with check (bucket_id = 'consentimientos' and is_panel_user());
drop policy if exists "fichas_lee" on storage.objects;
drop policy if exists "fichas_sube" on storage.objects;
create policy "fichas_lee"  on storage.objects for select using (bucket_id = 'fichas');
create policy "fichas_sube" on storage.objects for insert to authenticated
  with check (bucket_id = 'fichas' and is_panel_user());

-- ── 2. la reserva del sitio, con todas sus comprobaciones ──────
-- "60 min", "1 hr 30 min", "1hr30min", "2 Hrs" -> minutos
create or replace function duracion_min(t text) returns int
language sql immutable as $$
  select case
    when t is null then null
    when t ~* 'h' then coalesce((regexp_match(t, '(\d+)\s*h', 'i'))[1]::int, 0) * 60
                     + coalesce((regexp_match(t, '(\d+)\s*min', 'i'))[1]::int, 0)
    when t ~ '\d' then (regexp_match(t, '(\d+)'))[1]::int
    else null end
$$;

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

  /* freno al abuso: pocas reservas pendientes por telefono desde fuera */
  v_fono := regexp_replace(coalesce(p_fono,''), '\D', '', 'g');
  if p_origen <> 'panel' and length(v_fono) >= 8 then
    select count(*) into v_n from appointments
      where regexp_replace(coalesce(client_phone,''), '\D', '', 'g') like '%' || right(v_fono, 8)
        and appt_date >= v_hoy
        and status is distinct from 'cancelled'
        and origen in ('web','bot');
    if v_n >= 3 then
      raise exception 'Ya tienes 3 reservas pendientes. Escribenos por WhatsApp para agendar otra.';
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
