-- ════════════════════════════════════════════════════════════════
--  INSTALACIÓN — sistema de agenda para centros de estética y salud
--
--  Deja una base nueva lista para un cliente: tablas, índices, reglas
--  de seguridad, vistas del sitio público, funciones de reserva y
--  carpetas de archivos. No trae datos de ningún negocio.
--
--  CÓMO SE USA
--    1. Crear un proyecto nuevo en supabase.com. UNO POR CLIENTE:
--       si dos clientes comparten proyecto, cada uno ve los datos
--       y las fichas médicas del otro.
--    2. SQL Editor → pegar este archivo entero → Run.
--    3. Settings → API: copiar "Project URL" y la llave "anon" a
--       config-cliente.js y a api/cliente-config.php.
--    4. Abrir el panel y crear la cuenta de administrador.
--    5. Cargar servicios, profesionales y horarios desde el panel.
--
--  Se puede repetir sin miedo: no borra nada de lo que ya exista.
--
--  QUIÉN PUEDE VER QUÉ
--    · Llave pública (el sitio web): solo las tres vistas public_*,
--      la encuesta y las funciones de reservar. Nada más.
--    · Usuario con sesión: solo si tiene ficha activa en panel_users.
--    · Caja, configuración y usuarios: solo administradores.
-- ════════════════════════════════════════════════════════════════


-- ── 1. Tablas ───────────────────────────────────────────────────

create table if not exists appointments (
  id                    uuid primary key default gen_random_uuid(),
  professional_id       uuid,
  client_id             uuid,
  service_id            text,
  service_name          text,
  client_name           text not null,
  client_phone          text,
  appt_date             date not null,
  start_time            time not null,
  end_time              time not null,
  status                text default 'active'::text,
  price                 text,
  notes                 text,
  created_at            timestamptz default now(),
  reminded_at           timestamptz,
  reminded_channel      text,
  forzada               boolean default false,
  origen                text
);
alter table appointments add column if not exists id uuid default gen_random_uuid();
alter table appointments add column if not exists professional_id uuid;
alter table appointments add column if not exists client_id uuid;
alter table appointments add column if not exists service_id text;
alter table appointments add column if not exists service_name text;
alter table appointments add column if not exists client_name text;
alter table appointments add column if not exists client_phone text;
alter table appointments add column if not exists appt_date date;
alter table appointments add column if not exists start_time time;
alter table appointments add column if not exists end_time time;
alter table appointments add column if not exists status text default 'active'::text;
alter table appointments add column if not exists price text;
alter table appointments add column if not exists notes text;
alter table appointments add column if not exists created_at timestamptz default now();
alter table appointments add column if not exists reminded_at timestamptz;
alter table appointments add column if not exists reminded_channel text;
alter table appointments add column if not exists forzada boolean default false;
alter table appointments add column if not exists origen text;

create table if not exists bot_config (
  id                    integer primary key default 1,
  active                boolean default false,
  welcome               text,
  tone                  text,
  faq                   jsonb default '[]'::jsonb,
  handoff_keywords      text,
  updated_at            timestamptz default now(),
  rem_auto              boolean default false,
  rem_hours             integer default 24,
  rem_channel           text default 'email'::text,
  rem_template          text,
  inv_catalog           jsonb default '{}'::jsonb,
  inv_alarms            jsonb default '{}'::jsonb,
  local_info            jsonb default '{}'::jsonb,
  prof_meta             jsonb default '{}'::jsonb,
  prof_groups           jsonb default '[]'::jsonb,
  svc_classes           jsonb default '[]'::jsonb,
  svc_packages          jsonb default '[]'::jsonb,
  svc_meta              jsonb default '{}'::jsonb,
  resources             jsonb default '[]'::jsonb,
  comm_cfg              jsonb default '{}'::jsonb,
  plan_templates        jsonb default '[]'::jsonb,
  smart_pricing         jsonb default '{}'::jsonb,
  gc_catalog            jsonb default '[]'::jsonb,
  cfg_cuenta            jsonb default '{}'::jsonb,
  msg_templates         jsonb default '[]'::jsonb,
  bot_name              text,
  local_tips            text
);
alter table bot_config add column if not exists id integer default 1;
alter table bot_config add column if not exists active boolean default false;
alter table bot_config add column if not exists welcome text;
alter table bot_config add column if not exists tone text;
alter table bot_config add column if not exists faq jsonb default '[]'::jsonb;
alter table bot_config add column if not exists handoff_keywords text;
alter table bot_config add column if not exists updated_at timestamptz default now();
alter table bot_config add column if not exists rem_auto boolean default false;
alter table bot_config add column if not exists rem_hours integer default 24;
alter table bot_config add column if not exists rem_channel text default 'email'::text;
alter table bot_config add column if not exists rem_template text;
alter table bot_config add column if not exists inv_catalog jsonb default '{}'::jsonb;
alter table bot_config add column if not exists inv_alarms jsonb default '{}'::jsonb;
alter table bot_config add column if not exists local_info jsonb default '{}'::jsonb;
alter table bot_config add column if not exists prof_meta jsonb default '{}'::jsonb;
alter table bot_config add column if not exists prof_groups jsonb default '[]'::jsonb;
alter table bot_config add column if not exists svc_classes jsonb default '[]'::jsonb;
alter table bot_config add column if not exists svc_packages jsonb default '[]'::jsonb;
alter table bot_config add column if not exists svc_meta jsonb default '{}'::jsonb;
alter table bot_config add column if not exists resources jsonb default '[]'::jsonb;
alter table bot_config add column if not exists comm_cfg jsonb default '{}'::jsonb;
alter table bot_config add column if not exists plan_templates jsonb default '[]'::jsonb;
alter table bot_config add column if not exists smart_pricing jsonb default '{}'::jsonb;
alter table bot_config add column if not exists gc_catalog jsonb default '[]'::jsonb;
alter table bot_config add column if not exists cfg_cuenta jsonb default '{}'::jsonb;
alter table bot_config add column if not exists msg_templates jsonb default '[]'::jsonb;
alter table bot_config add column if not exists bot_name text;
alter table bot_config add column if not exists local_tips text;

