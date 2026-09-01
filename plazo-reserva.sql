-- ════════════════════════════════════════════════════════════════
-- Cuantos dias por delante se puede reservar desde el sitio web.
--
-- El plazo se configura en el panel (Local -> Sitio Web) y se guarda
-- en bot_config.local_info.maxDias. El sitio solo ofrece esos dias,
-- pero eso es el navegador: quien llame a la funcion directamente se
-- lo saltaria. Por eso el limite se comprueba tambien aqui.
--
-- Se deja un dia de margen sobre lo que ofrece el sitio: el servidor
-- calcula en UTC y Chile va cuatro horas por detras, asi que sin ese
-- margen se rechazarian reservas legitimas del ultimo dia.
-- ════════════════════════════════════════════════════════════════

create or replace function reservar_hora(
  p_profesional uuid,
  p_cliente     uuid,
  p_nombre      text,
  p_fono        text,
  p_servicio    text,
  p_fecha       date,
  p_inicio      time,
  p_fin         time,
  p_precio      text default null,
  p_nota        text default null,
  p_origen      text default 'web'
) returns uuid
language plpgsql security definer set search_path = public as $$
declare
  v_id  uuid;
  v_max int;
begin
  if p_fecha < current_date then
    raise exception 'No se puede reservar en una fecha pasada';
  end if;

  -- plazo maximo de anticipacion, solo para lo que entra por el sitio
  if p_origen <> 'panel' then
    select nullif(local_info->>'maxDias','')::int into v_max from bot_config where id = 1;
    v_max := greatest(coalesce(v_max, 14), 1);
    if p_fecha > current_date + v_max then
      raise exception 'Por ahora solo se puede reservar hasta % dias por delante. Escribenos si necesitas una fecha mas lejana.', v_max;
    end if;
  end if;

  if p_fin <= p_inicio then
    raise exception 'La hora de termino debe ser posterior a la de inicio';
  end if;
  if not exists (select 1 from professionals
                  where id = p_profesional and active is distinct from false) then
    raise exception 'Ese profesional no esta disponible';
  end if;

  -- la hora se comprueba aqui, no en el navegador
  if exists (select 1 from appointments
              where professional_id = p_profesional
                and appt_date = p_fecha
                and status is distinct from 'cancelled'
                and start_time < p_fin
                and end_time   > p_inicio) then
    raise exception 'Esa hora ya esta tomada';
  end if;

  insert into appointments (professional_id, client_id, client_name, client_phone,
                            service_name, appt_date, start_time, end_time,
                            status, price, notes, origen)
  values (p_profesional, p_cliente, trim(p_nombre), p_fono,
          p_servicio, p_fecha, p_inicio, p_fin,
          'reserved', p_precio, coalesce(p_nota, 'Reservado desde el sitio web'),
          case when p_origen in ('web','bot','panel') then p_origen else 'web' end)
  returning id into v_id;

  return v_id;
end $$;

grant execute on function reservar_hora(uuid,uuid,text,text,text,date,time,time,text,text,text)
  to anon, authenticated;
