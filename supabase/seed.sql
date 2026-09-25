-- ════════════════════════════════════════════════════════════════
-- Datos de PRUEBA para el entorno local. Todo es inventado: ninguna
-- persona, correo ni teléfono es real. Recrean los casos que existen
-- en producción para probar la vinculación de cuentas:
--   · Ana: dos fichas suyas con el mismo correo (una con mayúsculas y espacios)
--   · Familia Soto: dos personas distintas que comparten un correo
--   · Pedro: sin correo (solo teléfono)
--   · teléfonos en varios formatos (+569…, 569…, +56 9 …)
--   · notas internas en las reservas, que el cliente NUNCA debe ver
--
-- Cuentas del panel de prueba (contraseña de las dos: prueba1234):
--   admin@prueba.local (administrador) · keidy@prueba.local (profesional)
-- ════════════════════════════════════════════════════════════════

-- ── catálogo mínimo ──
insert into public.categories (id, name, sort_order) values
  ('podologia', 'Podología', 1), ('estetica', 'Estética', 2);

insert into public.services (id, cat_id, name, price, duration, sort_order) values
  ('atencion-podologica', 'podologia', 'Atención Podológica Integral', '$25.000', '60 min', 1),
  ('acido-nitrico-t1', 'podologia', 'Tratamiento con Ácido Nítrico+Alta Frecuencia-Tipo 1', '$27.000', '60 min', 2),
  ('perfilacion-cejas', 'estetica', 'Perfilación de Cejas', '$20.000', '20 min', 3);

insert into public.professionals (id, name, color, sort_order, work_start, work_end, work_days) values
  ('11111111-1111-1111-1111-111111111111', 'Keidy Prueba',  '#C27B8A', 1, '10:00', '20:00', '[1,2,3,4,5,6]'),
  ('22222222-2222-2222-2222-222222222222', 'Valentina Prueba', '#7FA7C9', 2, '10:00', '20:00', '[1,2,3,4,5,6]');

insert into public.bot_config (id, bot_name, local_info) values
  (1, 'Mariet', '{"maxDias": 8, "hours": {"0": ["", "", false], "1": ["10:00","20:00"], "2": ["10:00","20:00"], "3": ["10:00","20:00"], "4": ["10:00","20:00"], "5": ["10:00","20:00"], "6": ["10:00","18:00"]}}');

-- ── cuentas del panel ──
insert into auth.users (instance_id, id, aud, role, email, encrypted_password, email_confirmed_at,
                        raw_app_meta_data, raw_user_meta_data, created_at, updated_at,
                        confirmation_token, recovery_token, email_change, email_change_token_new)
values
  ('00000000-0000-0000-0000-000000000000', 'aaaaaaaa-0000-0000-0000-000000000001', 'authenticated', 'authenticated',
   'admin@prueba.local', extensions.crypt('prueba1234', extensions.gen_salt('bf')), now(),
   '{"provider":"email","providers":["email"]}', '{}', now(), now(), '', '', '', ''),
  ('00000000-0000-0000-0000-000000000000', 'aaaaaaaa-0000-0000-0000-000000000002', 'authenticated', 'authenticated',
   'keidy@prueba.local', extensions.crypt('prueba1234', extensions.gen_salt('bf')), now(),
   '{"provider":"email","providers":["email"]}', '{}', now(), now(), '', '', '', '');
insert into auth.identities (id, user_id, provider_id, identity_data, provider, created_at, updated_at, last_sign_in_at)
select gen_random_uuid(), u.id, u.id::text, jsonb_build_object('sub', u.id::text, 'email', u.email), 'email', now(), now(), now()
  from auth.users u where u.email in ('admin@prueba.local', 'keidy@prueba.local');

insert into public.panel_users (id, email, name, role, professional_id, active) values
  ('aaaaaaaa-0000-0000-0000-000000000001', 'admin@prueba.local', 'Admin Prueba', 'admin', null, true),
  ('aaaaaaaa-0000-0000-0000-000000000002', 'keidy@prueba.local', 'Keidy Prueba', 'pro', '11111111-1111-1111-1111-111111111111', true);

-- ── fichas de clientes ──
insert into public.clients (id, name, phone, email, notes) values
  -- Ana: la misma persona en dos fichas
  ('c0000000-0000-0000-0000-00000000000a', 'Ana Pérez',  '+56911112222', 'ana.perez@ejemplo.cl', 'Nota interna: prefiere la mañana'),
  ('c0000000-0000-0000-0000-00000000000b', 'Ana Perez',  '56911112222',  ' Ana.Perez@Ejemplo.cl ', null),
  -- Familia Soto: dos personas con un solo correo
  ('c0000000-0000-0000-0000-00000000000c', 'Carlos Soto', '+56 9 3333 4444', 'familia.soto@ejemplo.cl', null),
  ('c0000000-0000-0000-0000-00000000000d', 'Marta Soto',  '+56955556666',    'familia.soto@ejemplo.cl', 'Diabética: ofrecer desbaste'),
  -- Pedro: sin correo
  ('c0000000-0000-0000-0000-00000000000e', 'Pedro Rojas', '+56977778888', null, null);

-- ── reservas: pasadas y próximas (fechas relativas a hoy) ──
insert into public.appointments (professional_id, client_id, client_name, client_phone, service_name,
                                 appt_date, start_time, end_time, status, price, notes, origen) values
  ('11111111-1111-1111-1111-111111111111', 'c0000000-0000-0000-0000-00000000000a', 'Ana Pérez', '+56911112222',
   'Atención Podológica Integral', current_date - 40, '10:00', '11:00', 'completed', '$25.000', 'primera vez - abono 10 mil', 'panel'),
  ('11111111-1111-1111-1111-111111111111', 'c0000000-0000-0000-0000-00000000000b', 'Ana Perez', '56911112222',
   'Perfilación de Cejas', current_date - 10, '12:00', '12:20', 'cancelled', '$20.000', 'canceló por WhatsApp', 'bot'),
  ('22222222-2222-2222-2222-222222222222', 'c0000000-0000-0000-0000-00000000000a', 'Ana Pérez', '+56911112222',
   'Tratamiento con Ácido Nítrico+Alta Frecuencia-Tipo 1', current_date + 3, '15:00', '16:00', 'reserved', '$27.000', 'Agendado por el asistente de WhatsApp', 'bot'),
  ('11111111-1111-1111-1111-111111111111', 'c0000000-0000-0000-0000-00000000000c', 'Carlos Soto', '+56 9 3333 4444',
   'Atención Podológica Integral', current_date - 20, '11:00', '12:00', 'completed', '$25.000', null, 'panel'),
  ('22222222-2222-2222-2222-222222222222', 'c0000000-0000-0000-0000-00000000000d', 'Marta Soto', '+56955556666',
   'Atención Podológica Integral', current_date + 5, '10:00', '11:00', 'reserved', '$25.000', 'Diabética', 'panel'),
  ('11111111-1111-1111-1111-111111111111', 'c0000000-0000-0000-0000-00000000000e', 'Pedro Rojas', '+56977778888',
   'Perfilación de Cejas', current_date + 2, '17:00', '17:20', 'reserved', '$20.000', null, 'web'),
  -- un bloqueo del equipo (no es de ningún cliente)
  ('11111111-1111-1111-1111-111111111111', null, 'Bloqueado', null,
   null, current_date + 1, '13:00', '14:00', 'block', null, 'almuerzo', 'panel');
