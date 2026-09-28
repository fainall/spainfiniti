-- ════════════════════════════════════════════════════════════════
-- Reporte de ventas para la cuenta de publicidad (pedido de Luis, 28-sep-2026)
--
-- Publicidad no lee la tabla de ventas (trae el cliente, sus notas y el
-- medio de pago, donde aparecen códigos de gift cards). Como con la agenda
-- (agenda_publicidad), se le entrega una vista con lo que el reporte
-- necesita: fecha, ítems, total, estado y profesional. De las notas solo
-- sale la marca de abono ("Abono de $X sobre $Y"), que el reporte usa para
-- no contar dos veces un servicio abonado.
-- ════════════════════════════════════════════════════════════════

create or replace view public.ventas_publicidad as
select s.id, s.sale_date, s.items, s.total, s.status, s.professional_id, s.appointment_id, s.created_at, s.tip,
       substring(s.notes from '^(Abono de \$[0-9.]+ sobre \$[0-9.]+)') as notes
  from public.sales s
 where public.is_panel_user();

revoke all on public.ventas_publicidad from anon, public;
grant select on public.ventas_publicidad to authenticated;
