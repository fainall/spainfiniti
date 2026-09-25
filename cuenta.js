/* ═════════════════════════════════════════════════
   CUENTA DEL CLIENTE — Spa Infinity
   ═════════════════════════════════════════════════
   Registro, ingreso, recuperación de contraseña y sesión de los clientes
   del sitio, con Supabase Auth (correo y contraseña).

   La sesión del cliente se guarda aparte de la del panel del equipo
   ('spa_cliente_session' y no 'spa_session'): así una cuenta de cliente
   nunca se mezcla con el panel, y el equipo puede tener abierto el panel
   y una cuenta de cliente de prueba en el mismo navegador.

   Necesita config-cliente.js y supabase-client.js (SUPABASE_URL y la
   llave anónima). Todo el texto que ve la persona está en español de Chile.
   ═════════════════════════════════════════════════ */

const Cuenta = (() => {
  const CLAVE = 'spa_cliente_session'
  const AUTH = SUPABASE_URL + '/auth/v1'
  const REST = SUPABASE_URL + '/rest/v1'

  /* ── Mensajes de error en español ──
     Supabase responde con un código (error_code) y un texto en inglés;
     aquí se traduce lo que puede ver un cliente. */
  const ERRORES = {
    invalid_credentials:          'El correo o la contraseña no son correctos.',
    email_not_confirmed:          'Todavía no confirmas tu correo. Revisa tu bandeja de entrada (y la carpeta de spam).',
    user_already_exists:          'Ya existe una cuenta con ese correo. Ingresa o recupera tu contraseña.',
    email_exists:                 'Ya existe una cuenta con ese correo. Ingresa o recupera tu contraseña.',
    weak_password:                'La contraseña es muy débil: usa al menos 8 caracteres, mezclando letras y números.',
    same_password:                'La nueva contraseña tiene que ser distinta a la anterior.',
    email_address_invalid:        'Ese correo no es válido. Revísalo, por favor.',
    validation_failed:            'Hay un dato que no es válido. Revisa el formulario.',
    over_email_send_rate_limit:   'Enviamos varios correos seguidos. Espera unos minutos e inténtalo de nuevo.',
    over_request_rate_limit:      'Hiciste muchos intentos seguidos. Espera un momento e inténtalo de nuevo.',
    otp_expired:                  'El enlace expiró o ya se usó. Pide uno nuevo.',
    session_not_found:            'Tu sesión se cerró. Vuelve a ingresar.',
    refresh_token_not_found:      'Tu sesión se cerró. Vuelve a ingresar.',
    signup_disabled:              'Por ahora no es posible crear cuentas nuevas. Escríbenos por WhatsApp.',
    user_banned:                  'Esta cuenta está bloqueada. Escríbenos por WhatsApp.',
  }
  function traducir(j, status) {
    const codigo = j && (j.error_code || j.code)
    if (codigo && ERRORES[codigo]) return ERRORES[codigo]
    const t = String((j && (j.msg || j.error_description || j.message || j.error)) || '')
    if (/invalid login/i.test(t)) return ERRORES.invalid_credentials
    if (/not confirmed/i.test(t)) return ERRORES.email_not_confirmed
    if (/already registered|already exists/i.test(t)) return ERRORES.user_already_exists
    if (/password should be at least|weak/i.test(t)) return ERRORES.weak_password
    if (/rate limit|too many/i.test(t) || status === 429) return ERRORES.over_request_rate_limit
    if (/expired|invalid.*(token|link)/i.test(t)) return ERRORES.otp_expired
    return 'No pudimos completar la operación. Inténtalo de nuevo en un momento.'
  }
  class ErrorCuenta extends Error {
    constructor(mensaje, codigo) { super(mensaje); this.codigo = codigo || '' }
  }

  /* llamada a Supabase Auth; si falla, lanza el error ya en español */
  async function auth(ruta, { metodo = 'POST', cuerpo, token } = {}) {
    let res
    try {
      res = await fetch(AUTH + ruta, {
        method: metodo,
        headers: {
          'apikey': SUPABASE_ANON_KEY,
          'Content-Type': 'application/json',
          ...(token ? { 'Authorization': 'Bearer ' + token } : {})
        },
        body: cuerpo ? JSON.stringify(cuerpo) : undefined
      })
    } catch (e) {
      throw new ErrorCuenta('No pudimos conectarnos. Revisa tu conexión a internet e inténtalo de nuevo.', 'red')
    }
    const j = await res.json().catch(() => ({}))
    if (!res.ok) throw new ErrorCuenta(traducir(j, res.status), j.error_code || j.code || String(res.status))
    return j
  }

  /* ── La sesión guardada ── */
  function leer() {
    try { return JSON.parse(localStorage.getItem(CLAVE) || 'null') } catch (e) { return null }
  }
  function guardar(j) {
    const s = {
      access_token: j.access_token,
      refresh_token: j.refresh_token,
      expires_at: j.expires_at || (Math.floor(Date.now() / 1000) + (Number(j.expires_in) || 3600)),
      user: j.user || null
    }
    try { localStorage.setItem(CLAVE, JSON.stringify(s)) } catch (e) {}
    return s
  }
  function borrar() { try { localStorage.removeItem(CLAVE) } catch (e) {} }

  /* la sesión vigente: si el token está por vencer se renueva; si no se
     puede renovar, se cierra y devuelve null */
  let renovando = null
  async function sesion() {
    const s = leer()
    if (!s || !s.access_token) return null
    if (s.expires_at - 60 > Date.now() / 1000) return s
    if (!renovando) {
      renovando = auth('/token?grant_type=refresh_token', { cuerpo: { refresh_token: s.refresh_token } })
        .then(guardar)
        .catch(() => { borrar(); return null })
        .finally(() => { renovando = null })
    }
    return renovando
  }
  /* rápido y sin red, para decidir qué mostrar en el menú */
  function haySesion() { const s = leer(); return !!(s && s.refresh_token) }

  /* ── Enlaces que llegan por correo ──
     Supabase vuelve al sitio con los datos de la sesión en el ancla de la
     dirección (#access_token=…&type=signup|recovery), o con un error
     (#error_code=otp_expired…). Se leen, se guardan y se borran de la
     barra de direcciones para que no queden a la vista. */
  function leerEnlace() {
    const h = new URLSearchParams(location.hash.replace(/^#/, ''))
    if (!h.get('access_token') && !h.get('error_code') && !h.get('error')) return null
    history.replaceState(null, '', location.pathname + location.search)
    if (h.get('error_code') || h.get('error')) {
      const codigo = h.get('error_code') || h.get('error')
      return { error: ERRORES[codigo] || traducir({ error_code: codigo, msg: h.get('error_description') }) }
    }
    const s = guardar({
      access_token: h.get('access_token'),
      refresh_token: h.get('refresh_token'),
      expires_at: Number(h.get('expires_at')) || undefined,
      expires_in: h.get('expires_in')
    })
    return { tipo: h.get('type') || '', sesion: s }
  }

  /* ── Acciones ── */
  const volverA = ruta => location.origin + ruta

  async function registrar({ email, password, nombre, apellido, telefono, rut }) {
    /* Si el correo ya tiene cuenta, Supabase responde igual que si fuera
       nueva (para no revelar qué correos están registrados): en los dos
       casos se le pide a la persona revisar su correo. */
    return auth('/signup?redirect_to=' + encodeURIComponent(volverA('/ingresar?verificado=1')), {
      cuerpo: { email, password, data: { nombre, apellido, telefono: telefono || null, rut: rut || null } }
    })
  }
  async function reenviarConfirmacion(email) {
    return auth('/resend?redirect_to=' + encodeURIComponent(volverA('/ingresar?verificado=1')), {
      cuerpo: { type: 'signup', email }
    })
  }
  async function ingresar(email, password) {
    const j = await auth('/token?grant_type=password', { cuerpo: { email, password } })
    return guardar(j)
  }
  async function recuperar(email) {
    return auth('/recover?redirect_to=' + encodeURIComponent(volverA('/restablecer')), { cuerpo: { email } })
  }
  async function cambiarContrasena(nueva) {
    const s = await sesion()
    if (!s) throw new ErrorCuenta(ERRORES.session_not_found, 'session_not_found')
    return auth('/user', { metodo: 'PUT', cuerpo: { password: nueva }, token: s.access_token })
  }
  async function salir() {
    const s = leer()
    borrar()
    if (s && s.access_token) { try { await auth('/logout', { token: s.access_token }) } catch (e) {} }
  }

  /* páginas privadas: sin sesión se va a ingresar y después se vuelve aquí */
  async function requerirSesion() {
    const s = await sesion()
    if (s) return s
    location.replace('/ingresar?volver=' + encodeURIComponent(location.pathname + location.search))
    return new Promise(() => {})   // la página no sigue mientras cambia
  }
  /* adónde ir después de ingresar: solo rutas del mismo sitio */
  function destino(porDefecto = '/mi-cuenta') {
    const v = new URLSearchParams(location.search).get('volver') || ''
    return /^\/(?!\/)[\w\-\/.?=&%]*$/.test(v) ? v : porDefecto
  }

  /* ── Datos: la API de la base con el token del cliente ── */
  async function rest(ruta, { metodo = 'GET', cuerpo, prefer } = {}) {
    const s = await sesion()
    if (!s) throw new ErrorCuenta(ERRORES.session_not_found, 'session_not_found')
    let res
    try {
      res = await fetch(REST + ruta, {
        method: metodo,
        headers: {
          'apikey': SUPABASE_ANON_KEY,
          'Authorization': 'Bearer ' + s.access_token,
          'Content-Type': 'application/json',
          ...(prefer ? { 'Prefer': prefer } : {})
        },
        body: cuerpo ? JSON.stringify(cuerpo) : undefined
      })
    } catch (e) {
      throw new ErrorCuenta('No pudimos conectarnos. Revisa tu conexión a internet e inténtalo de nuevo.', 'red')
    }
    const texto = await res.text()
    const j = texto ? JSON.parse(texto) : null
    if (!res.ok) {
      /* 23514 = la base rechazó un dato por formato (RUT, teléfono…) */
      if (j && j.code === '23514') {
        const m = String(j.message || '')
        throw new ErrorCuenta(/rut/i.test(m) ? 'El RUT no es válido. Revisa el número y el dígito verificador.'
          : /telefono/i.test(m) ? 'El teléfono no es válido.' : 'Hay un dato que no es válido.', '23514')
      }
      /* P0001 = un aviso escrito en la base para la persona ("Esa hora ya está tomada") */
      if (j && j.code === 'P0001' && j.message) throw new ErrorCuenta(j.message, 'P0001')
      if (res.status === 401) { borrar(); throw new ErrorCuenta(ERRORES.session_not_found, 'session_not_found') }
      throw new ErrorCuenta('No pudimos guardar los cambios. Inténtalo de nuevo en un momento.', j && j.code)
    }
    return j
  }
  async function perfil() {
    const s = await sesion(); if (!s) return null
    const filas = await rest('/profiles?select=*&id=eq.' + encodeURIComponent(s.user ? s.user.id : idDelToken(s.access_token)))
    return filas && filas[0] || null
  }
  async function guardarPerfil(datos) {
    const s = await sesion()
    const id = s.user ? s.user.id : idDelToken(s.access_token)
    const filas = await rest('/profiles?id=eq.' + encodeURIComponent(id), { metodo: 'PATCH', cuerpo: datos, prefer: 'return=representation' })
    return filas && filas[0]
  }
  /* pide al servidor el correo de una cancelación o un cambio (no se espera: si
     el correo falla, la reserva ya quedó hecha igual) */
  async function avisarPorCorreo(cuerpo) {
    try {
      const s = await sesion()
      await fetch('/api/correo-reserva.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...(s ? { 'Authorization': 'Bearer ' + s.access_token } : {}) },
        body: JSON.stringify(cuerpo)
      })
    } catch (e) { /* el correo es un extra */ }
  }

  /* funciones de la base (historial, vinculación) */
  function rpc(fn, args) { return rest('/rpc/' + fn, { metodo: 'POST', cuerpo: args || {} }) }

  /* cambiar la contraseña pidiendo la actual: se comprueba ingresando con ella */
  async function cambiarConActual(actual, nueva) {
    try { await ingresar(correo(), actual) }
    catch (e) {
      if (e.codigo === 'invalid_credentials') throw new ErrorCuenta('La contraseña actual no es correcta.', 'clave_actual')
      throw e
    }
    return cambiarContrasena(nueva)
  }

  function idDelToken(t) {
    try { return JSON.parse(atob(t.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))).sub } catch (e) { return '' }
  }
  function correo() {
    const s = leer()
    if (s && s.user && s.user.email) return s.user.email
    try { return JSON.parse(atob(s.access_token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))).email || '' } catch (e) { return '' }
  }

  return { registrar, reenviarConfirmacion, ingresar, recuperar, cambiarContrasena, salir,
           sesion, haySesion, leerEnlace, requerirSesion, destino, rest, rpc, avisarPorCorreo, perfil, guardarPerfil, cambiarConActual, correo, ErrorCuenta }
})()

