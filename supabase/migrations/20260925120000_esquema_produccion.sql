-- ════════════════════════════════════════════════════════════════
-- Estructura de producción al 25-sep-2026 (solo esquema, sin datos)
--
-- Generada leyendo el catálogo desde el editor SQL de Supabase. Es la
-- base del entorno LOCAL: en producción ya existe todo esto y este
-- archivo NO se ejecuta allá. Las migraciones que siguen (…120100 en
-- adelante) son las que sí pasan a producción.
-- ════════════════════════════════════════════════════════════════
set check_function_bodies = false;

-- extensiones (pgcrypto, uuid-ossp, pg_stat_statements y vault ya vienen en Supabase)
create extension if not exists pgcrypto with schema extensions;
create extension if not exists "uuid-ossp" with schema extensions;

-- secuencias
create sequence if not exists public.promo_events_id_seq;
create sequence if not exists public.registro_cambios_id_seq;

-- ── tablas ──
create table public.appointments (
  id uuid default gen_random_uuid() not null,
  professional_id uuid,
  client_id uuid,
  service_id text,
  service_name text,
  client_name text not null,
  client_phone text,
  appt_date date not null,
  start_time time without time zone not null,
  end_time time without time zone not null,
  status text default 'active'::text,
  price text,
  notes text,
  created_at timestamp with time zone default now(),
  reminded_at timestamp with time zone,
  reminded_channel text,
  forzada boolean default false,
  origen text,
  correo_confirmacion_at timestamp with time zone
);

create table public.bot_config (
  id integer default 1 not null,
  active boolean default false,
  welcome text,
  tone text,
  faq jsonb default '[]'::jsonb,
  handoff_keywords text,
  updated_at timestamp with time zone default now(),
  rem_auto boolean default false,
  rem_hours integer default 24,
  rem_channel text default 'email'::text,
  rem_template text,
  inv_catalog jsonb default '{}'::jsonb,
  inv_alarms jsonb default '{}'::jsonb,
  local_info jsonb default '{}'::jsonb,
  prof_meta jsonb default '{}'::jsonb,
  prof_groups jsonb default '[]'::jsonb,
  svc_classes jsonb default '[]'::jsonb,
  svc_packages jsonb default '[]'::jsonb,
  svc_meta jsonb default '{}'::jsonb,
  resources jsonb default '[]'::jsonb,
  comm_cfg jsonb default '{}'::jsonb,
  plan_templates jsonb default '[]'::jsonb,
  smart_pricing jsonb default '{}'::jsonb,
  gc_catalog jsonb default '[]'::jsonb,
  cfg_cuenta jsonb default '{}'::jsonb,
  msg_templates jsonb default '[]'::jsonb,
  bot_name text,
  local_tips text
);

create table public.campaigns (
  id uuid default gen_random_uuid() not null,
  subject text,
  sent_count integer default 0,
  created_at timestamp with time zone default now()
);

create table public.cash_movements (
  id uuid default gen_random_uuid() not null,
  type text not null,
  description text,
  category text,
  amount numeric default 0 not null,
  method text,
  move_date date default CURRENT_DATE not null,
  created_at timestamp with time zone default now()
);

create table public.categories (
  id text not null,
  name text not null,
  tagline text,
  short_desc text,
  long_desc text,
  card_img text,
  hero_img text,
  link text,
  sort_order integer default 0,
  created_at timestamp with time zone default now()
);

create table public.client_comparisons (
  id uuid default gen_random_uuid() not null,
  client_id uuid not null,
  titulo text,
  antes_url text,
  antes_fecha date,
  despues_url text,
  despues_fecha date,
  notas text,
  created_at timestamp with time zone default now() not null,
  created_by uuid default auth.uid()
);

create table public.client_consents (
  id uuid default gen_random_uuid() not null,
  client_id uuid,
  consent_id uuid,
  consent_name text,
  signature text,
  signed_by text,
  signed_at timestamp with time zone default now()
);

