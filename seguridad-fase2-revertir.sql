-- ═══════════════════════════════════════════════════════════════
-- SPA INFINITY — REVERSA de la seguridad fase 2
-- Ejecuta esto SOLO si algo se rompió y necesitas volver atrás de inmediato.
-- Devuelve el acceso abierto que había antes (la llave pública puede todo).
-- ═══════════════════════════════════════════════════════════════

do $$
declare t text;
begin
  foreach t in array array[
    'categories','services','promo_events','page_content',
    'surveys','survey_responses',
    'professionals','clients','products','plans',
    'bot_config','cash_movements','gift_card_uses','stock_movements',
    'campaigns','email_suppressions','appointments','sales','gift_cards'
  ] loop
    -- quitar todas las políticas que existan en la tabla
    execute (
      select coalesce(string_agg(format('drop policy if exists %I on %I;', policyname, t), ' '), '')
      from pg_policies where schemaname = 'public' and tablename = t
    );
    -- volver a la política abierta
    execute format('create policy "anon all %s" on %I for all using(true) with check(true)', t, t);
  end loop;
end $$;

-- panel_users conserva su regla propia
drop policy if exists "anon all panel_users" on panel_users;
create policy "own profile" on panel_users for select using (auth.uid() = id);
create policy "admin reads all" on panel_users for select using (is_panel_admin());
create policy "admin writes all" on panel_users for all using (is_panel_admin()) with check (is_panel_admin());