/* ── Validaciones del formulario (las mismas reglas que exige la base) ── */
const Validar = {
  correo: v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(v || '').trim()),
  contrasena: v => String(v || '').length >= 8,
  /* teléfono chileno en cualquier formato → +569XXXXXXXX; uno extranjero con + se deja */
  telefono(v) {
    const t = String(v || '').trim()
    if (!t) return ''
    const dig = t.replace(/\D/g, '')
    if (/^9\d{8}$/.test(dig)) return '+56' + dig
    if (/^569\d{8}$/.test(dig)) return '+' + dig
    if (t.startsWith('+') && dig.length >= 8 && dig.length <= 15) return '+' + dig
    return null   // no válido
  },
  /* RUT: acepta 12.345.678-5 o 123456785; devuelve 12345678-5, o null si no es válido */
  rut(v) {
    const t = String(v || '').replace(/[.\s]/g, '').toUpperCase()
    if (!t) return ''
    const m = t.match(/^(\d{7,8})-?([\dK])$/)
    if (!m) return null
    let suma = 0, mul = 2
    for (let i = m[1].length - 1; i >= 0; i--) { suma += Number(m[1][i]) * mul; mul = mul === 7 ? 2 : mul + 1 }
    const r = 11 - (suma % 11)
    const dv = r === 11 ? '0' : r === 10 ? 'K' : String(r)
    return dv === m[2] ? m[1] + '-' + m[2] : null
  }
}
