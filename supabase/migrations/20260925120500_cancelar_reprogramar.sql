-- ════════════════════════════════════════════════════════════════
-- Cuentas de clientes · 05 · Cancelar y cambiar horas desde la cuenta
--
-- El cliente puede cancelar o cambiar SUS horas hasta cierto tiempo antes
-- (bot_config.local_info.cancelHoras, 24 por omisión; se configura en el
-- panel). Pasado ese plazo, tiene que escribir al spa.
--
-- Cambiar = reservar la hora nueva con reservar_hora() (que valida todo:
-- horario, profesional, que esté libre) y cancelar la anterior, en una
-- sola transacción: si la nueva no se puede, la anterior queda intacta.
--
-- Y para el panel: el listado de cuentas de clientes y la revisión de las
-- fichas que la vinculación dejó pendientes.
-- ════════════════════════════════════════════════════════════════

-- horas mínimas de anticipación para cancelar o cambiar
create or replace function public.horas_para_cambiar()
returns int language sql stable security definer set search_path = '' as $$
  -- ojo: greatest() ignora los nulos, así que el valor por omisión va antes
  select greatest(coalesce((select nullif(b.local_info->>'cancelHoras', '')::int
                              from public.bot_config b where b.id = 1), 24), 0)
$$;
grant execute on function public.horas_para_cambiar() to anon, authenticated;

-- ¿esta reserva (de la cuenta) todavía se puede cancelar o cambiar?
create or replace function public.cita_cambiable(a public.appointments)
returns boolean language sql stable security definer set search_path = '' as $$
  select a.status is distinct from 'cancelled'
     and coalesce(a.status, 'reserved') in ('reserved', 'confirmed', 'pending', 'waiting', 'active')
     and ((a.appt_date + a.start_time) at time zone 'America/Santiago')
         > now() + make_interval(hours => public.horas_para_cambiar())
$$;
revoke all on function public.cita_cambiable(public.appointments) from public, anon, authenticated;

-- el historial suma: si se puede cambiar, hasta cuándo, y el id del servicio
drop function if exists public.mis_citas();
create function public.mis_citas()
returns table (id uuid, fecha date, inicio time, fin time, servicio text, servicio_id text,
               profesional text, estado text, precio text, pasada boolean,
               cambiable boolean, limite_cambio timestamptz)
language sql stable security definer set search_path = '' as $$
  select a.id, a.appt_date, a.start_time, a.end_time, a.service_name,
         (select s.id from public.services s where lower(s.name) = lower(a.service_name) limit 1),
         coalesce(nullif(pm.nombre_publico, ''), p.name), coalesce(a.status, 'reserved'), a.price,
         (a.appt_date + a.end_time) < (now() at time zone 'America/Santiago'),
         public.cita_cambiable(a),
         ((a.appt_date + a.start_time) at time zone 'America/Santiago') - make_interval(hours => public.horas_para_cambiar())
    from public.appointments a
    left join public.professionals p on p.id = a.professional_id
    left join lateral (select b.prof_meta -> a.professional_id::text ->> 'publicName' as nombre_publico
                         from public.bot_config b where b.id = 1) pm on true
   where auth.uid() is not null
     and a.user_id = auth.uid()
     and a.status is distinct from 'block'
   order by a.appt_date desc, a.start_time desc
$$;
revoke all on function public.mis_citas() from public, anon;
grant execute on function public.mis_citas() to authenticated;

-- la reserva de la cuenta, bloqueada para que nadie la cambie a la vez
create or replace function public.mi_cita_para_cambiar(p_id uuid)
returns public.appointments language plpgsql security definer set search_path = '' as $$
declare a public.appointments;
begin
  if auth.uid() is null then raise exception 'Necesitas ingresar a tu cuenta'; end if;
  select * into a from public.appointments where id = p_id and user_id = auth.uid() for update;
  if not found then raise exception 'No encontramos esa hora en tu cuenta'; end if;
  if a.status = 'cancelled' then raise exception 'Esa hora ya estaba cancelada'; end if;
  if not public.cita_cambiable(a) then
    raise exception 'Faltan menos de % horas para tu cita: para cancelarla o cambiarla escríbenos por WhatsApp', public.horas_para_cambiar();
  end if;
  return a;
end $$;
revoke all on function public.mi_cita_para_cambiar(uuid) from public, anon, authenticated;