create table public.client_records (
  id uuid default gen_random_uuid() not null,
  client_id uuid,
  template_id uuid,
  appointment_id uuid,
  professional_id uuid,
  template_name text,
  data jsonb default '{}'::jsonb not null,
  created_at timestamp with time zone default now(),
  updated_at timestamp with time zone default now(),
  record_date timestamp with time zone default now()
);

create table public.clients (
  id uuid default gen_random_uuid() not null,
  name text not null,
  phone text,
  email text,
  rut text,
  notes text,
  created_at timestamp with time zone default now(),
  gender text,
  birthday date
);

create table public.consents (
  id uuid default gen_random_uuid() not null,
  name text not null,
  body text,
  file_url text,
  active boolean default true,
  created_at timestamp with time zone default now()
);

create table public.email_suppressions (
  email text not null,
  created_at timestamp with time zone default now()
);

create table public.gift_card_uses (
  id uuid default gen_random_uuid() not null,
  code text not null,
  amount numeric default 0 not null,
  note text,
  used_at timestamp with time zone default now(),
  service_name text,
  sale_id uuid
);

create table public.page_content (
  id text not null,
  content jsonb default '{}'::jsonb not null,
  updated_at timestamp with time zone default now()
);

create table public.panel_users (
  id uuid not null,
  email text,
  name text,
  role text default 'pro'::text not null,
  professional_id uuid,
  active boolean default true,
  created_at timestamp with time zone default now(),
  permisos jsonb
);

create table public.payment_links (
  id uuid default gen_random_uuid() not null,
  appointment_id uuid,
  client_id uuid,
  client_name text,
  service_name text,
  amount integer default 0 not null,
  channel text,
  url text,
  status text default 'sent'::text,
  created_at timestamp with time zone default now(),
  paid_at timestamp with time zone
);

create table public.plans (
  id uuid default gen_random_uuid() not null,
  client_id uuid,
  client_name text,
  name text not null,
  service_id text,
  service_name text,
  total_sessions integer default 1,
  used_sessions integer default 0,
  price numeric default 0,
  purchase_date date default CURRENT_DATE,
  expiry_date date,
  status text default 'active'::text,
  notes text,
  created_at timestamp with time zone default now()
);

create table public.products (
  id uuid default gen_random_uuid() not null,
  name text not null,
  sku text,
  category text,
  price numeric default 0,
  cost numeric default 0,
  stock integer default 0,
  min_stock integer default 0,
  active boolean default true,
  notes text,
  created_at timestamp with time zone default now(),
  brand text,
  format text
);

create table public.professional_documents (
  id uuid default gen_random_uuid() not null,
  professional_id uuid not null,
  nombre text not null,
  url text not null,
  tipo text,
  tamano integer,
  created_at timestamp with time zone default now() not null,
  created_by uuid default auth.uid()
);

create table public.professionals (
  id uuid default gen_random_uuid() not null,
  name text not null,
  color text default '#C5A467'::text,
  active boolean default true,
  sort_order integer default 0,
  work_start time without time zone default '09:00:00'::time without time zone,
  work_end time without time zone default '20:00:00'::time without time zone,
  work_days jsonb default '[1, 2, 3, 4, 5, 6]'::jsonb,
  created_at timestamp with time zone default now(),
  commission_pct numeric default 0
);

create table public.promo_events (
  id integer default nextval('promo_events_id_seq'::regclass) not null,
  active boolean default false not null,
  config jsonb default '{}'::jsonb not null,
  created_at timestamp with time zone default now(),
  updated_at timestamp with time zone default now()
);

create table public.record_templates (
  id uuid default gen_random_uuid() not null,
  name text not null,
  description text,
  sections jsonb default '[]'::jsonb not null,
  services jsonb default '"all"'::jsonb,
  active boolean default true,
  sort_order integer default 0,
  created_at timestamp with time zone default now()
);

create table public.registro_cambios (
  id bigint default nextval('registro_cambios_id_seq'::regclass) not null,
  cuando timestamp with time zone default now() not null,
  tabla text not null,
  fila_id text not null,
  nombre text,
  accion text not null,
  quien_id uuid,
  quien text,
  cambios jsonb
);

