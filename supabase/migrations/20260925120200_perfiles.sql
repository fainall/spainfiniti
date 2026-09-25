-- ════════════════════════════════════════════════════════════════
-- Cuentas de clientes · 02 · Perfiles
--
-- Cada cuenta de cliente (Supabase Auth, correo y contraseña) tiene un
-- perfil con sus datos. El perfil lo crea la base al registrarse, con
-- lo que la persona escribió en el formulario; después solo el dueño lo
-- edita. El correo vive en auth.users (Supabase lo verifica), no aquí.
-- ════════════════════════════════════════════════════════════════

-- RUT chileno válido: "12345678-5" (sin puntos, con guion y dígito verificador)
create or replace function public.rut_valido(p_rut text)
returns boolean
language plpgsql immutable set search_path = '' as $$
declare
  v_num text; v_dv text; v_suma int := 0; v_mul int := 2; v_res int; v_esperado text;
begin
  if p_rut is null or p_rut !~ '^[0-9]{7,8}-[0-9Kk]$' then return false; end if;
  v_num := split_part(p_rut, '-', 1);
  v_dv  := upper(split_part(p_rut, '-', 2));
  for i in reverse length(v_num)..1 loop
    v_suma := v_suma + substr(v_num, i, 1)::int * v_mul;
    v_mul  := case when v_mul = 7 then 2 else v_mul + 1 end;
  end loop;
  v_res := 11 - (v_suma % 11);
  v_esperado := case v_res when 11 then '0' when 10 then 'K' else v_res::text end;
  return v_dv = v_esperado;
end $$;

create table if not exists public.profiles (
  id          uuid primary key references auth.users(id) on delete cascade,
  nombre      text not null default '' check (char_length(nombre) <= 80),
  apellido    text not null default '' check (char_length(apellido) <= 80),
  -- formato internacional: +56912345678 (el sitio lo normaliza antes de guardar)
  telefono    text check (telefono is null or telefono ~ '^\+[0-9]{8,15}$'),
  rut         text check (rut is null or public.rut_valido(rut)),
  created_at  timestamptz not null default now(),
  updated_at  timestamptz not null default now()
);
comment on table public.profiles is 'Perfil de cada cuenta de cliente del sitio (1 a 1 con auth.users).';

alter table public.profiles enable row level security;

-- el dueño lee y edita su perfil; el equipo del panel lo puede leer
drop policy if exists perfil_lee on public.profiles;
create policy perfil_lee on public.profiles for select to authenticated
  using (id = auth.uid() or public.is_panel_user());
drop policy if exists perfil_edita on public.profiles;
create policy perfil_edita on public.profiles for update to authenticated
  using (id = auth.uid()) with check (id = auth.uid());
-- el administrador puede corregir un perfil
drop policy if exists perfil_admin on public.profiles;
create policy perfil_admin on public.profiles for all to authenticated
  using (public.is_panel_admin()) with check (public.is_panel_admin());
-- sin política de insert ni delete para clientes: el perfil lo crea el
-- trigger y se borra junto con la cuenta

revoke all on public.profiles from anon;
grant select, update on public.profiles to authenticated;

-- fecha de la última edición
create or replace function public.perfil_tocado()
returns trigger language plpgsql set search_path = '' as $$
begin
  new.updated_at := now();
  new.id := old.id;            -- el id nunca cambia
  new.created_at := old.created_at;
  return new;
end $$;
drop trigger if exists perfil_tocado on public.profiles;
create trigger perfil_tocado before update on public.profiles
  for each row execute function public.perfil_tocado();

-- al registrarse: el perfil con lo que venía en el formulario. Si algún
-- dato no cumple el formato, queda vacío en vez de impedir el registro.
create or replace function public.crear_perfil()
returns trigger language plpgsql security definer set search_path = '' as $$
declare
  m jsonb := coalesce(new.raw_user_meta_data, '{}'::jsonb);
  v_tel text := nullif(regexp_replace(coalesce(m->>'telefono', ''), '[^0-9+]', '', 'g'), '');
  v_rut text := nullif(upper(replace(coalesce(m->>'rut', ''), '.', '')), '');
begin
  if v_tel is not null and v_tel !~ '^\+[0-9]{8,15}$' then v_tel := null; end if;
  if v_rut is not null and not public.rut_valido(v_rut) then v_rut := null; end if;
  insert into public.profiles (id, nombre, apellido, telefono, rut)
  values (new.id,
          left(trim(coalesce(m->>'nombre', '')), 80),
          left(trim(coalesce(m->>'apellido', '')), 80),
          v_tel, v_rut)
  on conflict (id) do nothing;
  return new;
end $$;
drop trigger if exists crear_perfil on auth.users;
create trigger crear_perfil after insert on auth.users
  for each row execute function public.crear_perfil();