create table if not exists campaigns (
  id                    uuid primary key default gen_random_uuid(),
  subject               text,
  sent_count            integer default 0,
  created_at            timestamptz default now()
);
alter table campaigns add column if not exists id uuid default gen_random_uuid();
alter table campaigns add column if not exists subject text;
alter table campaigns add column if not exists sent_count integer default 0;
alter table campaigns add column if not exists created_at timestamptz default now();

create table if not exists cash_movements (
  id                    uuid primary key default gen_random_uuid(),
  type                  text not null,
  description           text,
  category              text,
  amount                numeric default 0 not null,
  method                text,
  move_date             date default CURRENT_DATE not null,
  created_at            timestamptz default now()
);
alter table cash_movements add column if not exists id uuid default gen_random_uuid();
alter table cash_movements add column if not exists type text;
alter table cash_movements add column if not exists description text;
alter table cash_movements add column if not exists category text;
alter table cash_movements add column if not exists amount numeric default 0;
alter table cash_movements add column if not exists method text;
alter table cash_movements add column if not exists move_date date default CURRENT_DATE;
alter table cash_movements add column if not exists created_at timestamptz default now();

create table if not exists categories (
  id                    text primary key,
  name                  text not null,
  tagline               text,
  short_desc            text,
  long_desc             text,
  card_img              text,
  hero_img              text,
  link                  text,
  sort_order            integer default 0,
  created_at            timestamptz default now()
);
alter table categories add column if not exists id text;
alter table categories add column if not exists name text;
alter table categories add column if not exists tagline text;
alter table categories add column if not exists short_desc text;
alter table categories add column if not exists long_desc text;
alter table categories add column if not exists card_img text;
alter table categories add column if not exists hero_img text;
alter table categories add column if not exists link text;
alter table categories add column if not exists sort_order integer default 0;
alter table categories add column if not exists created_at timestamptz default now();

create table if not exists client_consents (
  id                    uuid primary key default gen_random_uuid(),
  client_id             uuid,
  consent_id            uuid,
  consent_name          text,
  signature             text,
  signed_by             text,
  signed_at             timestamptz default now()
);
alter table client_consents add column if not exists id uuid default gen_random_uuid();
alter table client_consents add column if not exists client_id uuid;
alter table client_consents add column if not exists consent_id uuid;
alter table client_consents add column if not exists consent_name text;
alter table client_consents add column if not exists signature text;
alter table client_consents add column if not exists signed_by text;
alter table client_consents add column if not exists signed_at timestamptz default now();

create table if not exists client_records (
  id                    uuid primary key default gen_random_uuid(),
  client_id             uuid,
  template_id           uuid,
  appointment_id        uuid,
  professional_id       uuid,
  template_name         text,
  data                  jsonb default '{}'::jsonb not null,
  created_at            timestamptz default now(),
  updated_at            timestamptz default now(),
  record_date           timestamptz default now()
);
alter table client_records add column if not exists id uuid default gen_random_uuid();
alter table client_records add column if not exists client_id uuid;
alter table client_records add column if not exists template_id uuid;
alter table client_records add column if not exists appointment_id uuid;
alter table client_records add column if not exists professional_id uuid;
alter table client_records add column if not exists template_name text;
alter table client_records add column if not exists data jsonb default '{}'::jsonb;
alter table client_records add column if not exists created_at timestamptz default now();
alter table client_records add column if not exists updated_at timestamptz default now();
alter table client_records add column if not exists record_date timestamptz default now();

create table if not exists clients (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  phone                 text,
  email                 text,
  rut                   text,
  notes                 text,
  created_at            timestamptz default now(),
  gender                text,
  birthday              date
);
alter table clients add column if not exists id uuid default gen_random_uuid();
alter table clients add column if not exists name text;
alter table clients add column if not exists phone text;
alter table clients add column if not exists email text;
alter table clients add column if not exists rut text;
alter table clients add column if not exists notes text;
alter table clients add column if not exists created_at timestamptz default now();
alter table clients add column if not exists gender text;
alter table clients add column if not exists birthday date;

create table if not exists consents (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  body                  text,
  file_url              text,
  active                boolean default true,
  created_at            timestamptz default now()
);
alter table consents add column if not exists id uuid default gen_random_uuid();
alter table consents add column if not exists name text;
alter table consents add column if not exists body text;
alter table consents add column if not exists file_url text;
alter table consents add column if not exists active boolean default true;
alter table consents add column if not exists created_at timestamptz default now();

