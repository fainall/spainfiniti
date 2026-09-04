-- ════════════════════════════════════════════════════════════════
-- Registro de cambios del catalogo del sitio.
--
-- Tres servicios perdieron su imagen y no habia forma de saber quien
-- ni cuando. A partir de ahora, cada alta, cambio o borrado en
-- servicios y categorias deja una fila: quien lo hizo, cuando, y que
-- campos cambiaron con su valor anterior y nuevo. Se consulta desde
-- Administracion -> Servicios -> Historial de cambios.
-- ════════════════════════════════════════════════════════════════

create table if not exists registro_cambios (
  id          bigserial primary key,
  cuando      timestamptz not null default now(),
  tabla       text not null,
  fila_id     text not null,
  nombre      text,                       -- nombre del servicio o categoria, para leerlo sin buscar
  accion      text not null,              -- alta / cambio / borrado
  quien_id    uuid,
  quien       text,                       -- correo o nombre de quien lo hizo
  cambios     jsonb                       -- { campo: { antes, despues } }
);
create index if not exists registro_cambios_cuando_idx on registro_cambios (cuando desc);
create index if not exists registro_cambios_fila_idx   on registro_cambios (tabla, fila_id);

-- quien esta escribiendo: el correo de su perfil del panel, o el del token
create or replace function quien_soy() returns text
language sql stable security definer set search_path = public as $$
  select coalesce(
    (select coalesce(nullif(name,''), email) from panel_users where id = auth.uid()),
    nullif(auth.jwt() ->> 'email', ''),
    case when auth.role() = 'anon' then 'sitio web (sin sesion)' else auth.role() end)
$$;

create or replace function fn_registrar_cambio() returns trigger
language plpgsql security definer set search_path = public as $$
declare
  v_antes   jsonb := case when tg_op = 'INSERT' then null else to_jsonb(old) end;
  v_despues jsonb := case when tg_op = 'DELETE' then null else to_jsonb(new) end;
  v_cambios jsonb := '{}'::jsonb;
  k text;
begin
  if tg_op = 'UPDATE' then
    /* solo lo que cambio de verdad, con antes y despues */
    for k in select jsonb_object_keys(v_despues) loop
      if k in ('updated_at') then continue; end if;
      if (v_antes -> k) is distinct from (v_despues -> k) then
        v_cambios := v_cambios || jsonb_build_object(k, jsonb_build_object('antes', v_antes -> k, 'despues', v_despues -> k));
      end if;
    end loop;
    if v_cambios = '{}'::jsonb then return new; end if;   -- guardado sin cambios: no ensucia
  elsif tg_op = 'INSERT' then
    v_cambios := v_despues;
  else
    v_cambios := v_antes;
  end if;

  insert into registro_cambios (tabla, fila_id, nombre, accion, quien_id, quien, cambios)
  values (
    tg_table_name,
    coalesce(v_despues ->> 'id', v_antes ->> 'id'),
    coalesce(v_despues ->> 'name', v_antes ->> 'name'),
    case tg_op when 'INSERT' then 'alta' when 'UPDATE' then 'cambio' else 'borrado' end,
    auth.uid(),
    quien_soy(),
    v_cambios
  );
  return coalesce(new, old);
end $$;

drop trigger if exists trg_registro_services on services;
create trigger trg_registro_services
  after insert or update or delete on services
  for each row execute function fn_registrar_cambio();

drop trigger if exists trg_registro_categories on categories;
create trigger trg_registro_categories
  after insert or update or delete on categories
  for each row execute function fn_registrar_cambio();

-- lo lee el equipo del panel; nadie lo escribe desde fuera (solo el trigger)
alter table registro_cambios enable row level security;
drop policy if exists "registro_lee" on registro_cambios;
create policy "registro_lee" on registro_cambios for select to authenticated using (is_panel_user());

-- Refresca la cache de la API para que la tabla nueva sea visible al instante
notify pgrst, 'reload schema';

-- OJO: no anadir aqui una prueba con begin/rollback. El editor de Supabase
-- ejecuta el script entero como una sola transaccion, y el rollback
-- deshace tambien las tablas y los triggers creados arriba.
