-- ═══════════════════════════════════════════════════════════════
--  RECURSOS · vista pública
--  El sitio (reservar.html) necesita saber qué boxes, camillas o
--  equipos hace falta para cada servicio, para no ofrecer una hora
--  cuyo recurso ya está ocupado.
--
--  public_pricing es la vista que lee el sitio con la clave anónima.
--  Aquí se le añaden las columnas que faltaban: los recursos, las
--  reglas de cada servicio y el horario del local. Ninguna de ellas
--  contiene datos personales; los correos y teléfonos de los
--  profesionales siguen fuera (solo se expone qué servicios hace
--  cada uno, que es lo que el sitio necesita para ofrecer horas).
--
--  Se ejecuta en Supabase → SQL Editor. Es seguro repetirla.
-- ═══════════════════════════════════════════════════════════════

drop view if exists public_pricing;

create view public_pricing as
select
  smart_pricing,
  svc_classes,
  svc_packages,
  svc_meta,
  local_info,
  resources,
  (
    select coalesce(jsonb_object_agg(k, coalesce(v -> 'services', '"all"'::jsonb)), '{}'::jsonb)
    from jsonb_each(coalesce(prof_meta::jsonb, '{}'::jsonb)) as t(k, v)
  ) as prof_services
from bot_config
where id = 1;

grant select on public_pricing to anon, authenticated;

-- Comprobación:
--   select * from public_pricing;
