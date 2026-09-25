-- ════════════════════════════════════════════════════════════════
-- Cuentas de clientes · 03 · Vincular reservas e historial
--
-- Las reservas ya estaban ligadas a una ficha (clients). Ahora la ficha
-- puede quedar ligada a una cuenta (clients.user_id) y cada reserva lleva
-- también la cuenta (appointments.user_id), que se completa sola desde
-- su ficha. Nada de lo existente cambia: las columnas nuevas parten vacías.
--
-- Regla de vinculación (aprobada por Camilo):
--   · solo con el correo VERIFICADO de la cuenta, nunca solo por teléfono
--     (un teléfono no se puede comprobar);
--   · si el correo aparece en fichas de una sola persona, se ligan todas
--     (son la misma persona duplicada);
--   · si aparece en fichas de personas distintas (familia que comparte
--     correo), se ligan solo las que coinciden con el nombre o el teléfono
--     del perfil, y el resto queda para que el equipo lo revise.
-- ════════════════════════════════════════════════════════════════

alter table public.clients      add column if not exists user_id uuid references auth.users(id) on delete set null;
alter table public.appointments add column if not exists user_id uuid references auth.users(id) on delete set null;
create index if not exists clients_user_idx      on public.clients (user_id);
create index if not exists appointments_user_idx on public.appointments (user_id);
create index if not exists clients_email_norm_idx on public.clients (lower(trim(email)));

-- fichas que el sistema no se atrevió a ligar solas (las revisa el equipo)
create table if not exists public.vinculos_pendientes (
  user_id    uuid not null references auth.users(id) on delete cascade,
  client_id  uuid not null references public.clients(id) on delete cascade,
  motivo     text not null,
  created_at timestamptz not null default now(),
  primary key (user_id, client_id)
);
alter table public.vinculos_pendientes enable row level security;
drop policy if exists vinculos_equipo on public.vinculos_pendientes;
create policy vinculos_equipo on public.vinculos_pendientes for all to authenticated
  using (public.is_panel_datos()) with check (public.is_panel_datos());
revoke all on public.vinculos_pendientes from anon;
grant select, insert, update, delete on public.vinculos_pendientes to authenticated;

-- ── la reserva hereda la cuenta de su ficha ──
create or replace function public.reserva_toma_cuenta()
returns trigger language plpgsql security definer set search_path = '' as $$
begin
  if new.client_id is null then
    new.user_id := null;
  elsif tg_op = 'INSERT' or new.client_id is distinct from old.client_id then
    new.user_id := (select c.user_id from public.clients c where c.id = new.client_id);
  end if;
  return new;
end $$;
drop trigger if exists reserva_toma_cuenta on public.appointments;
create trigger reserva_toma_cuenta before insert or update of client_id on public.appointments
  for each row execute function public.reserva_toma_cuenta();

-- ── y si la ficha cambia de cuenta, sus reservas también ──
create or replace function public.ficha_pasa_cuenta()
returns trigger language plpgsql security definer set search_path = '' as $$
begin
  update public.appointments set user_id = new.user_id
   where client_id = new.id and user_id is distinct from new.user_id;
  return new;
end $$;
drop trigger if exists ficha_pasa_cuenta on public.clients;
create trigger ficha_pasa_cuenta after update of user_id on public.clients
  for each row when (old.user_id is distinct from new.user_id)
  execute function public.ficha_pasa_cuenta();

-- ── comparar nombres sin tildes, mayúsculas ni espacios de más ──
create or replace function public.nombre_normal(t text)
returns text language sql immutable set search_path = '' as $$
  select trim(regexp_replace(translate(lower(coalesce(t, '')), 'áéíóúüñàèìòù', 'aeiouunaeiou'), '\s+', ' ', 'g'))
$$;

-- ── la vinculación ──
create or replace function public.vincular_fichas(p_user uuid)
returns int language plpgsql security definer set search_path = '' as $$
declare
  v_correo text; v_nombre text; v_apellido text; v_fono text;
  v_personas int; v_ligadas int := 0; f record; v_coincide boolean;