create table if not exists email_suppressions (
  email                 text primary key,
  created_at            timestamptz default now()
);
alter table email_suppressions add column if not exists email text;
alter table email_suppressions add column if not exists created_at timestamptz default now();

create table if not exists gift_card_uses (
  id                    uuid primary key default gen_random_uuid(),
  code                  text not null,
  amount                numeric default 0 not null,
  note                  text,
  used_at               timestamptz default now()
);
alter table gift_card_uses add column if not exists id uuid default gen_random_uuid();
alter table gift_card_uses add column if not exists code text;
alter table gift_card_uses add column if not exists amount numeric default 0;
alter table gift_card_uses add column if not exists note text;
alter table gift_card_uses add column if not exists used_at timestamptz default now();

create table if not exists page_content (
  id                    text primary key,
  content               jsonb default '{}'::jsonb not null,
  updated_at            timestamptz default now()
);
alter table page_content add column if not exists id text;
alter table page_content add column if not exists content jsonb default '{}'::jsonb;
alter table page_content add column if not exists updated_at timestamptz default now();

create table if not exists panel_users (
  id                    uuid primary key,
  email                 text,
  name                  text,
  role                  text default 'pro'::text not null,
  professional_id       uuid,
  active                boolean default true,
  created_at            timestamptz default now()
);
alter table panel_users add column if not exists id uuid;
alter table panel_users add column if not exists email text;
alter table panel_users add column if not exists name text;
alter table panel_users add column if not exists role text default 'pro'::text;
alter table panel_users add column if not exists professional_id uuid;
alter table panel_users add column if not exists active boolean default true;
alter table panel_users add column if not exists created_at timestamptz default now();

create table if not exists payment_links (
  id                    uuid primary key default gen_random_uuid(),
  appointment_id        uuid,
  client_id             uuid,
  client_name           text,
  service_name          text,
  amount                integer default 0 not null,
  channel               text,
  url                   text,
  status                text default 'sent'::text,
  created_at            timestamptz default now(),
  paid_at               timestamptz
);
alter table payment_links add column if not exists id uuid default gen_random_uuid();
alter table payment_links add column if not exists appointment_id uuid;
alter table payment_links add column if not exists client_id uuid;
alter table payment_links add column if not exists client_name text;
alter table payment_links add column if not exists service_name text;
alter table payment_links add column if not exists amount integer default 0;
alter table payment_links add column if not exists channel text;
alter table payment_links add column if not exists url text;
alter table payment_links add column if not exists status text default 'sent'::text;
alter table payment_links add column if not exists created_at timestamptz default now();
alter table payment_links add column if not exists paid_at timestamptz;

create table if not exists plans (
  id                    uuid primary key default gen_random_uuid(),
  client_id             uuid,
  client_name           text,
  name                  text not null,
  service_id            text,
  service_name          text,
  total_sessions        integer default 1,
  used_sessions         integer default 0,
  price                 numeric default 0,
  purchase_date         date default CURRENT_DATE,
  expiry_date           date,
  status                text default 'active'::text,
  notes                 text,
  created_at            timestamptz default now()
);
alter table plans add column if not exists id uuid default gen_random_uuid();
alter table plans add column if not exists client_id uuid;
alter table plans add column if not exists client_name text;
alter table plans add column if not exists name text;
alter table plans add column if not exists service_id text;
alter table plans add column if not exists service_name text;
alter table plans add column if not exists total_sessions integer default 1;
alter table plans add column if not exists used_sessions integer default 0;
alter table plans add column if not exists price numeric default 0;
alter table plans add column if not exists purchase_date date default CURRENT_DATE;
alter table plans add column if not exists expiry_date date;
alter table plans add column if not exists status text default 'active'::text;
alter table plans add column if not exists notes text;
alter table plans add column if not exists created_at timestamptz default now();

create table if not exists products (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  sku                   text,
  category              text,
  price                 numeric default 0,
  cost                  numeric default 0,
  stock                 integer default 0,
  min_stock             integer default 0,
  active                boolean default true,
  notes                 text,
  created_at            timestamptz default now(),
  brand                 text,
  format                text
);
alter table products add column if not exists id uuid default gen_random_uuid();
alter table products add column if not exists name text;
alter table products add column if not exists sku text;
alter table products add column if not exists category text;
alter table products add column if not exists price numeric default 0;
alter table products add column if not exists cost numeric default 0;
alter table products add column if not exists stock integer default 0;
alter table products add column if not exists min_stock integer default 0;
alter table products add column if not exists active boolean default true;
alter table products add column if not exists notes text;
alter table products add column if not exists created_at timestamptz default now();
alter table products add column if not exists brand text;
alter table products add column if not exists format text;

create table if not exists professionals (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  color                 text default '#C5A467'::text,
  active                boolean default true,
  sort_order            integer default 0,
  work_start            time default '09:00:00'::time without time zone,
  work_end              time default '20:00:00'::time without time zone,
  work_days             jsonb default '[1, 2, 3, 4, 5, 6]'::jsonb,
  created_at            timestamptz default now(),
  commission_pct        numeric default 0
);
alter table professionals add column if not exists id uuid default gen_random_uuid();
alter table professionals add column if not exists name text;
alter table professionals add column if not exists color text default '#C5A467'::text;
alter table professionals add column if not exists active boolean default true;
alter table professionals add column if not exists sort_order integer default 0;
alter table professionals add column if not exists work_start time default '09:00:00'::time without time zone;
alter table professionals add column if not exists work_end time default '20:00:00'::time without time zone;
alter table professionals add column if not exists work_days jsonb default '[1, 2, 3, 4, 5, 6]'::jsonb;
alter table professionals add column if not exists created_at timestamptz default now();
alter table professionals add column if not exists commission_pct numeric default 0;

