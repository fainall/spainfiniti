/* ═══════════════════════════════════════════════════════════════
   IDENTIDAD DEL CLIENTE
   ═══════════════════════════════════════════════════════════════
   Este es el único archivo que cambia al montar el sistema para otro
   negocio. Todo lo demás (panel, sitio, agenda) es igual para todos.

   Para un cliente nuevo:
     1. Crear su proyecto en supabase.com y ejecutar instalar.sql.
     2. Settings → API: copiar "Project URL" y la llave "anon" aquí.
     3. Cambiar nombre, teléfono, correo y dominio.
     4. Copiar api/cliente-config.example.php a api/cliente-config.php
        con los mismos valores, para la parte del servidor.

   La llave anónima NO es secreta: va en el navegador y por eso las
   reglas de seguridad de la base son las que protegen los datos.
   La llave de servicio (service_role) NUNCA se escribe aquí: vive
   solo en el servidor, en api/bot-config.php.
   ═══════════════════════════════════════════════════════════════ */

const CLIENTE = {

  /* ── Base de datos (Supabase) ── */
  supabaseUrl: 'https://bxwamppamqxtncvfdycy.supabase.co',
  supabaseKey: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA',

  /* ── El negocio ── */
  nombre:   'Spa Infinity',
  rubro:    'Centro Podológico & Spa',
  dominio:  'https://spainfinity.cl',

  /* ── Contacto ── */
  telefono:  '+56 9 8668 8771',
  whatsapp:  '56986688771',        // solo dígitos, para los enlaces wa.me
  correo:    'reservainfinity@spainfinity.cl',
  direccion: 'Santo Domingo 1083, of. 502, Santiago',

  /* ── Asistente de WhatsApp ── */
  asistente: 'Julia'
}

/* Enlace de WhatsApp con un texto ya escrito */
function waLink(texto){
  return 'https://wa.me/' + CLIENTE.whatsapp +
         (texto ? '?text=' + encodeURIComponent(texto) : '')
}


/* Adelanta el saludo al servidor de la base para que la primera consulta
   llegue antes. Se hace desde aqui, y no con una etiqueta escrita en cada
   pagina, para que apunte a la base de este cliente y no a otra. */
if (typeof document !== 'undefined' && CLIENTE.supabaseUrl) {
  const _pre = document.createElement('link')
  _pre.rel = 'preconnect'
  _pre.href = CLIENTE.supabaseUrl
  _pre.crossOrigin = ''
  ;(document.head || document.documentElement).appendChild(_pre)
}

/* Para que también funcione si algún día esto se carga como módulo */
if (typeof module !== 'undefined' && module.exports) module.exports = { CLIENTE, waLink }
