-- ════════════════════════════════════════════════════════════════
--  Fotos clínicas privadas
--  Las carpetas 'fichas' (fotos de pacientes) y 'consentimientos'
--  (documentos firmados) se podían abrir sin iniciar sesión, con solo
--  tener el enlace. Desde aquí solo el equipo del panel puede verlas:
--  el panel pide enlaces firmados que vencen en una hora.
--  Se agrega además permiso para reemplazar archivos, que usa la
--  restauración de copias de seguridad.
-- ════════════════════════════════════════════════════════════════

update storage.buckets set public = false where id in ('fichas', 'consentimientos');

drop policy if exists "fichas_lee" on storage.objects;
create policy "fichas_lee" on storage.objects for select to authenticated
  using (bucket_id = 'fichas' and is_panel_user());

drop policy if exists "consent_lee" on storage.objects;
create policy "consent_lee" on storage.objects for select to authenticated
  using (bucket_id = 'consentimientos' and is_panel_user());

drop policy if exists "fichas_actualiza" on storage.objects;
create policy "fichas_actualiza" on storage.objects for update to authenticated
  using (bucket_id = 'fichas' and is_panel_user())
  with check (bucket_id = 'fichas' and is_panel_user());

drop policy if exists "consent_actualiza" on storage.objects;
create policy "consent_actualiza" on storage.objects for update to authenticated
  using (bucket_id = 'consentimientos' and is_panel_user())
  with check (bucket_id = 'consentimientos' and is_panel_user());

select id, public from storage.buckets where id in ('fichas', 'consentimientos');
