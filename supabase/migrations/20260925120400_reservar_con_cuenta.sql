-- ════════════════════════════════════════════════════════════════
-- Cuentas de clientes · 04 · Reservar con la cuenta
--
-- Sin cuenta, el sitio sigue igual: reservar_cliente() busca o crea la
-- ficha por teléfono y reservar_hora() crea la reserva.
--
-- Con la sesión iniciada, la ficha la elige la base a partir de la cuenta
-- (nunca de lo que diga el navegador): la ficha ya ligada a la cuenta, o
-- una nueva con los datos del perfil. Después reservar_hora() se usa igual
-- que siempre, y la reserva toma la cuenta de su ficha (trigger de la
-- migración 03), así que queda con user_id sin cambiar reservar_hora().
-- ════════════════════════════════════════════════════════════════

create or replace function public.reservar_cliente_cuenta(p_fono text default null)
returns uuid language plpgsql security definer set search_path = '' as $$
declare
  v_uid uuid := auth.uid();
  v_correo text; v_nombre text; v_rut text; v_tel text; v_id uuid;
begin
  if v_uid is null then
    raise exception 'Necesitas ingresar a tu cuenta para reservar con ella';
  end if;
  select lower(trim(u.email)) into v_correo
    from auth.users u where u.id = v_uid and u.email_confirmed_at is not null;
  if v_correo is null then
    raise exception 'Confirma tu correo antes de reservar con tu cuenta';
  end if;

  select trim(p.nombre || ' ' || p.apellido), p.rut, p.telefono into v_nombre, v_rut, v_tel
    from public.profiles p where p.id = v_uid;
  if not found or length(coalesce(v_nombre, '')) < 2 then
    raise exception 'Completa tu nombre en Mi cuenta antes de reservar';
  end if;

  -- si el perfil no tiene teléfono y viene uno válido, queda guardado
  if v_tel is null and nullif(trim(coalesce(p_fono, '')), '') is not null then
    v_tel := regexp_replace(p_fono, '[^0-9+]', '', 'g');
    if v_tel ~ '^9[0-9]{8}$' then v_tel := '+56' || v_tel;
    elsif v_tel ~ '^569[0-9]{8}$' then v_tel := '+' || v_tel;
    end if;
    if v_tel !~ '^\+[0-9]{8,15}$' then
      raise exception 'El teléfono no es válido. Ejemplo: +56 9 1234 5678';
    end if;
    update public.profiles set telefono = v_tel where id = v_uid;
  end if;
  if v_tel is null then
    raise exception 'Necesitamos tu teléfono para confirmarte la hora';
  end if;

  -- por si el spa le agregó el correo a una ficha después de crear la cuenta
  perform public.vincular_fichas(v_uid);

  -- la ficha ligada con la reserva más reciente (si hay varias duplicadas)
  select c.id into v_id
    from public.clients c
   where c.user_id = v_uid
   order by (select max(a.appt_date) from public.appointments a where a.client_id = c.id) desc nulls last,
            c.created_at desc
   limit 1;

  if v_id is null then
    insert into public.clients (name, phone, email, rut, user_id)
    values (v_nombre, v_tel, v_correo, v_rut, v_uid)
    returning id into v_id;
  else
    -- completa lo que le falte a la ficha, sin pisar lo que el equipo ya tiene
    update public.clients
       set email = coalesce(nullif(trim(email), ''), v_correo),
           phone = coalesce(nullif(trim(phone), ''), v_tel),
           rut   = coalesce(nullif(trim(rut), ''), v_rut)
     where id = v_id;
  end if;
  return v_id;
end $$;
revoke all on function public.reservar_cliente_cuenta(text) from public, anon;
grant execute on function public.reservar_cliente_cuenta(text) to authenticated;