create table public.sales (
  id uuid default gen_random_uuid() not null,
  client_id uuid,
  client_name text,
  sale_date date default CURRENT_DATE not null,
  items jsonb default '[]'::jsonb,
  total numeric default 0,
  payment_method text,
  status text default 'paid'::text,
  notes text,
  created_at timestamp with time zone default now(),
  professional_id uuid,
  tip numeric default 0,
  appointment_id uuid
);

create table public.services (
  id text not null,
  cat_id text,
  name text not null,
  tag text,
  price text not null,
  duration text,
  short_desc text,
  long_desc text,
  img text,
  link text,
  includes jsonb default '[]'::jsonb,
  highlighted boolean default false,
  has_discount boolean default false,
  discount_label text,
  original_price text,
  sort_order integer default 0,
  created_at timestamp with time zone default now(),
  process_steps jsonb default '[]'::jsonb,
  gc_discount_enabled boolean default false,
  gc_discount_percent integer default 0,
  gc_price text default ''::text,
  discount_text text default ''::text
);

create table public.stock_movements (
  id uuid default gen_random_uuid() not null,
  product_id uuid,
  product_name text,
  type text not null,
  qty numeric default 0 not null,
  note text,
  move_date date default CURRENT_DATE not null,
  created_at timestamp with time zone default now()
);

create table public.survey_responses (
  id uuid default gen_random_uuid() not null,
  survey_id uuid,
  answers jsonb default '{}'::jsonb,
  service_name text,
  client_name text,
  comment text,
  created_at timestamp with time zone default now()
);

create table public.surveys (
  id uuid default gen_random_uuid() not null,
  name text not null,
  questions jsonb default '[]'::jsonb,
  services jsonb default '[]'::jsonb,
  active boolean default true,
  created_at timestamp with time zone default now()
);

-- ── restricciones ──
alter table public.appointments add constraint appointments_pkey primary key (id);
alter table public.bot_config add constraint bot_config_pkey primary key (id);
alter table public.campaigns add constraint campaigns_pkey primary key (id);
alter table public.cash_movements add constraint cash_movements_pkey primary key (id);
alter table public.categories add constraint categories_pkey primary key (id);
alter table public.client_comparisons add constraint client_comparisons_pkey primary key (id);
alter table public.client_consents add constraint client_consents_pkey primary key (id);
alter table public.client_records add constraint client_records_pkey primary key (id);
alter table public.clients add constraint clients_pkey primary key (id);
alter table public.consents add constraint consents_pkey primary key (id);
alter table public.email_suppressions add constraint email_suppressions_pkey primary key (email);
alter table public.gift_card_uses add constraint gift_card_uses_pkey primary key (id);
alter table public.page_content add constraint page_content_pkey primary key (id);
alter table public.panel_users add constraint panel_users_pkey primary key (id);
alter table public.payment_links add constraint payment_links_pkey primary key (id);
alter table public.plans add constraint plans_pkey primary key (id);
alter table public.products add constraint products_pkey primary key (id);
alter table public.professional_documents add constraint professional_documents_pkey primary key (id);
alter table public.professionals add constraint professionals_pkey primary key (id);
alter table public.promo_events add constraint promo_events_pkey primary key (id);
alter table public.record_templates add constraint record_templates_pkey primary key (id);
alter table public.registro_cambios add constraint registro_cambios_pkey primary key (id);
alter table public.sales add constraint sales_pkey primary key (id);
alter table public.services add constraint services_pkey primary key (id);
alter table public.stock_movements add constraint stock_movements_pkey primary key (id);
alter table public.survey_responses add constraint survey_responses_pkey primary key (id);
alter table public.surveys add constraint surveys_pkey primary key (id);
alter table public.appointments add constraint appointments_client_id_fkey foreign key (client_id) references clients(id) on delete set null;
alter table public.appointments add constraint appointments_professional_id_fkey foreign key (professional_id) references professionals(id) on delete cascade;
alter table public.client_comparisons add constraint client_comparisons_client_id_fkey foreign key (client_id) references clients(id) on delete cascade;
alter table public.client_consents add constraint client_consents_client_id_fkey foreign key (client_id) references clients(id) on delete cascade;
alter table public.client_consents add constraint client_consents_consent_id_fkey foreign key (consent_id) references consents(id) on delete set null;
alter table public.client_records add constraint client_records_appointment_id_fkey foreign key (appointment_id) references appointments(id) on delete set null;
alter table public.client_records add constraint client_records_client_id_fkey foreign key (client_id) references clients(id) on delete cascade;
alter table public.client_records add constraint client_records_professional_id_fkey foreign key (professional_id) references professionals(id) on delete set null;
alter table public.client_records add constraint client_records_template_id_fkey foreign key (template_id) references record_templates(id) on delete set null;
alter table public.panel_users add constraint panel_users_id_fkey foreign key (id) references auth.users(id) on delete cascade;
alter table public.panel_users add constraint panel_users_professional_id_fkey foreign key (professional_id) references professionals(id) on delete set null;
alter table public.payment_links add constraint payment_links_appointment_id_fkey foreign key (appointment_id) references appointments(id) on delete set null;
alter table public.payment_links add constraint payment_links_client_id_fkey foreign key (client_id) references clients(id) on delete set null;
alter table public.plans add constraint plans_client_id_fkey foreign key (client_id) references clients(id) on delete set null;
alter table public.professional_documents add constraint professional_documents_professional_id_fkey foreign key (professional_id) references professionals(id) on delete cascade;
alter table public.sales add constraint sales_appointment_id_fkey foreign key (appointment_id) references appointments(id) on delete set null;
alter table public.sales add constraint sales_client_id_fkey foreign key (client_id) references clients(id) on delete set null;
alter table public.sales add constraint sales_professional_id_fkey foreign key (professional_id) references professionals(id) on delete set null;
alter table public.services add constraint services_cat_id_fkey foreign key (cat_id) references categories(id) on delete cascade;
alter table public.survey_responses add constraint survey_responses_survey_id_fkey foreign key (survey_id) references surveys(id) on delete cascade;

