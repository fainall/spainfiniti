-- ═══════════════════════════════════════════
-- SPA INFINITY — Sistema de Agenda (Fase 1)
-- Pegar TODO esto en Supabase → SQL Editor → Run
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
  created_at  timestamptz default now()
);

-- 2. Reservas / citas
create table if not exists appointments (
  id              uuid primary key default gen_random_uuid(),
  professional_id uuid references professionals(id) on delete cascade,
  service_id      text,
  service_name    text,
  client_name     text not null,
  client_phone    text,
  appt_date       date not null,
  start_time      time not null,
  end_time        time not null,
  status          text default 'active',   -- active | done | cancelled | no_show
  price           text,
  notes           text,
  created_at      timestamptz default now()
);
create index if not exists idx_appt_date on appointments(appt_date);
create index if not exists idx_appt_prof on appointments(professional_id);

-- 3. Permisos (igual patrón que el resto del sitio: acceso con clave anon)
alter table professionals enable row level security;
alter table appointments  enable row level security;

drop policy if exists "anon all professionals" on professionals;
drop policy if exists "anon all appointments"  on appointments;
create policy "anon all professionals" on professionals for all using (true) with check (true);
create policy "anon all appointments"  on appointments  for all using (true) with check (true);

-- 4. Un profesional de ejemplo para empezar (puedes editarlo/borrarlo en la agenda)
insert into professionals (name, color, work_start, work_end, work_days)
values ('Profesional 1', '#C5A467', '10:00', '20:00', '[1,2,3,4,5,6]');
