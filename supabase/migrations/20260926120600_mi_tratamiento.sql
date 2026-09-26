-- ════════════════════════════════════════════════════════════════
-- Cuentas de clientes · 06 · Mi tratamiento
--
-- El cliente ve en su cuenta las fotos de antes y después que el EQUIPO
-- decide compartir (una casilla por comparación en la ficha) y el avance
-- de sus bonos o planes. Nada más de la ficha clínica: ni las notas
-- internas, ni los consentimientos, ni las fichas de sesión.
--
-- Las fotos siguen en la carpeta privada "fichas". El cliente recibe
-- enlaces firmados de una hora, y la carpeta solo se los firma para las
-- fotos compartidas de sus propias fichas.
-- ════════════════════════════════════════════════════════════════

alter table public.client_comparisons
  add column if not exists compartir boolean not null default false,
  add column if not exists nota_cliente text check (nota_cliente is null or char_length(nota_cliente) <= 600);
comment on column public.client_comparisons.compartir is 'true = el cliente ve esta comparación en su cuenta del sitio';
comment on column public.client_comparisons.nota_cliente is 'Mensaje para el cliente (lo ve en su cuenta). Las notas internas van en "notas".';

-- ruta dentro de la carpeta a partir de lo que guarda el panel
-- ("…/object/public/fichas/<ruta>")
create or replace function public.ruta_ficha(p_url text)
returns text language sql immutable set search_path = '' as $$
  select nullif(split_part(coalesce(p_url, ''), '/object/public/fichas/', 2), '')
$$;

-- ¿esta foto de la carpeta es de una comparación compartida con quien la pide?
create or replace function public.foto_compartida_conmigo(p_ruta text)
returns boolean language sql stable security definer set search_path = '' as $$
  select auth.uid() is not null and exists (
    select 1
      from public.client_comparisons cc
      join public.clients c on c.id = cc.client_id
     where cc.compartir
       and c.user_id = auth.uid()
       and p_ruta in (public.ruta_ficha(cc.antes_url), public.ruta_ficha(cc.despues_url)))
$$;
revoke all on function public.foto_compartida_conmigo(text) from public, anon;
grant execute on function public.foto_compartida_conmigo(text) to authenticated;

drop policy if exists fichas_cliente_lee on storage.objects;
create policy fichas_cliente_lee on storage.objects for select to authenticated
  using (bucket_id = 'fichas' and public.foto_compartida_conmigo(name));

-- las comparaciones compartidas con el cliente (sin las notas internas)
create or replace function public.mis_comparaciones()
returns table (id uuid, titulo text, antes text, antes_fecha date, despues text, despues_fecha date,
               nota text, actualizada timestamptz)
language sql stable security definer set search_path = '' as $$
  select cc.id, nullif(trim(cc.titulo), ''), public.ruta_ficha(cc.antes_url), cc.antes_fecha,
         public.ruta_ficha(cc.despues_url), cc.despues_fecha, nullif(trim(cc.nota_cliente), ''), cc.created_at
    from public.client_comparisons cc
    join public.clients c on c.id = cc.client_id
   where auth.uid() is not null
     and c.user_id = auth.uid()
     and cc.compartir
     and (cc.antes_url is not null or cc.despues_url is not null)
   order by coalesce(cc.despues_fecha, cc.antes_fecha) desc nulls last, cc.created_at desc
$$;
revoke all on function public.mis_comparaciones() from public, anon;
grant execute on function public.mis_comparaciones() to authenticated;

-- sus bonos y planes con el avance de sesiones
create or replace function public.mis_planes()
returns table (id uuid, nombre text, servicio text, usadas int, total int, compra date, vence date, estado text)
language sql stable security definer set search_path = '' as $$
  select p.id, p.name, p.service_name, coalesce(p.used_sessions, 0), coalesce(p.total_sessions, 0),
         p.purchase_date, p.expiry_date, coalesce(p.status, 'active')
    from public.plans p
    join public.clients c on c.id = p.client_id
   where auth.uid() is not null
     and c.user_id = auth.uid()
     and p.status is distinct from 'cancelled'
   order by (coalesce(p.used_sessions, 0) >= coalesce(p.total_sessions, 0)), p.purchase_date desc nulls last
$$;
revoke all on function public.mis_planes() from public, anon;
grant execute on function public.mis_planes() to authenticated;