-- ── índices ──
create index idx_appt_prof on public.appointments using btree (professional_id);
create index idx_appt_date on public.appointments using btree (appt_date);
create index client_comparisons_cliente on public.client_comparisons using btree (client_id);
create index client_consents_client_idx on public.client_consents using btree (client_id);
create index client_records_client_idx on public.client_records using btree (client_id);
create index idx_clients_name on public.clients using btree (name);
create index payment_links_status_idx on public.payment_links using btree (status);
create index idx_plans_client on public.plans using btree (client_id);
create index professional_documents_prof on public.professional_documents using btree (professional_id);
create index registro_cambios_fila_idx on public.registro_cambios using btree (tabla, fila_id);
create index registro_cambios_cuando_idx on public.registro_cambios using btree (cuando desc);
create index sales_appt_idx on public.sales using btree (appointment_id);
create index idx_sales_date on public.sales using btree (sale_date);

-- ── funciones ──
create or replace function public.admin_exists()
returns boolean language sql stable security definer set search_path to 'public' as $function$
select exists (select 1 from panel_users where role = 'admin' and active)
$function$;

create or replace function public.duracion_min(t text)
returns integer language sql immutable as $function$
select case
  when t is null then null
  when t ~* 'h' then coalesce((regexp_match(t, '(\d+)\s*h', 'i'))[1]::int, 0) * 60
                   + coalesce((regexp_match(t, '(\d+)\s*min', 'i'))[1]::int, 0)
  when t ~ '\d' then (regexp_match(t, '(\d+)'))[1]::int
  else null end
$function$;

create or replace function public.fn_registrar_cambio()
returns trigger language plpgsql security definer set search_path to 'public' as $function$
declare
  v_antes jsonb := case when tg_op = 'INSERT' then null else to_jsonb(old) end;
  v_despues jsonb := case when tg_op = 'DELETE' then null else to_jsonb(new) end;
  v_cambios jsonb := '{}'::jsonb;
  k text;
