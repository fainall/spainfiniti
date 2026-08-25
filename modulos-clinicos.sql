-- ═══════════════════════════════════════════════════════════════
--  MÓDULOS CLÍNICOS Y DE COBRO
--  · Fichas médicas (plantillas + fichas rellenadas por cliente)
--  · Consentimientos (documento + firma del cliente)
--  · Cobros (links de pago enviados por WhatsApp o correo)
--  · sales.appointment_id, para saber qué reserva quedó pagada
--
--  Las fichas médicas guardan datos de salud: estas tablas NO se
--  abren al público. Solo las lee y escribe alguien con sesión
--  iniciada en el panel.
--
--  Se ejecuta en Supabase → SQL Editor. Es seguro repetirla.
-- ═══════════════════════════════════════════════════════════════

-- ── 1. saber qué reserva pagó cada venta ─────────────────────────
alter table sales add column if not exists appointment_id uuid references appointments(id) on delete set null;
create index if not exists sales_appt_idx on sales(appointment_id);

-- ── 2. fichas médicas ────────────────────────────────────────────
-- Plantilla: define qué preguntas tiene la ficha.
-- sections = [{ title, fields:[{ label, type, options[], required }] }]
create table if not exists record_templates (
  id          uuid primary key default gen_random_uuid(),
  name        text not null,
  description text,
  sections    jsonb not null default '[]'::jsonb,
  services    jsonb default '"all"'::jsonb,   -- a qué servicios aplica
  active      boolean default true,
  sort_order  int default 0,
  created_at  timestamptz default now()
);

-- Ficha rellenada: una por cliente (y opcionalmente por visita).
create table if not exists client_records (
  id              uuid primary key default gen_random_uuid(),
  client_id       uuid references clients(id) on delete cascade,
  template_id     uuid references record_templates(id) on delete set null,
  appointment_id  uuid references appointments(id) on delete set null,
  professional_id uuid references professionals(id) on delete set null,
  template_name   text,
  data            jsonb not null default '{}'::jsonb,
  created_at      timestamptz default now(),
  updated_at      timestamptz default now()
);
create index if not exists client_records_client_idx on client_records(client_id);

-- ── 3. consentimientos ───────────────────────────────────────────
create table if not exists consents (
  id         uuid primary key default gen_random_uuid(),
  name       text not null,
  body       text,        -- texto del consentimiento
  file_url   text,        -- o un PDF subido
  active     boolean default true,
  created_at timestamptz default now()
);

create table if not exists client_consents (
  id           uuid primary key default gen_random_uuid(),
  client_id    uuid references clients(id) on delete cascade,
  consent_id   uuid references consents(id) on delete set null,
  consent_name text,
  signature    text,      -- firma dibujada, en base64
  signed_by    text,      -- nombre de quien firma
  signed_at    timestamptz default now()
);
create index if not exists client_consents_client_idx on client_consents(client_id);

-- ── 4. cobros ────────────────────────────────────────────────────
create table if not exists payment_links (
  id             uuid primary key default gen_random_uuid(),
  appointment_id uuid references appointments(id) on delete set null,
  client_id      uuid references clients(id) on delete set null,
  client_name    text,
  service_name   text,
  amount         int not null default 0,
  channel        text,                      -- whatsapp | email | link
  url            text,
  status         text default 'sent',       -- sent | paid | cancelled
  created_at     timestamptz default now(),
  paid_at        timestamptz
);
create index if not exists payment_links_status_idx on payment_links(status);

-- ── 5. permisos: solo con sesión iniciada ────────────────────────
do $$
declare t text;
begin
  foreach t in array array['record_templates','client_records','consents','client_consents','payment_links'] loop
    execute format('alter table %I enable row level security', t);
    execute format('drop policy if exists "panel_lee_%s" on %I', t, t);
    execute format('drop policy if exists "panel_escribe_%s" on %I', t, t);
    execute format('create policy "panel_lee_%s" on %I for select to authenticated using (true)', t, t);
    execute format('create policy "panel_escribe_%s" on %I for all to authenticated using (true) with check (true)', t, t);
  end loop;
end $$;

-- ── 6. carpeta para los PDF de consentimientos ───────────────────
insert into storage.buckets (id, name, public)
values ('consentimientos', 'consentimientos', true)
on conflict (id) do nothing;

drop policy if exists "consent_lee" on storage.objects;
drop policy if exists "consent_sube" on storage.objects;
create policy "consent_lee"  on storage.objects for select using (bucket_id = 'consentimientos');
create policy "consent_sube" on storage.objects for insert to authenticated with check (bucket_id = 'consentimientos');

-- Comprobación:
--   select count(*) from record_templates;
--   select column_name from information_schema.columns where table_name='sales';
