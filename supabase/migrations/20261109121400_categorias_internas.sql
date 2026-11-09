-- Categorias internas (pedido de Luis, 9-nov-2026).
-- Una categoria marcada como interna es solo para el equipo: sus servicios se
-- agendan y se cobran en el panel, pero el sitio web no la muestra y Mariet
-- (api/bot-reply.php) no conoce sus servicios.
-- La marca se cambia en la agenda: Administración → Servicios → ✎ Editar categoría.

alter table public.categories add column if not exists interna boolean not null default false;