begin
  if tg_op = 'UPDATE' then
    for k in select jsonb_object_keys(v_despues) loop
      if k in ('updated_at') then continue; end if;
      if (v_antes -> k) is distinct from (v_despues -> k) then
        v_cambios := v_cambios || jsonb_build_object(k, jsonb_build_object('antes', v_antes -> k, 'despues', v_despues -> k));
      end if;
    end loop;
    if v_cambios = '{}'::jsonb then return new; end if;
  elsif tg_op = 'INSERT' then v_cambios := v_despues;
  else v_cambios := v_antes; end if;
  insert into registro_cambios (tabla, fila_id, nombre, accion, quien_id, quien, cambios)
  values (tg_table_name, coalesce(v_despues ->> 'id', v_antes ->> 'id'), coalesce(v_despues ->> 'name', v_antes ->> 'name'),
          case tg_op when 'INSERT' then 'alta' when 'UPDATE' then 'cambio' else 'borrado' end, auth.uid(), quien_soy(), v_cambios);
  return coalesce(new, old);
end $function$;

create or replace function public.giftcard_estado(p_code text)
returns table(service_name text, tipo text, fecha date)
language sql stable security definer set search_path to 'public' as $function$
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
$function$;

create or replace function public.is_panel_admin()
returns boolean language sql stable security definer set search_path to 'public' as $function$
select exists(select 1 from public.panel_users
              where id = auth.uid() and coalesce(active, true) and role = 'admin');
$function$;

create or replace function public.is_panel_datos()
returns boolean language sql stable security definer set search_path to 'public' as $function$
select exists(select 1 from public.panel_users
              where id = auth.uid() and coalesce(active, true) and role <> 'publicidad');
$function$;

create or replace function public.is_panel_user()
returns boolean language sql stable security definer set search_path to 'public' as $function$
select exists (select 1 from panel_users where id = auth.uid() and active)
$function$;

create or replace function public.quien_soy()
returns text language sql stable security definer set search_path to 'public' as $function$
select coalesce(
  (select coalesce(nullif(name,''), email) from panel_users where id = auth.uid()),
  nullif(auth.jwt() ->> 'email', ''),
  case when auth.role() = 'anon' then 'sitio web (sin sesion)' else auth.role() end)
$function$;

