-- ════════════════════════════════════════════════════════════════
-- El historial de una profesional no se pierde nunca (Luis, 23-oct-2026)
--
-- Antes: borrar una profesional BORRABA todas sus reservas (asistencia y
-- pagos) en cascada, y dejaba sus ventas y fichas clínicas sin profesional.
-- El 22-oct se perdieron así 849 reservas (se recuperaron del respaldo).
--
-- Ahora la base NO deja borrar a una profesional que tenga reservas, ventas
-- o fichas clínicas: hay que dejarla inactiva (el panel lo hace solo). Una
-- profesional sin nada registrado se puede seguir borrando.
-- ════════════════════════════════════════════════════════════════

alter table public.appointments drop constraint if exists appointments_professional_id_fkey;
alter table public.appointments add constraint appointments_professional_id_fkey
  foreign key (professional_id) references public.professionals(id) on delete restrict;

alter table public.sales drop constraint if exists sales_professional_id_fkey;
alter table public.sales add constraint sales_professional_id_fkey
  foreign key (professional_id) references public.professionals(id) on delete restrict;

alter table public.client_records drop constraint if exists client_records_professional_id_fkey;
alter table public.client_records add constraint client_records_professional_id_fkey
  foreign key (professional_id) references public.professionals(id) on delete restrict;

notify pgrst, 'reload schema';