begin
  select lower(trim(u.email)) into v_correo
    from auth.users u where u.id = p_user and u.email_confirmed_at is not null;
  if v_correo is null or v_correo = '' then return 0; end if;   -- correo sin verificar: nada

  select public.nombre_normal(p.nombre), public.nombre_normal(p.apellido),
         right(regexp_replace(coalesce(p.telefono, ''), '\D', '', 'g'), 8)
    into v_nombre, v_apellido, v_fono
    from public.profiles p where p.id = p_user;

  -- ¿cuántas personas distintas usan este correo en fichas aún sin cuenta?
  select count(distinct public.nombre_normal(c.name)) into v_personas
    from public.clients c
   where lower(trim(c.email)) = v_correo and c.user_id is null;
  if v_personas = 0 then return 0; end if;

  for f in select c.id, c.name, c.phone from public.clients c
            where lower(trim(c.email)) = v_correo and c.user_id is null loop
    if v_personas = 1 then
      v_coincide := true;
    else
      -- varias personas: el nombre y el apellido del perfil están en la ficha,
      -- o el teléfono de la ficha es el del perfil
      v_coincide :=
        (coalesce(v_nombre, '') <> '' and coalesce(v_apellido, '') <> ''
          and (' ' || public.nombre_normal(f.name) || ' ') like ('% ' || split_part(v_nombre, ' ', 1) || ' %')
          and (' ' || public.nombre_normal(f.name) || ' ') like ('% ' || split_part(v_apellido, ' ', 1) || ' %'))
        or (length(v_fono) = 8 and right(regexp_replace(coalesce(f.phone, ''), '\D', '', 'g'), 8) = v_fono);
    end if;

    if v_coincide then
      update public.clients set user_id = p_user where id = f.id;
      delete from public.vinculos_pendientes where client_id = f.id;
      v_ligadas := v_ligadas + 1;
    else
      insert into public.vinculos_pendientes (user_id, client_id, motivo)
      values (p_user, f.id, 'Correo compartido por varias personas; el nombre y el teléfono no coinciden con la cuenta')
      on conflict do nothing;
    end if;
  end loop;
  return v_ligadas;
end $$;
revoke all on function public.vincular_fichas(uuid) from public, anon, authenticated;

-- el cliente puede pedir que se revise de nuevo (p. ej. si el spa le agregó
-- el correo a su ficha después); solo para su propia cuenta
create or replace function public.vincular_mis_fichas()
returns int language sql security definer set search_path = '' as $$
  select public.vincular_fichas(auth.uid())
$$;
revoke all on function public.vincular_mis_fichas() from public, anon;
grant execute on function public.vincular_mis_fichas() to authenticated;

-- al confirmar el correo (o si la cuenta nace ya confirmada)
create or replace function public.vincular_al_confirmar()
returns trigger language plpgsql security definer set search_path = '' as $$
begin
  if new.email_confirmed_at is not null
     and (tg_op = 'INSERT' or old.email_confirmed_at is null) then
    perform public.vincular_fichas(new.id);
  end if;
  return new;
end $$;
drop trigger if exists vincular_al_confirmar on auth.users;
create trigger vincular_al_confirmar after insert or update of email_confirmed_at on auth.users
  for each row execute function public.vincular_al_confirmar();

-- ── historial del cliente ──
-- Solo estas columnas y solo sus reservas: las notas internas del equipo,
-- el teléfono y los datos de otras personas nunca salen de la base.
create or replace function public.mis_citas()
returns table (id uuid, fecha date, inicio time, fin time, servicio text,
               profesional text, estado text, precio text, pasada boolean)
language sql stable security definer set search_path = '' as $$
  select a.id, a.appt_date, a.start_time, a.end_time, a.service_name,
         coalesce(nullif(pm.nombre_publico, ''), p.name), coalesce(a.status, 'reserved'), a.price,
         (a.appt_date + a.end_time) < (now() at time zone 'America/Santiago')
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