create or replace function public.reservar_cliente(p_nombre text, p_fono text, p_email text default null::text)
returns uuid language plpgsql security definer set search_path to 'public' as $function$
declare v_id uuid; v_dig text;
begin
  if coalesce(trim(p_nombre), '') = '' then raise exception 'Falta el nombre'; end if;
  v_dig := right(regexp_replace(coalesce(p_fono, ''), '[^0-9]', '', 'g'), 8);
  if length(v_dig) = 8 then
    select id into v_id from clients where right(regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g'), 8) = v_dig limit 1;
  end if;
  if v_id is null then
    insert into clients (name, phone, email) values (trim(p_nombre), p_fono, nullif(trim(coalesce(p_email, '')), '')) returning id into v_id;
  end if;
  return v_id;
end $function$;

create or replace function public.reservar_hora(p_profesional uuid, p_cliente uuid, p_nombre text, p_fono text, p_servicio text, p_fecha date,
  p_inicio time without time zone, p_fin time without time zone, p_precio text default null::text, p_nota text default null::text,
  p_origen text default 'web'::text)
returns uuid language plpgsql security definer set search_path to 'public' as $function$
declare
  v_id uuid; v_max int; v_hoy date; v_ahora time; v_dow int; v_dias jsonb; v_ws time; v_we time;
  v_hpd jsonb; v_dia jsonb; v_b jsonb; v_dur int; v_svc_dur int; v_fono text; v_n int;
begin
  /* la hora de Chile, no la del servidor */
  v_hoy := (now() at time zone 'America/Santiago')::date;
  v_ahora := (now() at time zone 'America/Santiago')::time;

  if p_fecha < v_hoy then
    raise exception 'No se puede reservar en una fecha pasada';
  end if;
  if p_origen <> 'panel' and p_fecha = v_hoy and p_inicio < v_ahora then
    raise exception 'Esa hora ya paso';
  end if;
  if p_fin <= p_inicio then
    raise exception 'La hora de termino debe ser posterior a la de inicio';
  end if;
  if length(trim(coalesce(p_nombre,''))) < 2 then
    raise exception 'Falta el nombre';
  end if;

  /* plazo maximo de anticipacion, solo para lo que entra por el sitio */
  if p_origen <> 'panel' then
    select nullif(local_info->>'maxDias','')::int into v_max from bot_config where id = 1;
    v_max := greatest(coalesce(v_max, 14), 1);
    if p_fecha > v_hoy + v_max then
      raise exception 'Por ahora solo se puede reservar hasta % dias por delante. Escribenos si necesitas una fecha mas lejana.', v_max;
    end if;
  end if;

  /* duracion razonable, y la del servicio si se conoce */
  v_dur := (extract(epoch from (p_fin - p_inicio)) / 60)::int;
  if v_dur < 10 or v_dur > 240 then
    raise exception 'Duracion no valida';
  end if;
  select duracion_min(duration) into v_svc_dur
    from services where lower(name) = lower(trim(coalesce(p_servicio,''))) limit 1;
  if not found then
    raise exception 'Ese servicio no existe';
  end if;
  if v_svc_dur is not null and v_svc_dur > 0 and v_dur <> v_svc_dur then
    raise exception 'La duracion no corresponde al servicio';
  end if;

  /* el profesional y su horario de ese dia */
  select work_days, work_start, work_end into v_dias, v_ws, v_we
    from professionals where id = p_profesional and active is distinct from false;
  if not found then
    raise exception 'Ese profesional no esta disponible';
  end if;
  v_dow := extract(dow from p_fecha)::int; -- 0 = domingo
  /* el panel guarda el domingo como 0; un gestor antiguo lo guardaba como 7 */
  if v_dias is not null and jsonb_typeof(v_dias) = 'array'
     and not (v_dias @> to_jsonb(v_dow) or (v_dow = 0 and v_dias @> '7'::jsonb)) then
    raise exception 'El profesional no atiende ese dia';
  end if;
  select prof_meta -> p_profesional::text -> 'horasPorDia' -> v_dow::text into v_hpd
    from bot_config where id = 1;
  if v_hpd is not null and jsonb_typeof(v_hpd) = 'array' and coalesce(v_hpd->>0,'') <> '' then
    v_ws := (v_hpd->>0)::time; v_we := (v_hpd->>1)::time;
  end if;
  if v_ws is not null and v_we is not null and (p_inicio < v_ws or p_fin > v_we) then
    raise exception 'Esa hora esta fuera del horario del profesional';
  end if;

  /* sus descansos */
  for v_b in
    select value from jsonb_array_elements(coalesce(
      (select prof_meta -> p_profesional::text -> 'breaks' -> v_dow::text from bot_config where id = 1),
      '[]'::jsonb))
  loop
    if jsonb_typeof(v_b) = 'array' and coalesce(v_b->>0,'') <> ''
       and p_inicio < (v_b->>1)::time and p_fin > (v_b->>0)::time then
      raise exception 'Esa hora cae en un descanso';
    end if;
  end loop;

  /* y el horario del local */
  select local_info -> 'hours' -> v_dow::text into v_dia from bot_config where id = 1;
  if v_dia is not null and jsonb_typeof(v_dia) = 'array' then
    if (v_dia -> 2)::text = 'false' then
      raise exception 'El local esta cerrado ese dia';
    end if;
    if coalesce(v_dia->>0,'') <> '' and (p_inicio < (v_dia->>0)::time or p_fin > (v_dia->>1)::time) then
      raise exception 'Esa hora esta fuera del horario del local';
    end if;
  end if;

  /* Freno al abuso: tope de reservas pendientes por telefono desde fuera.
     Se lee de bot_config.local_info.maxPendientes. Vacio o 0 = sin tope. */
  select nullif(local_info->>'maxPendientes','')::int into v_max from bot_config where id = 1;
  v_fono := regexp_replace(coalesce(p_fono,''), '\D', '', 'g');
  if coalesce(v_max, 0) > 0 and p_origen <> 'panel' and length(v_fono) >= 8 then
    select count(*) into v_n from appointments
     where regexp_replace(coalesce(client_phone,''), '\D', '', 'g') like '%' || right(v_fono, 8)
       and appt_date >= v_hoy
       and status is distinct from 'cancelled'
       and origen in ('web','bot');
    if v_n >= v_max then
      raise exception 'Ya tienes % reservas pendientes. Escribenos por WhatsApp para agendar otra.', v_max;
    end if;
  end if;

  /* candado por profesional y dia: dos reservas a la vez ya no se cuelan */
  perform pg_advisory_xact_lock(hashtext(p_profesional::text || '|' || p_fecha::text));

  if exists (select 1 from appointments
              where professional_id = p_profesional
                and appt_date = p_fecha
                and status is distinct from 'cancelled'
                and start_time < p_fin
                and end_time > p_inicio) then
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
end $function$;

create or replace function public.rol_panel()
returns text language sql stable security definer set search_path to 'public' as $function$
select role from public.panel_users where id = auth.uid() and coalesce(active, true) limit 1;
$function$;

-- ── vistas ──
create or replace view public.public_pricing as
select smart_pricing, svc_classes, svc_packages, svc_meta, local_info, resources, gc_catalog,
  (select coalesce(jsonb_object_agg(t.k, coalesce((t.v -> 'services'::text), '"all"'::jsonb)), '{}'::jsonb)
     from jsonb_each(coalesce(bot_config.prof_meta, '{}'::jsonb)) t(k, v)) as prof_services,
  (select coalesce(jsonb_object_agg(t2.k, jsonb_build_object('publicName', (t2.v -> 'publicName'::text),
            'online', coalesce((t2.v -> 'online'::text), 'true'::jsonb),
            'horasPorDia', coalesce((t2.v -> 'horasPorDia'::text), '{}'::jsonb),
            'breaks', coalesce((t2.v -> 'breaks'::text), '{}'::jsonb))), '{}'::jsonb)
     from jsonb_each(coalesce(bot_config.prof_meta, '{}'::jsonb)) t2(k, v)) as prof_publico
  from bot_config
 where (id = 1);

create or replace view public.public_professionals as
select id, name, color, work_days, work_start, work_end, sort_order
  from professionals
 where (active is distinct from false);

create or replace view public.public_busy as
select professional_id, appt_date, start_time, end_time, service_name, status
  from appointments
 where ((status is distinct from 'cancelled'::text) and (appt_date >= (current_date - '1 day'::interval)));

create or replace view public.agenda_publicidad as
select id, appt_date, start_time, end_time, status, service_name, professional_id,
       case when (status = 'block'::text) then 'Bloqueado'::text else 'Reservado'::text end as client_name
  from appointments a
 where (status <> 'cancelled'::text);

create or replace view public.profesionales_publicidad as
select id, name, color, active, sort_order, work_start, work_end, work_days, created_at
  from professionals
 where is_panel_user();

-- ── disparadores ──
create trigger trg_registro_categories after insert or delete or update on public.categories for each row execute function fn_registrar_cambio();
create trigger trg_registro_services after insert or delete or update on public.services for each row execute function fn_registrar_cambio();

-- ── seguridad por filas ──
do $$
declare t text;
begin
  foreach t in array array['appointments','bot_config','campaigns','cash_movements','categories','client_comparisons',
    'client_consents','client_records','clients','consents','email_suppressions','gift_card_uses','page_content',
    'panel_users','payment_links','plans','products','professional_documents','professionals','promo_events',
    'record_templates','registro_cambios','sales','services','stock_movements','survey_responses','surveys'] loop
    execute format('alter table public.%I enable row level security', t);
  end loop;
  -- el patrón general: el equipo (sin publicidad) lee y escribe
  foreach t in array array['appointments','bot_config','campaigns','cash_movements','client_comparisons','client_consents',
    'client_records','clients','consents','email_suppressions','gift_card_uses','payment_links','plans','products',
    'professionals','record_templates','sales','stock_movements','survey_responses'] loop
    execute format('create policy panel_escribe_%s on public.%I as permissive for all to authenticated using (is_panel_datos()) with check (is_panel_datos())', t, t);
    execute format('create policy panel_lee_%s on public.%I as permissive for select to authenticated using (is_panel_datos())', t, t);
  end loop;
  -- catálogo público: todos leen, el equipo escribe
  foreach t in array array['categories','page_content','promo_events','services','surveys'] loop
    execute format('create policy panel_escribe_%s on public.%I as permissive for all to authenticated using (is_panel_datos()) with check (is_panel_datos())', t, t);
    execute format('create policy publico_lee_%s on public.%I as permissive for select to public using (true)', t, t);
  end loop;
end $$;

create policy panel_escribe_panel_users on public.panel_users as permissive for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
create policy panel_lee_panel_users on public.panel_users as permissive for select to authenticated using (is_panel_datos());
create policy panel_lee_su_perfil on public.panel_users as permissive for select to authenticated using ((id = auth.uid()));
create policy admin_escribe_prof_docs on public.professional_documents as permissive for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
create policy admin_lee_prof_docs on public.professional_documents as permissive for select to authenticated using (is_panel_admin());
create policy registro_lee on public.registro_cambios as permissive for select to authenticated using (is_panel_datos());
create policy publico_responde_encuesta on public.survey_responses as permissive for insert to anon with check (true);

-- almacenamiento: cubetas privadas y sus reglas
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types) values
  ('consentimientos', 'consentimientos', false, null, null),
  ('fichas', 'fichas', false, null, null),
  ('documentos', 'documentos', false, 15728640, null)
