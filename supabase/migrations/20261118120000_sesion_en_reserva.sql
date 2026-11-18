-- ════════════════════════════════════════════════════════════════
-- La sesión del tratamiento en cada reserva (pedido de Luis, 18-nov-2026)
--
-- Al agendar un tratamiento por sesiones (Ácido Nítrico de 8 sesiones…)
-- el panel deja elegir en qué sesión va la clienta. Si se deja vacía, el
-- Seguimiento de tratamientos la cuenta sola en orden de fecha, como antes.
-- ════════════════════════════════════════════════════════════════

alter table public.appointments add column if not exists sesion smallint;
alter table public.appointments drop constraint if exists appointments_sesion_rango;
alter table public.appointments add constraint appointments_sesion_rango
  check (sesion is null or sesion between 1 and 99);

notify pgrst, 'reload schema';
