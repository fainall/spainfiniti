-- ════════════════════════════════════════════════════════════════
-- Documentos de la clienta (pedido de Luis, 17-nov-2026)
--
-- Una pestaña "Documentos" en la ficha de la clienta, como la de las
-- profesionales: PDF e imágenes (exámenes, resultados de toma de muestra,
-- órdenes médicas, carnet…). Los archivos van en la carpeta privada
-- "documentos-clientes"; el panel los abre con enlaces firmados de una hora.
-- Los ve y sube el equipo del panel (como las fichas), nunca el perfil de
-- publicidad ni el sitio web.
-- ════════════════════════════════════════════════════════════════

create table if not exists public.client_documents (
  id          uuid primary key default gen_random_uuid(),
  client_id   uuid not null references public.clients(id) on delete cascade,
  nombre      text not null check (char_length(nombre) <= 300),
  url         text not null,
  tipo        text,
  tamano      integer,
  created_at  timestamptz not null default now(),
  created_by  uuid default auth.uid()
);
create index if not exists client_documents_cliente_idx on public.client_documents (client_id);

alter table public.client_documents enable row level security;
drop policy if exists docs_cliente_lee on public.client_documents;
create policy docs_cliente_lee on public.client_documents for select to authenticated using (public.is_panel_datos());
drop policy if exists docs_cliente_sube on public.client_documents;
create policy docs_cliente_sube on public.client_documents for insert to authenticated with check (public.is_panel_datos());
drop policy if exists docs_cliente_borra on public.client_documents;
create policy docs_cliente_borra on public.client_documents for delete to authenticated using (public.is_panel_datos());
revoke all on public.client_documents from anon;
grant select, insert, delete on public.client_documents to authenticated;

-- la carpeta privada (15 MB por archivo; el panel solo deja subir PDF e imágenes)
insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types) values
  ('documentos-clientes', 'documentos-clientes', false, 15728640, null)
on conflict (id) do nothing;

drop policy if exists docs_clientes_lee on storage.objects;
create policy docs_clientes_lee on storage.objects for select to authenticated
  using (bucket_id = 'documentos-clientes' and public.is_panel_datos());
drop policy if exists docs_clientes_sube on storage.objects;
create policy docs_clientes_sube on storage.objects for insert to authenticated
  with check (bucket_id = 'documentos-clientes' and public.is_panel_datos());
drop policy if exists docs_clientes_borra on storage.objects;
create policy docs_clientes_borra on storage.objects for delete to authenticated
  using (bucket_id = 'documentos-clientes' and public.is_panel_datos());

notify pgrst, 'reload schema';