on conflict (id) do nothing;
create policy consent_actualiza on storage.objects as permissive for update to authenticated using (((bucket_id = 'consentimientos'::text) and is_panel_user())) with check (((bucket_id = 'consentimientos'::text) and is_panel_user()));
create policy consent_lee on storage.objects as permissive for select to authenticated using (((bucket_id = 'consentimientos'::text) and is_panel_user()));
create policy consent_sube on storage.objects as permissive for insert to authenticated with check (((bucket_id = 'consentimientos'::text) and is_panel_user()));
create policy documentos_admin_borra on storage.objects as permissive for delete to authenticated using (((bucket_id = 'documentos'::text) and is_panel_admin()));
create policy documentos_admin_cambia on storage.objects as permissive for update to authenticated using (((bucket_id = 'documentos'::text) and is_panel_admin()));
create policy documentos_admin_lee on storage.objects as permissive for select to authenticated using (((bucket_id = 'documentos'::text) and is_panel_admin()));
create policy documentos_admin_sube on storage.objects as permissive for insert to authenticated with check (((bucket_id = 'documentos'::text) and is_panel_admin()));
create policy fichas_actualiza on storage.objects as permissive for update to authenticated using (((bucket_id = 'fichas'::text) and is_panel_user())) with check (((bucket_id = 'fichas'::text) and is_panel_user()));
create policy fichas_borra on storage.objects as permissive for delete to authenticated using (((bucket_id = 'fichas'::text) and is_panel_user()));
create policy fichas_lee on storage.objects as permissive for select to authenticated using (((bucket_id = 'fichas'::text) and is_panel_user()));
create policy fichas_sube on storage.objects as permissive for insert to authenticated with check (((bucket_id = 'fichas'::text) and is_panel_user()));

-- ── permisos (igual que en producción) ──
-- tablas y vistas: todo para anon, authenticated y service_role (las reglas por filas protegen),
-- salvo las dos vistas del perfil de publicidad
grant all on all tables in schema public to anon, authenticated, service_role;
grant all on all sequences in schema public to anon, authenticated, service_role;
revoke all on public.agenda_publicidad from anon, authenticated;
grant select on public.agenda_publicidad to authenticated;
revoke all on public.profesionales_publicidad from anon;
-- funciones: ejecutables por todos (como en producción)
grant execute on all functions in schema public to public, anon, authenticated, service_role;

-- realtime
alter publication supabase_realtime add table public.clients, public.appointments, public.sales;
