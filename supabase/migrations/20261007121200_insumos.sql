-- ════════════════════════════════════════════════════════════════
-- Insumos usados en cada sesión (pedido de Luis, 07-oct-2026)
--
-- · products.uso: 'venta' (se vende), 'insumo' (uso interno: bisturí, gasa,
--   guantes…) o 'ambos'. Los insumos no aparecen al vender.
-- · servicio_insumos: el kit de cada servicio (lo que se usa por defecto).
--   Lo define administración.
-- · consumos: lo que se usó en cada sesión (cita), con su cliente,
--   profesional y costo. Se registra a mano en cada sesión (viene con el kit
--   del servicio ya cargado) por la profesional de esa cita, recepción o
--   administración, con registrar_insumos(), que deja la lista de la sesión
--   y descuenta del stock la diferencia en un solo paso.
-- ════════════════════════════════════════════════════════════════

alter table public.products add column if not exists uso text not null default 'venta';
alter table public.products drop constraint if exists products_uso_check;
alter table public.products add constraint products_uso_check check (uso in ('venta', 'insumo', 'ambos'));
alter table public.products add column if not exists unidad text;

-- ── el kit de cada servicio ──
create table if not exists public.servicio_insumos (
  id          uuid primary key default gen_random_uuid(),
  service_id  text not null,
  product_id  uuid not null references public.products(id) on delete cascade,
  qty         numeric not null default 1 check (qty > 0),
  created_at  timestamptz default now(),
  unique (service_id, product_id)
);
alter table public.servicio_insumos enable row level security;
drop policy if exists kit_lee on public.servicio_insumos;
create policy kit_lee on public.servicio_insumos for select to authenticated using (public.is_panel_datos());
drop policy if exists kit_admin on public.servicio_insumos;
create policy kit_admin on public.servicio_insumos for all to authenticated using (public.is_admin()) with check (public.is_admin());
revoke all on public.servicio_insumos from anon;

-- ── lo usado en cada sesión ──
create table if not exists public.consumos (
  id              uuid primary key default gen_random_uuid(),
  appointment_id  uuid references public.appointments(id) on delete set null,
  client_id       uuid references public.clients(id) on delete set null,
  professional_id uuid,
  service_name    text,
  product_id      uuid references public.products(id) on delete set null,
  product_name    text,
  qty             numeric not null check (qty > 0),
  costo_unit      numeric default 0,
  fecha           date not null default ((now() at time zone 'America/Santiago')::date),
  creado_por      uuid default auth.uid(),
  created_at      timestamptz default now()
);
create index if not exists consumos_cita_idx on public.consumos (appointment_id);
create index if not exists consumos_cliente_idx on public.consumos (client_id);
create index if not exists consumos_fecha_idx on public.consumos (fecha);
alter table public.consumos enable row level security;
drop policy if exists consumos_lee on public.consumos;
create policy consumos_lee on public.consumos for select to authenticated using (public.is_panel_datos());
-- se escribe solo con registrar_insumos() (que además descuenta el stock)
revoke all on public.consumos from anon;
revoke insert, update, delete on public.consumos from authenticated;

/* Deja la lista de insumos de una sesión y ajusta el stock por la diferencia.
   p_items: [{"product_id": "...", "qty": 2}, ...] (lista completa de la sesión;
   una lista vacía borra lo registrado y devuelve el stock).
   Pueden usarla administración, recepción y la profesional de esa cita;
   no los perfiles de solo lectura ni publicidad. */
create or replace function public.registrar_insumos(p_cita uuid, p_items jsonb)
returns jsonb language plpgsql security definer set search_path = '' as $$
declare
  yo public.panel_users; a public.appointments; it jsonb; v_prod public.products;
  v_nuevo jsonb := '{}'::jsonb; v_antes jsonb := '{}'::jsonb; v_id text; v_dif numeric; v_n int := 0;
  v_detalle text;
begin
  select * into yo from public.panel_users where id = auth.uid() and coalesce(active, true);
  if not found or yo.role in ('publicidad', 'recepcion_ro', 'pro_ro') then
    raise exception 'Tu perfil no puede registrar insumos';
  end if;
  select * into a from public.appointments where id = p_cita for update;
  if not found then raise exception 'No encontramos esa cita'; end if;
  if yo.role = 'pro' and a.professional_id is distinct from yo.professional_id then
    raise exception 'Solo puedes registrar los insumos de tus propias sesiones';
  end if;
  if jsonb_typeof(coalesce(p_items, '[]'::jsonb)) <> 'array' then raise exception 'Lista de insumos no válida'; end if;

  -- lo pedido ahora, sumado por producto
  for it in select * from jsonb_array_elements(coalesce(p_items, '[]'::jsonb)) loop
    if coalesce((it->>'qty')::numeric, 0) <= 0 then continue; end if;
    -- el stock se lleva en unidades enteras
    if (it->>'qty')::numeric <> trunc((it->>'qty')::numeric) then raise exception 'Las cantidades van en unidades enteras'; end if;
    v_id := it->>'product_id';
    v_nuevo := jsonb_set(v_nuevo, array[v_id], to_jsonb(coalesce((v_nuevo->>v_id)::numeric, 0) + (it->>'qty')::numeric));
  end loop;
  -- lo que ya estaba registrado en esta sesión
  select coalesce(jsonb_object_agg(product_id::text, total), '{}'::jsonb) into v_antes
    from (select product_id, sum(qty) total from public.consumos where appointment_id = p_cita and product_id is not null group by product_id) x;

  v_detalle := 'Sesión de ' || coalesce(nullif(a.client_name, ''), 'cliente') || coalesce(' · ' || nullif(a.service_name, ''), '')
               || ' · ' || to_char(a.appt_date, 'DD/MM');

  -- el stock se mueve solo por la diferencia, producto por producto
  for v_id in select jsonb_object_keys(v_nuevo) union select jsonb_object_keys(v_antes) loop
    v_dif := coalesce((v_nuevo->>v_id)::numeric, 0) - coalesce((v_antes->>v_id)::numeric, 0);
    if v_dif = 0 then continue; end if;
    update public.products set stock = coalesce(stock, 0) - v_dif where id = v_id::uuid returning * into v_prod;
    if not found then raise exception 'Uno de los productos ya no existe'; end if;
    insert into public.stock_movements (product_id, product_name, type, qty, note, move_date)
    values (v_prod.id, v_prod.name, 'consumo', -v_dif,
            v_detalle || case when v_dif < 0 then ' (corrección)' else '' end,
            (now() at time zone 'America/Santiago')::date);
  end loop;

  -- la lista de la sesión queda tal como se pidió
  delete from public.consumos where appointment_id = p_cita;
  for v_id in select jsonb_object_keys(v_nuevo) loop
    select * into v_prod from public.products where id = v_id::uuid;
    insert into public.consumos (appointment_id, client_id, professional_id, service_name, product_id, product_name, qty, costo_unit, fecha)
    values (a.id, a.client_id, a.professional_id, a.service_name, v_prod.id, v_prod.name, (v_nuevo->>v_id)::numeric,
            coalesce(v_prod.cost, 0), a.appt_date);
    v_n := v_n + 1;
  end loop;

  return jsonb_build_object('ok', true, 'productos', v_n);
end $$;
revoke all on function public.registrar_insumos(uuid, jsonb) from public, anon;
grant execute on function public.registrar_insumos(uuid, jsonb) to authenticated;

notify pgrst, 'reload schema';
