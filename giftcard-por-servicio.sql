-- ════════════════════════════════════════════════════════════════
-- Los canjes de gift card pasan a ser por servicio.
--
-- Una gift card de Spa Infinity no es un saldo en plata: es un vale por
-- uno o varios servicios. Si se compro una podologia, se canjea por esa
-- podologia y queda usada. Cada canje anota ahora que servicio se uso y
-- desde que venta, para saber que le queda a cada tarjeta.
--
-- amount se conserva: guarda el valor del servicio canjeado, que es lo
-- que suma la columna "Monto canjeado" en la pantalla de Gift Cards.
-- ════════════════════════════════════════════════════════════════

alter table gift_card_uses add column if not exists service_name text;
alter table gift_card_uses add column if not exists sale_id uuid;

notify pgrst, 'reload schema';

-- Comprobacion: deben aparecer las dos columnas nuevas
select column_name, data_type
  from information_schema.columns
 where table_name = 'gift_card_uses'
 order by ordinal_position;
