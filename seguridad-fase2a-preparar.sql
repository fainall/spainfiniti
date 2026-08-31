-- ═══════════════════════════════════════════════════════════════
--  SEGURIDAD FASE 2-A · PREPARAR (no cierra nada todavía)
--
--  Hoy el sitio web reserva horas leyendo y escribiendo las tablas
--  directamente con la llave pública. Eso obliga a dejar abiertas
--  clients y appointments, que es justo lo que queremos cerrar.
--
--  Este script crea la puerta estrecha por la que pasará el sitio:
--    · dos vistas que enseñan solo lo imprescindible para ofrecer horas
--    · dos funciones que crean la reserva validando en el servidor
--  Después de esto, el sitio ya no necesita tocar ninguna tabla.
--
--  Es aditivo: no cambia permisos ni rompe nada. Se puede ejecutar
--  con el sistema en marcha.
-- ═══════════════════════════════════════════════════════════════

-- ── 1. Profesionales visibles para el público ───────────────────
-- Sin comisión ni datos internos: solo lo que hace falta para agendar.
drop view if exists public_professionals;
create view public_professionals as
  select id, name, color, work_days, work_start, work_end, sort_order
  from professionals
  where active is distinct from false;
grant select on public_professionals to anon, authenticated;

-- ── 2. Horas ocupadas ───────────────────────────────────────────
-- Para calcular qué queda libre hace falta saber QUÉ franjas están
-- tomadas, no de quién son. Aquí no viaja ningún dato personal:
-- ni nombre, ni teléfono, ni precio.
drop view if exists public_busy;
create view public_busy as
  select professional_id, appt_date, start_time, end_time, service_name, status
  from appointments
  where status is distinct from 'cancelled'
    and appt_date >= (current_date - interval '1 day');
grant select on public_busy to anon, authenticated;

-- ── 3. Reutilizar o crear el cliente ────────────────────────────
-- El sitio no puede leer la tabla de clientes (ahí está toda la base
-- del negocio), pero sí necesita no duplicar a quien ya vino antes.
-- Esta función busca por los últimos 8 dígitos del teléfono y
-- devuelve el id, creando la ficha solo si no existía.
create or replace function reservar_cliente(p_nombre text, p_fono text, p_email text default null)
returns uuid
language plpgsql security definer set search_path = public as $$
declare
  v_id uuid;
  v_dig text;
begin
  if coalesce(trim(p_nombre), '') = '' then
    raise exception 'Falta el nombre';
  end if;

  v_dig := right(regexp_replace(coalesce(p_fono, ''), '[^0-9]', '', 'g'), 8);
  if length(v_dig) = 8 then
    select id into v_id from clients
     where right(regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g'), 8) = v_dig
     limit 1;
  end if;

  if v_id is null then
    insert into clients (name, phone, email)
    values (trim(p_nombre), p_fono, nullif(trim(coalesce(p_email, '')), ''))
    returning id into v_id;
  end if;

  return v_id;
end $$;
grant execute on function reservar_cliente(text, text, text) to anon, authenticated;

-- ── 4. Crear la reserva ─────────────────────────────────────────
-- Toda la validación ocurre aquí dentro, donde el visitante no puede
-- alterarla: que el profesional exista y atienda, que la hora no esté
-- tomada y que el estado sea siempre 'reserved'. Así nadie puede
-- colarse creando bloqueos o marcando citas como pagadas.
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
  p_nota        text default null
) returns uuid
language plpgsql security definer set search_path = public as $$
declare v_id uuid;
begin
  if p_fecha < current_date then
    raise exception 'No se puede reservar en una fecha pasada';
  end if;
  if p_fin <= p_inicio then
    raise exception 'La hora de término debe ser posterior a la de inicio';
  end if;
  if not exists (select 1 from professionals
                  where id = p_profesional and active is distinct from false) then
    raise exception 'Ese profesional no está disponible';
  end if;

  -- la hora se comprueba aquí, no en el navegador
  if exists (select 1 from appointments
              where professional_id = p_profesional
                and appt_date = p_fecha
                and status is distinct from 'cancelled'
                and start_time < p_fin
                and end_time   > p_inicio) then
    raise exception 'Esa hora ya está tomada';
  end if;

  insert into appointments (professional_id, client_id, client_name, client_phone,
                            service_name, appt_date, start_time, end_time,
                            status, price, notes)
  values (p_profesional, p_cliente, trim(p_nombre), p_fono,
          p_servicio, p_fecha, p_inicio, p_fin,
          'reserved', p_precio, coalesce(p_nota, 'Reservado desde el sitio web'))
  returning id into v_id;

  return v_id;
end $$;
grant execute on function reservar_hora(uuid, uuid, text, text, text, date, time, time, text, text)
  to anon, authenticated;

-- ── Comprobación ────────────────────────────────────────────────
--   select * from public_professionals;
--   select * from public_busy limit 5;
