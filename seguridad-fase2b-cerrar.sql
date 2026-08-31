-- ═══════════════════════════════════════════════════════════════
--  SEGURIDAD FASE 2-B · CERRAR
--  Deja de exponer los datos del negocio a la llave pública.
--
--  ⚠ ANTES DE EJECUTAR, LOS DOS SON OBLIGATORIOS:
--    1) Existe al menos una cuenta con rol 'admin' en panel_users.
--       Sin ella, nadie podrá volver a entrar al panel.
--    2) api/bot-config.php tiene 'serviceKey' (la llave service_role).
--       Sin ella se caen: el bot de WhatsApp, los recordatorios
--       automáticos, las campañas de correo y las bajas de email.
--
--  Requiere haber ejecutado antes seguridad-fase2a-preparar.sql.
--
--  Qué sigue abierto sin sesión (lo que el sitio necesita):
--    · categories, services, promo_events, page_content → solo lectura
--    · surveys activas → lectura   ·  survey_responses → solo insertar
--    · public_pricing, public_professionals, public_busy → solo lectura
--    · reservar_cliente() y reservar_hora() → crear una reserva
--  Todo lo demás exige sesión iniciada.
--
--  Para deshacerlo: seguridad-fase2-revertir.sql
-- ═══════════════════════════════════════════════════════════════

-- ── 1. Quién es quién ───────────────────────────────────────────
create or replace function is_panel_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users
                  where id = auth.uid() and role in ('admin','admin_local') and active)
$$;

create or replace function is_panel_user() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users where id = auth.uid() and active)
$$;

create or replace function my_professional_id() returns uuid
language sql stable security definer set search_path = public as $$
  select professional_id from panel_users where id = auth.uid() and active
$$;

-- ── 2. Contenido del sitio: lo lee cualquiera, lo edita el admin ──
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

-- ── 3. Encuestas: el cliente responde sin cuenta, pero no lee nada ──
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

-- ── 4. Lo que ve todo el equipo ─────────────────────────────────
-- El sitio ya no lee estas tablas: usa las vistas y las funciones.
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

-- ── 5. Solo el administrador ────────────────────────────────────
do $$
declare t text;
begin
  foreach t in array array['bot_config','plans','cash_movements','gift_card_uses',
                           'stock_movements','campaigns','email_suppressions','panel_users'] loop
    execute format('alter table %I enable row level security', t);
    execute format('drop policy if exists "anon all %s" on %I', t, t);
    execute format('drop policy if exists "admin only %s" on %I', t, t);
    execute format('create policy "admin only %s" on %I for all using (is_panel_admin()) with check (is_panel_admin())', t, t);
  end loop;
end $$;

-- cada uno puede leer su propio perfil: si no, no sabría ni su rol
drop policy if exists "own profile" on panel_users;
create policy "own profile" on panel_users for select using (auth.uid() = id);

-- ── 6. Agenda y ventas: el profesional solo lo suyo ─────────────
alter table appointments enable row level security;
drop policy if exists "anon all appointments" on appointments;
drop policy if exists "admin all appointments" on appointments;
drop policy if exists "pro own appointments" on appointments;
create policy "admin all appointments" on appointments for all
  using (is_panel_admin()) with check (is_panel_admin());
create policy "pro own appointments" on appointments for all
  using (professional_id = my_professional_id())
  with check (professional_id = my_professional_id());

alter table sales enable row level security;
drop policy if exists "anon all sales" on sales;
drop policy if exists "admin all sales" on sales;
drop policy if exists "pro own sales" on sales;
create policy "admin all sales" on sales for all
  using (is_panel_admin()) with check (is_panel_admin());
create policy "pro own sales" on sales for select
  using (professional_id = my_professional_id());

-- ── 7. Gift cards: se compran sin cuenta ────────────────────────
-- La compra la crea el servidor (api/), que va con la llave de servicio
-- y se salta estas reglas. Desde el navegador ya no se leen.
alter table gift_cards enable row level security;
drop policy if exists "anon all gift_cards" on gift_cards;
drop policy if exists "staff gift_cards" on gift_cards;
create policy "staff gift_cards" on gift_cards for all
  using (is_panel_user()) with check (is_panel_user());

-- ── 8. Comprobación ─────────────────────────────────────────────
-- Con la llave anónima, estas deben devolver 0 filas o error:
--   select * from clients;   select * from appointments;   select * from sales;
-- Y estas deben seguir funcionando:
--   select * from services;              select * from public_pricing;
--   select * from public_professionals;  select * from public_busy;
