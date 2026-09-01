-- ════════════════════════════════════════════════════════════════
-- Las reservas hechas en el sitio web salian como "tomadas por el
-- equipo en el mostrador".
--
-- Motivo: la columna origen tiene por defecto 'panel', y la funcion
-- reservar_hora() nunca lo indicaba, asi que toda reserva del sitio
-- heredaba el valor equivocado.
--
-- 1. la funcion guarda el origen
-- 2. el defecto deja de mentir: sin dato, el panel lo deduce
-- 3. se corrigen las que quedaron mal marcadas
-- ════════════════════════════════════════════════════════════════

-- ── 1. la funcion guarda de donde viene la reserva ──────────────
-- Lleva un parametro mas, asi que "create or replace" dejaria dos
-- versiones conviviendo y la llamada quedaria ambigua: se borra la
-- anterior por su firma exacta.
drop function if exists reservar_hora(uuid,uuid,text,text,text,date,time,time,text,text);

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
declare v_id uuid;
begin
  if p_fecha < current_date then
    raise exception 'No se puede reservar en una fecha pasada';
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

-- ── 2. sin dato, mejor vacio que un origen inventado ────────────
-- El panel manda 'panel' siempre que crea una cita, asi que no pierde
-- nada; y si algun dia otra via olvida indicarlo, se notara en vez de
-- disfrazarse de mostrador.
alter table appointments alter column origen drop default;

-- ── 3. las que quedaron mal marcadas ────────────────────────────
-- Solo las que llevan la nota que pone la propia funcion del sitio.
-- Las demas notas ("sesion intermedia") las escribe el equipo.
update appointments
set origen = 'web'
where origen = 'panel'
  and notes ilike '%sitio web%';

-- comprobacion
select coalesce(origen,'(sin dato)') as origen, count(*)
from appointments group by 1 order by 2 desc;