create or replace function public.cancelar_mi_cita(p_id uuid)
returns uuid language plpgsql security definer set search_path = '' as $$
declare a public.appointments;
begin
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
  update public.appointments
     set notes = concat_ws(' · ', nullif(notes, ''), 'Cambiada por el cliente a '
                 || to_char(p_fecha, 'DD/MM') || ' ' || to_char(p_inicio, 'HH24:MI'))
   where id = a.id;
  return v_nueva;
end $$;
revoke all on function public.reprogramar_mi_cita(uuid, uuid, date, time, time, text) from public, anon;
grant execute on function public.reprogramar_mi_cita(uuid, uuid, date, time, time, text) to authenticated;

-- ── Panel: cuentas de clientes ──
create or replace function public.cuentas_clientes()
returns table (id uuid, correo text, confirmado boolean, creada timestamptz, ultimo_ingreso timestamptz,
               nombre text, apellido text, telefono text, rut text,
               fichas jsonb, proximas int, pasadas int, ultima_cita date)
language plpgsql stable security definer set search_path = '' as $$
begin
  if not public.is_panel_datos() then raise exception 'Solo el equipo del spa'; end if;
  return query
  select u.id, u.email::text, u.email_confirmed_at is not null, u.created_at, u.last_sign_in_at,
         p.nombre, p.apellido, p.telefono, p.rut,
         coalesce((select jsonb_agg(jsonb_build_object('id', c.id, 'name', c.name) order by c.created_at)
                     from public.clients c where c.user_id = u.id), '[]'::jsonb),
         (select count(*)::int from public.appointments a where a.user_id = u.id and a.status is distinct from 'cancelled'
             and a.appt_date >= (now() at time zone 'America/Santiago')::date),
         (select count(*)::int from public.appointments a where a.user_id = u.id and a.status is distinct from 'cancelled'
             and a.appt_date < (now() at time zone 'America/Santiago')::date),
         (select max(a.appt_date) from public.appointments a where a.user_id = u.id and a.status is distinct from 'cancelled')
    from auth.users u
    join public.profiles p on p.id = u.id
   where not exists (select 1 from public.panel_users pu where pu.id = u.id)   -- solo clientes, no el equipo
   order by u.created_at desc;
end $$;
revoke all on function public.cuentas_clientes() from public, anon;
grant execute on function public.cuentas_clientes() to authenticated;

-- las fichas pendientes de revisar, con los datos para decidir
create or replace function public.vinculos_por_revisar()
returns table (user_id uuid, correo text, cuenta_nombre text, cuenta_telefono text,
               client_id uuid, ficha_nombre text, ficha_telefono text, motivo text, creado timestamptz)
language plpgsql stable security definer set search_path = '' as $$
begin
  if not public.is_panel_datos() then raise exception 'Solo el equipo del spa'; end if;
  return query
  select v.user_id, u.email::text, trim(p.nombre || ' ' || p.apellido), p.telefono,
         v.client_id, c.name, c.phone, v.motivo, v.created_at
    from public.vinculos_pendientes v
    join auth.users u on u.id = v.user_id
    left join public.profiles p on p.id = v.user_id
    join public.clients c on c.id = v.client_id
   where c.user_id is null
   order by v.created_at;
end $$;
revoke all on function public.vinculos_por_revisar() from public, anon;
grant execute on function public.vinculos_por_revisar() to authenticated;

-- el equipo decide: ligar la ficha a la cuenta, o descartar
create or replace function public.resolver_vinculo(p_user uuid, p_client uuid, p_ligar boolean)
returns void language plpgsql security definer set search_path = '' as $$
begin
  if not public.is_panel_datos() then raise exception 'Solo el equipo del spa'; end if;
  if p_ligar then
    update public.clients set user_id = p_user where id = p_client and user_id is null;
  end if;
  delete from public.vinculos_pendientes where user_id = p_user and client_id = p_client;
end $$;
revoke all on function public.resolver_vinculo(uuid, uuid, boolean) from public, anon;
grant execute on function public.resolver_vinculo(uuid, uuid, boolean) to authenticated;

-- desligar una ficha de una cuenta (si se ligó por error)
create or replace function public.desligar_ficha(p_client uuid)
returns void language plpgsql security definer set search_path = '' as $$
begin
  if not public.is_panel_datos() then raise exception 'Solo el equipo del spa'; end if;
  update public.clients set user_id = null where id = p_client;
end $$;
revoke all on function public.desligar_ficha(uuid) from public, anon;
grant execute on function public.desligar_ficha(uuid) to authenticated;
