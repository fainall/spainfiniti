-- ════════════════════════════════════════════════════════════════
-- Canje de gift cards desde la web.
--
-- Quien recibe una gift card escribe su codigo en spainfinity.cl/giftcard
-- y agenda sola. Para mostrarle que le queda por usar hace falta saber,
-- para ese codigo, que servicios ya se canjearon y cuales ya estan
-- agendados. Las gift cards viven en el servidor web; aqui solo se
-- consultan sus canjes y las reservas que las nombran.
--
-- La funcion solo acepta codigos con el formato GC-XXXXXX: sin eso, un
-- '%' en el codigo haria que la busqueda en las notas trajera todas las
-- reservas. Devuelve nombres de servicio y fechas, nunca datos de clientes.
-- ════════════════════════════════════════════════════════════════

create or replace function giftcard_estado(p_code text)
returns table(service_name text, tipo text, fecha date)
language sql stable security definer set search_path = public as $$
  with c as (
    select upper(trim(p_code)) as code
     where upper(trim(coalesce(p_code,''))) ~ '^GC-[A-Z0-9]{6,12}$'
  )
  /* servicios ya canjeados */
  select u.service_name, 'canjeado'::text, (u.used_at at time zone 'America/Santiago')::date
    from gift_card_uses u, c
   where upper(trim(u.code)) = c.code
  union all
  /* reservas vigentes que se pagan con esta gift card y aun no se cobran */
  select a.service_name, 'agendado'::text, a.appt_date
    from appointments a, c
   where a.notes ilike '%' || c.code || '%'
     and a.status is distinct from 'cancelled'
     and a.appt_date >= (now() at time zone 'America/Santiago')::date
     and not exists (select 1 from gift_card_uses u join sales s on s.id = u.sale_id
                      where s.appointment_id = a.id)
$$;

grant execute on function giftcard_estado(text) to anon, authenticated;

notify pgrst, 'reload schema';

-- Comprobacion: con un codigo de formato valido responde (vacio si no hay nada);
-- con un comodin no devuelve filas.
select 'formato valido' as prueba, count(*) from giftcard_estado('GC-J5K7FM6R7')
union all
select 'comodin', count(*) from giftcard_estado('%');
