-- ════════════════════════════════════════════════════════════════
-- URGENTE · vistas de solo lectura (inspección del 29-sep-2026)
--
-- El respaldo de producción daba todos los permisos sobre tablas y vistas
-- a anon y authenticated. En las tablas eso lo frena RLS, pero las vistas
-- escriben en su tabla como su dueño, saltándose RLS: se comprobó que un
-- visitante sin sesión podía hacer UPDATE/DELETE sobre public_busy
-- (reservas), public_professionals y public_pricing (configuración del
-- local). Nadie escribe a través de vistas (el sitio, el panel y el
-- servidor solo las leen), así que se les deja solo SELECT.
-- ════════════════════════════════════════════════════════════════

-- todas las vistas del esquema public: fuera insertar, cambiar y borrar
do $$
declare v record;
begin
  for v in select table_name from information_schema.views where table_schema = 'public' loop
    execute format('revoke insert, update, delete, truncate, references, trigger on public.%I from public, anon, authenticated', v.table_name);
  end loop;
end $$;

-- la lectura que sí se usa
grant select on public.public_busy, public.public_professionals, public.public_pricing to anon, authenticated;
grant select on public.agenda_publicidad, public.profesionales_publicidad, public.ventas_publicidad to authenticated;

-- lo que se cree de aquí en adelante no nace con permisos de escritura para visitantes
alter default privileges in schema public revoke insert, update, delete, truncate on tables from anon, authenticated;

-- que la API lo tome de inmediato
notify pgrst, 'reload schema';
