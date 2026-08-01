-- ═══════════════════════════════════════════
-- SPA INFINITY — Sistema de Agenda (Fase 1)
-- Pegar TODO esto en Supabase → SQL Editor → Run
-- (Se puede correr varias veces sin problema)
-- ═══════════════════════════════════════════

-- 1. Profesionales
create table if not exists professionals (
  id          uuid primary key default gen_random_uuid(),
  name        text not null,
  color       text default '#C5A467',
  active      boolean default true,
  sort_order  int default 0,
  work_start  time default '09:00',
  work_end    time default '20:00',
  work_days   jsonb default '[1,2,3,4,5,6]',  -- 1=Lun ... 7=Dom
  commission_pct numeric default 0,
  created_at  timestamptz default now()
);
alter table professionals add column if not exists commission_pct numeric default 0;

-- 2. Clientes
create table if not exists clients (
  id          uuid primary key default gen_random_uuid(),
  name        text not null,
  phone       text,
  email       text,
  rut         text,
  gender      text,
  birthday    date,
  notes       text,
  created_at  timestamptz default now()
);
create index if not exists idx_clients_name on clients(name);
alter table clients add column if not exists gender text;
alter table clients add column if not exists birthday date;

-- 3. Reservas / citas
create table if not exists appointments (
  id              uuid primary key default gen_random_uuid(),
  professional_id uuid references professionals(id) on delete cascade,
  client_id       uuid references clients(id) on delete set null,
  service_id      text,
  service_name    text,
  client_name     text not null,
  client_phone    text,
  appt_date       date not null,
  start_time      time not null,
  end_time        time not null,
  status          text default 'active',   -- active | done | cancelled | no_show | block
  price           text,
  notes           text,
  created_at      timestamptz default now()
);
create index if not exists idx_appt_date on appointments(appt_date);
create index if not exists idx_appt_prof on appointments(professional_id);

-- Si ya habías creado appointments antes, agrega la columna nueva:
alter table appointments add column if not exists client_id uuid references clients(id) on delete set null;

-- 3b. Ventas (caja)
create table if not exists sales (
  id              uuid primary key default gen_random_uuid(),
  client_id       uuid references clients(id) on delete set null,
  client_name     text,
  professional_id uuid references professionals(id) on delete set null,
  sale_date       date not null default current_date,
  items           jsonb default '[]',
  total           numeric default 0,
  tip             numeric default 0,
  payment_method  text,
  status          text default 'paid',   -- paid | partial | deleted
  notes           text,
  created_at      timestamptz default now()
);
create index if not exists idx_sales_date on sales(sale_date);
alter table sales add column if not exists professional_id uuid references professionals(id) on delete set null;
alter table sales add column if not exists tip numeric default 0;

-- 3c. Planes / Bonos (packs de sesiones prepagadas)
create table if not exists plans (
  id             uuid primary key default gen_random_uuid(),
  client_id      uuid references clients(id) on delete set null,
  client_name    text,
  name           text not null,
  service_id     text,
  service_name   text,
  total_sessions int default 1,
  used_sessions  int default 0,
  price          numeric default 0,
  purchase_date  date default current_date,
  expiry_date    date,
  status         text default 'active',   -- active | cancelled
  notes          text,
  created_at     timestamptz default now()
);
create index if not exists idx_plans_client on plans(client_id);

-- 3d. Productos (inventario)
create table if not exists products (
  id          uuid primary key default gen_random_uuid(),
  name        text not null,
  sku         text,
  category    text,
  price       numeric default 0,
  cost        numeric default 0,
  stock       int default 0,
  min_stock   int default 0,
  active      boolean default true,
  notes       text,
  created_at  timestamptz default now()
);

-- 4. Permisos (mismo patrón del resto del sitio)
alter table professionals enable row level security;
alter table clients       enable row level security;
alter table appointments  enable row level security;
alter table sales         enable row level security;
alter table plans         enable row level security;
alter table products      enable row level security;

drop policy if exists "anon all professionals" on professionals;
drop policy if exists "anon all clients"        on clients;
drop policy if exists "anon all appointments"   on appointments;
drop policy if exists "anon all sales"          on sales;
drop policy if exists "anon all plans"          on plans;
drop policy if exists "anon all products"       on products;
create policy "anon all professionals" on professionals for all using (true) with check (true);
create policy "anon all clients"        on clients       for all using (true) with check (true);
create policy "anon all appointments"   on appointments  for all using (true) with check (true);
create policy "anon all sales"          on sales         for all using (true) with check (true);
create policy "anon all plans"          on plans         for all using (true) with check (true);
create policy "anon all products"       on products      for all using (true) with check (true);

-- 5. Un profesional de ejemplo para empezar
insert into professionals (name, color, work_start, work_end, work_days)
select 'Profesional 1', '#C5A467', '10:00', '20:00', '[1,2,3,4,5,6]'
where not exists (select 1 from professionals);