create table if not exists promo_events (
  id                    integer primary key default nextval('promo_events_id_seq'::regclass),
  active                boolean default false not null,
  config                jsonb default '{}'::jsonb not null,
  created_at            timestamptz default now(),
  updated_at            timestamptz default now()
);
alter table promo_events add column if not exists id integer default nextval('promo_events_id_seq'::regclass);
alter table promo_events add column if not exists active boolean default false;
alter table promo_events add column if not exists config jsonb default '{}'::jsonb;
alter table promo_events add column if not exists created_at timestamptz default now();
alter table promo_events add column if not exists updated_at timestamptz default now();

create table if not exists record_templates (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  description           text,
  sections              jsonb default '[]'::jsonb not null,
  services              jsonb default '"all"'::jsonb,
  active                boolean default true,
  sort_order            integer default 0,
  created_at            timestamptz default now()
);
alter table record_templates add column if not exists id uuid default gen_random_uuid();
alter table record_templates add column if not exists name text;
alter table record_templates add column if not exists description text;
alter table record_templates add column if not exists sections jsonb default '[]'::jsonb;
alter table record_templates add column if not exists services jsonb default '"all"'::jsonb;
alter table record_templates add column if not exists active boolean default true;
alter table record_templates add column if not exists sort_order integer default 0;
alter table record_templates add column if not exists created_at timestamptz default now();

create table if not exists sales (
  id                    uuid primary key default gen_random_uuid(),
  client_id             uuid,
  client_name           text,
  sale_date             date default CURRENT_DATE not null,
  items                 jsonb default '[]'::jsonb,
  total                 numeric default 0,
  payment_method        text,
  status                text default 'paid'::text,
  notes                 text,
  created_at            timestamptz default now(),
  professional_id       uuid,
  tip                   numeric default 0,
  appointment_id        uuid
);
alter table sales add column if not exists id uuid default gen_random_uuid();
alter table sales add column if not exists client_id uuid;
alter table sales add column if not exists client_name text;
alter table sales add column if not exists sale_date date default CURRENT_DATE;
alter table sales add column if not exists items jsonb default '[]'::jsonb;
alter table sales add column if not exists total numeric default 0;
alter table sales add column if not exists payment_method text;
alter table sales add column if not exists status text default 'paid'::text;
alter table sales add column if not exists notes text;
alter table sales add column if not exists created_at timestamptz default now();
alter table sales add column if not exists professional_id uuid;
alter table sales add column if not exists tip numeric default 0;
alter table sales add column if not exists appointment_id uuid;

create table if not exists services (
  id                    text primary key,
  cat_id                text,
  name                  text not null,
  tag                   text,
  price                 text not null,
  duration              text,
  short_desc            text,
  long_desc             text,
  img                   text,
  link                  text,
  includes              jsonb default '[]'::jsonb,
  highlighted           boolean default false,
  has_discount          boolean default false,
  discount_label        text,
  original_price        text,
  sort_order            integer default 0,
  created_at            timestamptz default now(),
  process_steps         jsonb default '[]'::jsonb,
  gc_discount_enabled   boolean default false,
  gc_discount_percent   integer default 0,
  gc_price              text default ''::text,
  discount_text         text default ''::text
);
alter table services add column if not exists id text;
alter table services add column if not exists cat_id text;
alter table services add column if not exists name text;
alter table services add column if not exists tag text;
alter table services add column if not exists price text;
alter table services add column if not exists duration text;
alter table services add column if not exists short_desc text;
alter table services add column if not exists long_desc text;
alter table services add column if not exists img text;
alter table services add column if not exists link text;
alter table services add column if not exists includes jsonb default '[]'::jsonb;
alter table services add column if not exists highlighted boolean default false;
alter table services add column if not exists has_discount boolean default false;
alter table services add column if not exists discount_label text;
alter table services add column if not exists original_price text;
alter table services add column if not exists sort_order integer default 0;
alter table services add column if not exists created_at timestamptz default now();
alter table services add column if not exists process_steps jsonb default '[]'::jsonb;
alter table services add column if not exists gc_discount_enabled boolean default false;
alter table services add column if not exists gc_discount_percent integer default 0;
alter table services add column if not exists gc_price text default ''::text;
alter table services add column if not exists discount_text text default ''::text;

create table if not exists stock_movements (
  id                    uuid primary key default gen_random_uuid(),
  product_id            uuid,
  product_name          text,
  type                  text not null,
  qty                   numeric default 0 not null,
  note                  text,
  move_date             date default CURRENT_DATE not null,
  created_at            timestamptz default now()
);
alter table stock_movements add column if not exists id uuid default gen_random_uuid();
alter table stock_movements add column if not exists product_id uuid;
alter table stock_movements add column if not exists product_name text;
alter table stock_movements add column if not exists type text;
alter table stock_movements add column if not exists qty numeric default 0;
alter table stock_movements add column if not exists note text;
alter table stock_movements add column if not exists move_date date default CURRENT_DATE;
alter table stock_movements add column if not exists created_at timestamptz default now();

