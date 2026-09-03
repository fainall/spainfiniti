-- ════════════════════════════════════════════════════════════════
-- La asistente de WhatsApp: nombre propio y datos practicos.
--
-- Antes se presentaba como "el asistente de reservas" y no tenia como
-- responder lo que mas preguntan antes de venir (estacionamiento, metro,
-- como entrar al edificio). Se agregan dos campos que se editan en el
-- panel, en Asistente.
-- ════════════════════════════════════════════════════════════════

alter table bot_config add column if not exists bot_name   text;
alter table bot_config add column if not exists local_tips text;

-- Valores de partida: el nombre y los saludos que pidio el negocio.
-- Todo esto se edita despues desde el panel.
update bot_config set
  bot_name = coalesce(nullif(trim(bot_name), ''), 'Julia'),
  welcome  = coalesce(nullif(trim(welcome), ''),
    'Hola, soy Julia, asistente de Spa Infinity. ¿En qué puedo ayudarte?' || chr(10) ||
    'Hola 👋 Soy Julia, de Spa Infinity. ¿Te ayudo a agendar tu hora?' || chr(10) ||
    'Hola, te habla Julia de Spa Infinity. Cuéntame qué necesitas y lo vemos.' || chr(10) ||
    'Hola, soy Julia del equipo de Spa Infinity. ¿Qué tratamiento te interesa?'),
  tone     = coalesce(nullif(trim(tone), ''),
    'Cercana, natural y profesional, como una recepcionista con experiencia. ' ||
    'Frases cortas y claras, de tú. Nunca suenes a formulario ni repitas la misma frase. ' ||
    'Confirma siempre servicio, día y hora antes de agendar.')
where id = 1;

select bot_name, left(welcome, 60) as primer_saludo,
       array_length(string_to_array(welcome, chr(10)), 1) as saludos,
       coalesce(local_tips, '(por completar)') as datos_practicos
from bot_config where id = 1;
