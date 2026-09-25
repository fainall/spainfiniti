-- ════════════════════════════════════════════════════════════════
-- Cuentas de clientes · 01 · Blindaje previo
--
-- Hasta ahora las únicas cuentas de Supabase Auth eran las del equipo.
-- Con clientes registrados, "tener sesión" deja de significar "ser del
-- equipo": todo lo que se abrió a cualquier usuario con sesión
-- (rol authenticated) hay que cerrarlo al equipo del panel.
-- ════════════════════════════════════════════════════════════════

-- La agenda de publicidad (horas ocupadas, servicio y profesional) la
-- podía leer cualquier usuario con sesión: un cliente registrado habría
-- visto la agenda completa del spa. Ahora solo el equipo.
create or replace view public.agenda_publicidad as
  select a.id, a.appt_date, a.start_time, a.end_time, a.status, a.service_name, a.professional_id,
         case when a.status = 'block' then 'Bloqueado' else 'Reservado' end as client_name
    from public.appointments a
   where a.status <> 'cancelled'
     and public.is_panel_user();
revoke all on public.agenda_publicidad from anon, public;
grant select on public.agenda_publicidad to authenticated;