create table if not exists survey_responses (
  id                    uuid primary key default gen_random_uuid(),
  survey_id             uuid,
  answers               jsonb default '{}'::jsonb,
  service_name          text,
  client_name           text,
  comment               text,
  created_at            timestamptz default now()
);
alter table survey_responses add column if not exists id uuid default gen_random_uuid();
alter table survey_responses add column if not exists survey_id uuid;
alter table survey_responses add column if not exists answers jsonb default '{}'::jsonb;
alter table survey_responses add column if not exists service_name text;
alter table survey_responses add column if not exists client_name text;
alter table survey_responses add column if not exists comment text;
alter table survey_responses add column if not exists created_at timestamptz default now();

create table if not exists surveys (
  id                    uuid primary key default gen_random_uuid(),
  name                  text not null,
  questions             jsonb default '[]'::jsonb,
  services              jsonb default '[]'::jsonb,
  active                boolean default true,
  created_at            timestamptz default now()
);
alter table surveys add column if not exists id uuid default gen_random_uuid();
alter table surveys add column if not exists name text;
alter table surveys add column if not exists questions jsonb default '[]'::jsonb;
alter table surveys add column if not exists services jsonb default '[]'::jsonb;
alter table surveys add column if not exists active boolean default true;
alter table surveys add column if not exists created_at timestamptz default now();


-- ── 2. Índices ──────────────────────────────────────────────────

create index if not exists idx_clients_name ON public.clients USING btree (name);
create index if not exists idx_plans_client ON public.plans USING btree (client_id);
create index if not exists idx_appt_date ON public.appointments USING btree (appt_date);
create index if not exists idx_appt_prof ON public.appointments USING btree (professional_id);
create index if not exists idx_sales_date ON public.sales USING btree (sale_date);
create index if not exists sales_appt_idx ON public.sales USING btree (appointment_id);
create index if not exists client_records_client_idx ON public.client_records USING btree (client_id);
create index if not exists client_consents_client_idx ON public.client_consents USING btree (client_id);
create index if not exists payment_links_status_idx ON public.payment_links USING btree (status);


-- ── 3. Funciones ────────────────────────────────────────────────

-- quién es del equipo: de esto dependen casi todas las reglas
create or replace function is_panel_user() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users where id = auth.uid() and active)
$$;

create or replace function is_panel_admin() returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from panel_users
                  where id = auth.uid() and role in ('admin','admin_local') and active)
$$;

create or replace function my_professional_id() returns uuid
language sql stable security definer set search_path = public as $$
  select professional_id from panel_users where id = auth.uid() and active
$$;

create or replace function public.duracion_min(t text)
 RETURNS integer
 LANGUAGE sql
 IMMUTABLE
AS $function$
  select case
    when t is null then null
    when t ~* 'h' then coalesce((regexp_match(t, '(\d+)\s*h', 'i'))[1]::int, 0) * 60
                     + coalesce((regexp_match(t, '(\d+)\s*min', 'i'))[1]::int, 0)
    when t ~ '\d' then (regexp_match(t, '(\d+)'))[1]::int
    else null end
$function$
;

create or replace function public.admin_exists()
 RETURNS boolean
 LANGUAGE sql
 STABLE SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
  select exists (select 1 from panel_users where role = 'admin' and active)
$function$
;

