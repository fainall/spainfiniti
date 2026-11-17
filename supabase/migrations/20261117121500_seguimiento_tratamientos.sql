-- Seguimiento de tratamientos por sesiones (pedido de Luis, 17-nov-2026).
-- Lo que el equipo anota de cada clienta en un tratamiento (sesiones hechas
-- antes del panel, notas, si se cerró el seguimiento y cuándo se le escribió):
--   seguimiento = { "<id del servicio>": { "<id de la clienta>": { previas, estado, nota, contactado } } }
-- Va en su propia columna y no en svc_meta porque svc_meta la publica la vista
-- public_pricing (la lee el sitio sin sesión). Esta columna no está en esa vista,
-- así que solo la lee el panel (RLS de bot_config).

alter table public.bot_config add column if not exists seguimiento jsonb not null default '{}'::jsonb;
