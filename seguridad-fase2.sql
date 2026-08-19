-- ═══════════════════════════════════════════════════════════════
-- SPA INFINITY — Seguridad fase 2
-- Cierra el acceso público a los datos del negocio.
--
-- ANTES DE EJECUTAR:
--   1) El panel con login debe estar publicado en el servidor.
--   2) Debe existir al menos una cuenta con rol 'admin' en panel_users.
--   3) api/bot-config.php debe tener 'serviceKey' (llave service_role).
--
-- Qué queda abierto al público (sin sesión):
--   · categories, services, promo_events, page_content  → solo lectura (el sitio)
--   · surveys (activas) → lectura   ·  survey_responses → solo insertar
--   · public_pricing (vista) → solo las reglas de descuento
-- Todo lo demás exige sesión iniciada.
-- ═══════════════════════════════════════════════════════════════

-- ── 1. Funciones auxiliares ─────────────────────────────────────
create or replace function is_panel_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users where id = auth.uid() and role = 'admin' and active)
$$;

-- ficha de profesional del usuario que tiene la sesión abierta
create or replace function my_professional_id() returns uuid
language sql stable security definer set search_path = public as $$
  select professional_id from panel_users where id = auth.uid() and active
$$;

-- cualquier usuario con sesión y perfil activo
create or replace function is_panel_user() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users where id = auth.uid() and active)
$$;

-- ── 2. Vista pública con las reglas de precios ──────────────────
create or replace view public_pricing as
  select smart_pricing from bot_config where id = 1;
grant select on public_pricing to anon, authenticated;

-- ── 3. Contenido del sitio: lectura pública, escritura solo admin ──
do $$
declare t text;
begin
  foreach t in array array['categories','services','promo_events','page_content'] loop
    execute format('alter table %I enable row level security', t);
    execute format('drop policy if exists "anon all %s" on %I', t, t);
    execute format('drop policy if exists "public read %s" on %I', t, t);
    execute format('drop policy if exists "admin writes %s" on %I', t, t);
    execute format('create policy "public read %s" on %I for select using (true)', t, t);
    execute format('create policy "admin writes %s" on %I for all using (is_panel_admin()) with check (is_panel_admin())', t, t);
  end loop;
end $$;

-- ── 4. Encuestas: el cliente responde sin cuenta, pero no lee respuestas ──
alter table surveys enable row level security;
drop policy if exists "anon all surveys" on surveys;
drop policy if exists "public read surveys" on surveys;
drop policy if exists "staff writes surveys" on surveys;
create policy "public read surveys" on surveys for select using (active);
create policy "staff writes surveys" on surveys for all using (is_panel_user()) with check (is_panel_user());

alter table survey_responses enable row level security;
drop policy if exists "anon all svresp" on survey_responses;
drop policy if exists "public insert svresp" on survey_responses;
drop policy if exists "staff reads svresp" on survey_responses;
create policy "public insert svresp" on survey_responses for insert with check (true);
create policy "staff reads svresp" on survey_responses for all using (is_panel_user()) with check (is_panel_user());

-- ── 5. Datos del negocio: solo con sesión ───────────────────────
-- Tablas que ve todo el equipo (el profesional necesita leerlas para trabajar)
do $$
declare t text;
begin
  foreach t in array array['professionals','clients','products'] loop
    execute format('alter table %I enable row level security', t);
    execute format('drop policy if exists "anon all %s" on %I', t, t);
    execute format('drop policy if exists "staff reads %s" on %I', t, t);
    execute format('drop policy if exists "admin writes %s" on %I', t, t);
    execute format('create policy "staff reads %s" on %I for select using (is_panel_user())', t, t);
    execute format('create policy "admin writes %s" on %I for all using (is_panel_admin()) with check (is_panel_admin())', t, t);
  end loop;
end $$;

-- Tablas solo del administrador
do $$
declare t text;
begin
  foreach t in array array['bot_config','plans','cash_movements','gift_card_uses','stock_movements','campaigns','email_suppressions','panel_users'] loop
    execute format('alter table %I enable row level security', t);
    execute format('drop policy if exists "anon all %s" on %I', t, t);
    execute format('drop policy if exists "admin only %s" on %I', t, t);
    execute format('create policy "admin only %s" on %I for all using (is_panel_admin()) with check (is_panel_admin())', t, t);
  end loop;
end $$;

-- panel_users: además, cada uno puede leer su propio perfil (necesario para saber su rol)
drop policy if exists "own profile" on panel_users;
create policy "own profile" on panel_users for select using (auth.uid() = id);

-- ── 6. Agenda y ventas: el profesional solo ve y toca lo suyo ───
alter table appointments enable row level security;
drop policy if exists "anon all appointments" on appointments;
drop policy if exists "admin all appointments" on appointments;
drop policy if exists "pro own appointments" on appointments;
create policy "admin all appointments" on appointments for all using (is_panel_admin()) with check (is_panel_admin());
create policy "pro own appointments" on appointments for all
  using (professional_id = my_professional_id())
  with check (professional_id = my_professional_id());

alter table sales enable row level security;
drop policy if exists "anon all sales" on sales;
drop policy if exists "admin all sales" on sales;
drop policy if exists "pro own sales" on sales;
create policy "admin all sales" on sales for all using (is_panel_admin()) with check (is_panel_admin());
create policy "pro own sales" on sales for select using (professional_id = my_professional_id());

-- ── 7. Comprobación rápida ──────────────────────────────────────
-- Con la llave anónima, esto debe devolver 0 filas:
--   select * from appointments;   select * from clients;   select * from sales;
-- Y esto debe seguir funcionando:
--   select * from services;  select * from public_pricing;