create or replace function public.reservar_cliente(p_nombre text, p_fono text, p_email text DEFAULT NULL::text)
 RETURNS uuid
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$ declare v_id uuid; v_dig text; begin if coalesce(trim(p_nombre), '') = '' then raise exception 'Falta el nombre'; end if; v_dig := right(regexp_replace(coalesce(p_fono, ''), '[^0-9]', '', 'g'), 8); if length(v_dig) = 8 then select id into v_id from clients where right(regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g'), 8) = v_dig limit 1; end if; if v_id is null then insert into clients (name, phone, email) values (trim(p_nombre), p_fono, nullif(trim(coalesce(p_email, '')), '')) returning id into v_id; end if; return v_id; end $function$
;

create or replace function public.reservar_hora(p_profesional uuid, p_cliente uuid, p_nombre text, p_fono text, p_servicio text, p_fecha date, p_inicio time without time zone, p_fin time without time zone, p_precio text DEFAULT NULL::text, p_nota text DEFAULT NULL::text, p_origen text DEFAULT 'web'::text)
 RETURNS uuid
 LANGUAGE plpgsql
 SECURITY DEFINER
 SET search_path TO 'public'
AS $function$
declare
  v_id uuid; v_max int; v_hoy date; v_ahora time; v_dow int; v_dias jsonb;
  v_ws time; v_we time; v_hpd jsonb; v_dia jsonb; v_b jsonb; v_dur int; v_svc_dur int; v_fono text; v_n int;
begin
  v_hoy   := (now() at time zone 'America/Santiago')::date;
  v_ahora := (now() at time zone 'America/Santiago')::time;
  if p_fecha < v_hoy then raise exception 'No se puede reservar en una fecha pasada'; end if;
  if p_origen <> 'panel' and p_fecha = v_hoy and p_inicio < v_ahora then raise exception 'Esa hora ya paso'; end if;
  if p_fin <= p_inicio then raise exception 'La hora de termino debe ser posterior a la de inicio'; end if;
  if length(trim(coalesce(p_nombre,''))) < 2 then raise exception 'Falta el nombre'; end if;
  if p_origen <> 'panel' then
    select nullif(local_info->>'maxDias','')::int into v_max from bot_config where id = 1;
    v_max := greatest(coalesce(v_max, 14), 1);
    if p_fecha > v_hoy + v_max then
      raise exception 'Por ahora solo se puede reservar hasta % dias por delante. Escribenos si necesitas una fecha mas lejana.', v_max;
    end if;
  end if;
  v_dur := (extract(epoch from (p_fin - p_inicio)) / 60)::int;
  if v_dur < 10 or v_dur > 240 then raise exception 'Duracion no valida'; end if;
  select duracion_min(duration) into v_svc_dur from services where lower(name) = lower(trim(coalesce(p_servicio,''))) limit 1;
  if not found then raise exception 'Ese servicio no existe'; end if;
  if v_svc_dur is not null and v_svc_dur > 0 and v_dur <> v_svc_dur then raise exception 'La duracion no corresponde al servicio'; end if;
  select work_days, work_start, work_end into v_dias, v_ws, v_we from professionals where id = p_profesional and active is distinct from false;
  if not found then raise exception 'Ese profesional no esta disponible'; end if;
  v_dow := extract(dow from p_fecha)::int;
  if v_dias is not null and jsonb_typeof(v_dias) = 'array' and not (v_dias @> to_jsonb(v_dow) or (v_dow = 0 and v_dias @> '7'::jsonb)) then
    raise exception 'El profesional no atiende ese dia';
  end if;
  select prof_meta -> p_profesional::text -> 'horasPorDia' -> v_dow::text into v_hpd from bot_config where id = 1;
  if v_hpd is not null and jsonb_typeof(v_hpd) = 'array' and coalesce(v_hpd->>0,'') <> '' then
    v_ws := (v_hpd->>0)::time;  v_we := (v_hpd->>1)::time;
  end if;
  if v_ws is not null and v_we is not null and (p_inicio < v_ws or p_fin > v_we) then raise exception 'Esa hora esta fuera del horario del profesional'; end if;
  for v_b in select value from jsonb_array_elements(coalesce((select prof_meta -> p_profesional::text -> 'breaks' -> v_dow::text from bot_config where id = 1), '[]'::jsonb)) loop
    if jsonb_typeof(v_b) = 'array' and coalesce(v_b->>0,'') <> '' and p_inicio < (v_b->>1)::time and p_fin > (v_b->>0)::time then
      raise exception 'Esa hora cae en un descanso';
    end if;
  end loop;
  select local_info -> 'hours' -> v_dow::text into v_dia from bot_config where id = 1;
  if v_dia is not null and jsonb_typeof(v_dia) = 'array' then
    if (v_dia -> 2)::text = 'false' then raise exception 'El local esta cerrado ese dia'; end if;
    if coalesce(v_dia->>0,'') <> '' and (p_inicio < (v_dia->>0)::time or p_fin > (v_dia->>1)::time) then raise exception 'Esa hora esta fuera del horario del local'; end if;
  end if;
  v_fono := regexp_replace(coalesce(p_fono,''), '\D', '', 'g');
  if p_origen <> 'panel' and length(v_fono) >= 8 then
    select count(*) into v_n from appointments
      where regexp_replace(coalesce(client_phone,''), '\D', '', 'g') like '%' || right(v_fono, 8)
        and appt_date >= v_hoy and status is distinct from 'cancelled' and origen in ('web','bot');
    if v_n >= 3 then raise exception 'Ya tienes 3 reservas pendientes. Escribenos por WhatsApp para agendar otra.'; end if;
  end if;
  perform pg_advisory_xact_lock(hashtext(p_profesional::text || '|' || p_fecha::text));
  if exists (select 1 from appointments where professional_id = p_profesional and appt_date = p_fecha
              and status is distinct from 'cancelled' and start_time < p_fin and end_time > p_inicio) then
    raise exception 'Esa hora ya esta tomada';
  end if;
  insert into appointments (professional_id, client_id, client_name, client_phone, service_name, appt_date, start_time, end_time, status, price, notes, origen)
  values (p_profesional, p_cliente, trim(p_nombre), p_fono, p_servicio, p_fecha, p_inicio, p_fin, 'reserved', p_precio,
          coalesce(p_nota, 'Reservado desde el sitio web'), case when p_origen in ('web','bot','panel') then p_origen else 'web' end)
  returning id into v_id;
  return v_id;
end $function$
;


-- ── 4. Vistas que lee el sitio público ──────────────────────────
-- No exponen teléfonos, correos ni notas de los clientes.

drop view if exists public_pricing;
create view public_pricing as SELECT smart_pricing,     svc_classes,     svc_packages,     svc_meta,     local_info,     resources,     gc_catalog,     ( SELECT COALESCE(jsonb_object_agg(t.k, COALESCE((t.v -> 'services'::text), '"all"'::jsonb)), '{}'::jsonb) AS "coalesce"            FROM jsonb_each(COALESCE(bot_config.prof_meta, '{}'::jsonb)) t(k, v)) AS prof_services,     ( SELECT COALESCE(jsonb_object_agg(t2.k, jsonb_build_object('publicName', (t2.v -> 'publicName'::text), 'online', COALESCE((t2.v -> 'online'::text), 'true'::jsonb), 'horasPorDia', COALESCE((t2.v -> 'horasPorDia'::text), '{}'::jsonb), 'breaks', COALESCE((t2.v -> 'breaks'::text), '{}'::jsonb))), '{}'::jsonb) AS "coalesce"            FROM jsonb_each(COALESCE(bot_config.prof_meta, '{}'::jsonb)) t2(k, v)) AS prof_publico    FROM bot_config   WHERE (id = 1);
grant select on public_pricing to anon, authenticated;

drop view if exists public_professionals;
create view public_professionals as SELECT id,     name,     color,     work_days,     work_start,     work_end,     sort_order    FROM professionals   WHERE (active IS DISTINCT FROM false);
grant select on public_professionals to anon, authenticated;

drop view if exists public_busy;
create view public_busy as SELECT professional_id,     appt_date,     start_time,     end_time,     service_name,     status    FROM appointments   WHERE ((status IS DISTINCT FROM 'cancelled'::text) AND (appt_date >= (CURRENT_DATE - '1 day'::interval)));
grant select on public_busy to anon, authenticated;


-- ── 5. Reglas de seguridad ──────────────────────────────────────
-- Primero se quitan los permisos de tabla que Supabase da por defecto:
-- son la primera barrera, antes incluso de las reglas por fila.

revoke all on appointments from anon, authenticated;
revoke all on bot_config from anon, authenticated;
revoke all on campaigns from anon, authenticated;
revoke all on cash_movements from anon, authenticated;
revoke all on categories from anon, authenticated;
revoke all on client_consents from anon, authenticated;
revoke all on client_records from anon, authenticated;
revoke all on clients from anon, authenticated;
revoke all on consents from anon, authenticated;
revoke all on email_suppressions from anon, authenticated;
revoke all on gift_card_uses from anon, authenticated;
revoke all on page_content from anon, authenticated;
revoke all on panel_users from anon, authenticated;
revoke all on payment_links from anon, authenticated;
revoke all on plans from anon, authenticated;
revoke all on products from anon, authenticated;
revoke all on professionals from anon, authenticated;
revoke all on promo_events from anon, authenticated;
revoke all on record_templates from anon, authenticated;
revoke all on sales from anon, authenticated;
revoke all on services from anon, authenticated;
revoke all on stock_movements from anon, authenticated;
revoke all on survey_responses from anon, authenticated;
revoke all on surveys from anon, authenticated;

alter table appointments enable row level security;
alter table bot_config enable row level security;
alter table campaigns enable row level security;
alter table cash_movements enable row level security;
alter table categories enable row level security;
alter table client_consents enable row level security;
alter table client_records enable row level security;
alter table clients enable row level security;
alter table consents enable row level security;
alter table email_suppressions enable row level security;
alter table gift_card_uses enable row level security;
alter table page_content enable row level security;
alter table panel_users enable row level security;
alter table payment_links enable row level security;
alter table plans enable row level security;
alter table products enable row level security;
alter table professionals enable row level security;
alter table promo_events enable row level security;
alter table record_templates enable row level security;
alter table sales enable row level security;
alter table services enable row level security;
alter table stock_movements enable row level security;
alter table survey_responses enable row level security;
alter table surveys enable row level security;

-- El equipo (con ficha activa en panel_users) trabaja con estas tablas:
grant select, insert, update, delete on appointments to authenticated;
drop policy if exists "equipo_appointments" on appointments;
create policy "equipo_appointments" on appointments for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on categories to authenticated;
drop policy if exists "equipo_categories" on categories;
create policy "equipo_categories" on categories for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on client_consents to authenticated;
drop policy if exists "equipo_client_consents" on client_consents;
create policy "equipo_client_consents" on client_consents for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on client_records to authenticated;
drop policy if exists "equipo_client_records" on client_records;
create policy "equipo_client_records" on client_records for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on clients to authenticated;
drop policy if exists "equipo_clients" on clients;
create policy "equipo_clients" on clients for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on consents to authenticated;
drop policy if exists "equipo_consents" on consents;
create policy "equipo_consents" on consents for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on gift_card_uses to authenticated;
drop policy if exists "equipo_gift_card_uses" on gift_card_uses;
create policy "equipo_gift_card_uses" on gift_card_uses for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on payment_links to authenticated;
drop policy if exists "equipo_payment_links" on payment_links;
create policy "equipo_payment_links" on payment_links for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on plans to authenticated;
drop policy if exists "equipo_plans" on plans;
create policy "equipo_plans" on plans for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on products to authenticated;
drop policy if exists "equipo_products" on products;
create policy "equipo_products" on products for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on professionals to authenticated;
drop policy if exists "equipo_professionals" on professionals;
create policy "equipo_professionals" on professionals for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on record_templates to authenticated;
drop policy if exists "equipo_record_templates" on record_templates;
create policy "equipo_record_templates" on record_templates for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on sales to authenticated;
drop policy if exists "equipo_sales" on sales;
create policy "equipo_sales" on sales for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on services to authenticated;
drop policy if exists "equipo_services" on services;
create policy "equipo_services" on services for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on stock_movements to authenticated;
drop policy if exists "equipo_stock_movements" on stock_movements;
create policy "equipo_stock_movements" on stock_movements for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on survey_responses to authenticated;
drop policy if exists "equipo_survey_responses" on survey_responses;
create policy "equipo_survey_responses" on survey_responses for all to authenticated using (is_panel_user()) with check (is_panel_user());
grant select, insert, update, delete on surveys to authenticated;
drop policy if exists "equipo_surveys" on surveys;
create policy "equipo_surveys" on surveys for all to authenticated using (is_panel_user()) with check (is_panel_user());

-- Caja, configuración, usuarios y campañas: solo administradores.
grant select, insert, update, delete on panel_users to authenticated;
drop policy if exists "admin_panel_users" on panel_users;
create policy "admin_panel_users" on panel_users for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
grant select, insert, update, delete on bot_config to authenticated;
drop policy if exists "admin_bot_config" on bot_config;
create policy "admin_bot_config" on bot_config for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
grant select, insert, update, delete on cash_movements to authenticated;
drop policy if exists "admin_cash_movements" on cash_movements;
create policy "admin_cash_movements" on cash_movements for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
grant select, insert, update, delete on campaigns to authenticated;
drop policy if exists "admin_campaigns" on campaigns;
create policy "admin_campaigns" on campaigns for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
grant select, insert, update, delete on email_suppressions to authenticated;
drop policy if exists "admin_email_suppressions" on email_suppressions;
create policy "admin_email_suppressions" on email_suppressions for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
grant select, insert, update, delete on promo_events to authenticated;
drop policy if exists "admin_promo_events" on promo_events;
create policy "admin_promo_events" on promo_events for all to authenticated using (is_panel_admin()) with check (is_panel_admin());
grant select, insert, update, delete on page_content to authenticated;
drop policy if exists "admin_page_content" on page_content;
create policy "admin_page_content" on page_content for all to authenticated using (is_panel_admin()) with check (is_panel_admin());

-- Cada persona lee su propia ficha de usuario: sin esto no podría
-- entrar al panel (necesita saber su rol antes de que nada funcione).
drop policy if exists "mi_ficha_panel_users" on panel_users;
create policy "mi_ficha_panel_users" on panel_users for select to authenticated using (id = auth.uid());

-- La configuración del negocio la leen todos los del equipo (horarios,
-- reglas de la agenda); cambiarla sigue siendo cosa del administrador.
drop policy if exists "equipo_lee_bot_config" on bot_config;
create policy "equipo_lee_bot_config" on bot_config for select to authenticated using (is_panel_user());


-- El sitio público: solo la encuesta y las funciones de reservar.
grant select on surveys to anon;
drop policy if exists "publico_lee_surveys" on surveys;
create policy "publico_lee_surveys" on surveys for select to anon using (true);
grant insert on survey_responses to anon;
drop policy if exists "publico_responde_survey_responses" on survey_responses;
create policy "publico_responde_survey_responses" on survey_responses for insert to anon with check (true);

grant execute on function admin_exists() to anon, authenticated;
grant execute on function reservar_cliente(text,text,text) to anon, authenticated;
grant execute on function reservar_hora(uuid,uuid,text,text,text,date,time,time,text,text,text) to anon, authenticated;


-- ── 6. Carpetas de archivos ─────────────────────────────────────
-- 'fichas' guarda fotos clínicas y 'consentimientos' los PDF firmados.
-- Se leen por enlace directo (el enlace es impredecible) pero solo el
-- equipo puede subir o borrar.
insert into storage.buckets (id, name, public) values ('fichas','fichas', true) on conflict (id) do nothing;
insert into storage.buckets (id, name, public) values ('consentimientos','consentimientos', true) on conflict (id) do nothing;

drop policy if exists "fichas_lee" on storage.objects;
drop policy if exists "fichas_sube" on storage.objects;
drop policy if exists "fichas_borra" on storage.objects;
create policy "fichas_lee"  on storage.objects for select using (bucket_id = 'fichas');
create policy "fichas_sube" on storage.objects for insert to authenticated with check (bucket_id = 'fichas' and is_panel_user());
create policy "fichas_borra" on storage.objects for delete to authenticated using (bucket_id = 'fichas' and is_panel_user());

drop policy if exists "consent_lee" on storage.objects;
drop policy if exists "consent_sube" on storage.objects;
drop policy if exists "consent_borra" on storage.objects;
create policy "consent_lee"  on storage.objects for select using (bucket_id = 'consentimientos');
create policy "consent_sube" on storage.objects for insert to authenticated with check (bucket_id = 'consentimientos' and is_panel_user());
create policy "consent_borra" on storage.objects for delete to authenticated using (bucket_id = 'consentimientos' and is_panel_user());


-- ── 7. Fila única de configuración ──────────────────────────────
insert into bot_config (id) values (1) on conflict (id) do nothing;


-- ── Comprobación ────────────────────────────────────────────────
select 'tablas'     as que, count(*)::text as cuantas from information_schema.tables where table_schema='public' and table_type='BASE TABLE'
union all select 'funciones', count(*)::text from pg_proc p join pg_namespace n on n.oid=p.pronamespace where n.nspname='public'
union all select 'vistas',    count(*)::text from pg_views where schemaname='public'
union all select 'politicas', count(*)::text from pg_policies where schemaname in ('public','storage')
union all select 'tablas sin proteger', count(*)::text from pg_class where relnamespace='public'::regnamespace and relkind='r' and not relrowsecurity;
