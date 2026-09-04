/* "1hr30min", "2 Hrs", "90" -> "90 min", que es como lo guarda la agenda */
function duracionNormal(v){
  const t = String(v == null ? '' : v).trim().toLowerCase()
  if(!t) return ''
  const NUM = '([0-9]+)[^0-9a-z]*'
  const hr = t.match(new RegExp(NUM + '(h|hr|hrs|hora|horas)'))
  const mi = t.match(new RegExp(NUM + '(m|min|mins|minuto|minutos)'))
  let n = (hr || mi) ? ((hr ? parseInt(hr[1]) * 60 : 0) + (mi ? parseInt(mi[1]) : 0)) : 0
  /* "1 h 30" -> la segunda cifra son los minutos */
  if(hr && !mi){
    const resto = t.slice(t.indexOf(hr[0]) + hr[0].length).match(/[0-9]+/)
    if(resto) n += parseInt(resto[0])
  }
  if(!n){ const solo = t.match(/[0-9]+/); n = solo ? parseInt(solo[0]) : 0 }
  return n ? n + ' min' : t
}

/* ═══════════════════════════════════════════
   SPA INFINITY — Supabase Client
   ═══════════════════════════════════════════ */

/* La dirección y la llave salen de config-cliente.js, que es lo único
   que cambia de un negocio a otro. Si esa configuración no cargara, se
   usan los valores de siempre para que nada deje de funcionar. */
const SUPABASE_URL = (typeof CLIENTE !== 'undefined' && CLIENTE.supabaseUrl)
  ? CLIENTE.supabaseUrl
  : 'https://bxwamppamqxtncvfdycy.supabase.co'
const SUPABASE_ANON_KEY = (typeof CLIENTE !== 'undefined' && CLIENTE.supabaseKey)
  ? CLIENTE.supabaseKey
  : 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA'

/* ── Lightweight Supabase REST wrapper ──── */
/* Token de sesión (Supabase Auth). Si no hay sesión se usa la clave anónima.

   OJO con lo que costo encontrar esto: aqui se tomaba el token guardado sin
   mirar si seguia vivo. El token del panel dura una hora. Pasada esa hora, a
   quien hubiera entrado alguna vez al panel en ese navegador —o sea, Luis y
   cualquiera del equipo— la base le respondia 401 a TODO, tambien en las
   paginas publicas: el catalogo se caia al respaldo (precios viejos) y la
   pagina de reserva decia "No pudimos cargar las horas". Desde otro navegador,
   sin sesion, el mismo enlace andaba perfecto.

   Ahora un token vencido sencillamente no se usa: se lee con la clave publica,
   que es lo que necesita el sitio. El panel renueva su sesion aparte, con
   supaAuth.ensureFresh(). */
let SUPA_TOKEN = null
try {
  const sess = JSON.parse(localStorage.getItem('spa_session') || 'null')
  /* 60 segundos de margen, por si el reloj del equipo va adelantado */
  const vivo = sess && sess.access_token && (!sess.expires_at || sess.expires_at - Date.now() > 60000)
  if (vivo) SUPA_TOKEN = sess.access_token
} catch (e) {}
function supaAuthHeader() { return 'Bearer ' + (SUPA_TOKEN || SUPABASE_ANON_KEY) }

const supabase = {
  async fetch(table, { select = '*', filters = '', order = '' } = {}) {
    let url = `${SUPABASE_URL}/rest/v1/${table}?select=${encodeURIComponent(select)}`
    if (filters) url += `&${filters}`
    if (order) url += `&order=${order}`
    const pedir = () => fetch(url, {
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': supaAuthHeader(),
        'Content-Type': 'application/json'
      }
    })
    /* En el celular una peticion se corta de vez en cuando y la pagina se caia
       entera por eso. Se intenta una segunda vez, esperando un momento, cuando
       el fallo es de red o del servidor. Si la respuesta es un 4xx no se
       reintenta: ese error no se arregla repitiendolo. */
    /* si esta lectura salio con el token, hay que recordarlo: dos lecturas en
       paralelo pueden recibir 401 a la vez, y la primera en reaccionar deja el
       token en nulo antes de que la segunda mire */
    const saliaConToken = !!SUPA_TOKEN
    let res
    try { res = await pedir() }
    catch (e) {
      await new Promise(r => setTimeout(r, 700))
      res = await pedir()
    }
    if (res.status >= 500) {
      await new Promise(r => setTimeout(r, 700))
      res = await pedir()
    }
    /* El token puede morir mientras la pagina esta abierta, o haber sido
       revocado. Antes de darse por vencida, la lectura se repite con la clave
       publica: para el sitio es lo unico que hace falta. */
    if (res.status === 401 && saliaConToken) {
      console.warn('La sesion del panel ya no sirve; se lee con la clave publica')
      SUPA_TOKEN = null
      res = await pedir()
    }
    if (!res.ok) throw new Error(`Supabase ${table}: ${res.status}`)
    return res.json()
  },

  async insert(table, data) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${table}`, {
      method: 'POST',
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': supaAuthHeader(),
        'Content-Type': 'application/json',
        'Prefer': 'return=representation'
      },
      body: JSON.stringify(data)
    })
    if (!res.ok) {
      const err = await res.text()
      throw new Error(`Supabase insert ${table}: ${res.status} - ${err}`)
    }
    return res.json()
  },

  async update(table, id, data) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${table}?id=eq.${encodeURIComponent(id)}`, {
      method: 'PATCH',
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': supaAuthHeader(),
        'Content-Type': 'application/json',
        'Prefer': 'return=representation'
      },
      body: JSON.stringify(data)
    })
    if (!res.ok) {
      const err = await res.text()
      throw new Error(`Supabase update ${table}: ${res.status} - ${err}`)
    }
    return res.json()
  },

  /* llama a una función de la base (RPC). Se usa para las operaciones
     que el sitio público no puede hacer tocando tablas directamente. */
  async rpc(fn, params) {
    const pedir = () => fetch(`${SUPABASE_URL}/rest/v1/rpc/${fn}`, {
      method: 'POST',
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': supaAuthHeader(),
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(params || {})
    })
    const saliaConToken = !!SUPA_TOKEN
    let res = await pedir()
    /* misma proteccion que en fetch(): si el token del panel guardado en este
       navegador ya no sirve, la reserva se hace con la clave publica, que es
       con la que esta pensada la funcion de la base */
    if (res.status === 401 && saliaConToken) {
      console.warn('La sesion del panel ya no sirve; la llamada se repite con la clave publica')
      SUPA_TOKEN = null
      res = await pedir()
    }
    const txt = await res.text()
    if (!res.ok) {
      let msg = txt
      try { const j = JSON.parse(txt); msg = j.message || j.hint || txt } catch (e) {}
      throw new Error(msg)
    }
    try { return JSON.parse(txt) } catch (e) { return txt }
  },

  async delete(table, id) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${table}?id=eq.${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': supaAuthHeader(),
        'Content-Type': 'application/json'
      }
    })
    if (!res.ok) throw new Error(`Supabase delete ${table}: ${res.status}`)
    return true
  }
}

/* ── El catalogo que vio este navegador la ultima vez ──
   Si la base no responde, el sitio se quedaba con la lista escrita a mano en
   data.js, que es la que habia el dia que se escribio ese archivo: precios y
   descripciones de hace meses, mostrados sin avisar. Ahora se guarda aqui la
   ultima version buena y esa es la que se usa cuando falla la conexion. */
const RESPALDO_CATALOGO = 'catalogo-visto-v1'
const RESPALDO_DIAS = 30

function guardarCatalogo(cats) {
  try { localStorage.setItem(RESPALDO_CATALOGO, JSON.stringify({ cuando: Date.now(), cats })) } catch (e) {}
}
function catalogoGuardado() {
  try {
    const j = JSON.parse(localStorage.getItem(RESPALDO_CATALOGO) || 'null')
    if (!j || !Array.isArray(j.cats) || !j.cats.length) return null
    /* si lleva mas de un mes sin poder actualizarse, tampoco es de fiar */
    if (Date.now() - (j.cuando || 0) > RESPALDO_DIAS * 24 * 3600 * 1000) return null
    return j.cats
  } catch (e) { return null }
}

/* De donde salieron los precios que se estan mostrando: 'base' (al dia),
   'respaldo' (lo ultimo que vio este navegador) o 'estatico' (data.js).
   Se puede mirar desde la consola con CATALOGO_ORIGEN. */
window.CATALOGO_ORIGEN = 'estatico'

/* ── Load categories + services from Supabase ── */
async function loadFromSupabase() {
  try {
    const [cats, svcs] = await Promise.all([
      supabase.fetch('categories', { order: 'sort_order.asc' }),
      supabase.fetch('services', { order: 'sort_order.asc' })
    ])

    // Transform DB rows → JS format matching data.js structure
    const categories = cats.map(c => ({
      id: c.id,
      name: c.name,
      tagline: c.tagline,
      shortDesc: c.short_desc,
      longDesc: c.long_desc,
      cardImg: c.card_img,
      heroImg: c.hero_img,
      link: c.link,
      sortOrder: c.sort_order,
      services: svcs
        .filter(s => s.cat_id === c.id)
        .map(s => ({
          id: s.id,
          catId: s.cat_id,
          name: s.name,
          tag: s.tag,
          price: s.price,
          duration: s.duration,
          shortDesc: s.short_desc,
          longDesc: s.long_desc,
          img: s.img || '',
          /* respaldo solo para mostrar: si el servicio no tiene foto propia se
             usa la de su categoria. No se guarda: el campo real sigue vacio */
          imgCat: c.card_img || c.hero_img || '',
          link: s.link,
          includes: typeof s.includes === 'string' ? JSON.parse(s.includes) : (s.includes || []),
          processSteps: typeof s.process_steps === 'string' ? JSON.parse(s.process_steps) : (s.process_steps || []),
          highlighted: s.highlighted || false,
          hasDiscount: s.has_discount || false,
          discountLabel: s.discount_label || '',
          discountText: s.discount_text || '',
          originalPrice: s.original_price || '',
          gcDiscountEnabled: s.gc_discount_enabled || false,
          gcDiscountPercent: s.gc_discount_percent || 0,
          gcPrice: s.gc_price || '',
          sortOrder: s.sort_order
        }))
    }))

    window.CATALOGO_ORIGEN = 'base'
    guardarCatalogo(categories)
    return categories
  } catch (err) {
    const guardado = catalogoGuardado()
    if (guardado) {
      console.warn('No se pudo leer el catalogo; se usa el que vio este navegador la ultima vez:', err)
      window.CATALOGO_ORIGEN = 'respaldo'
      return guardado
    }
    console.warn('No se pudo leer el catalogo ni hay respaldo; quedan los precios de data.js, que pueden estar viejos:', err)
    window.CATALOGO_ORIGEN = 'estatico'
    return null
  }
}

/* ── Convert JS service object → DB row ──
   Note: sort_order is preserved if present on the object. New services
   should get sort_order assigned BEFORE calling insert (see admin). */
/* Guarda solo los campos que de verdad cambiaron.
   El panel de la web arma el registro completo y lo manda entero, asi que
   un campo vacio en su formulario borraba lo que hubiera puesto la agenda.
   Ademas, al comparar contra lo que hay guardado en ese momento, dos
   personas pueden editar cosas distintas del mismo servicio sin pisarse. */
async function updateSoloCambios(tabla, id, row) {
  let actual = null
  try { actual = (await supabase.fetch(tabla, { filters: 'id=eq.' + encodeURIComponent(id) }))[0] } catch (e) {}
  if (!actual) return supabase.update(tabla, id, row)   // no se pudo comparar: se guarda todo
  const igual = (a, b) => {
    const norm = v => {
      if (v === null || v === undefined) return ''
      if (typeof v === 'string') {
        try { const p = JSON.parse(v); if (p && typeof p === 'object') return JSON.stringify(p) } catch (e) {}
        return v.trim()
      }
      if (typeof v === 'object') return JSON.stringify(v)
      return String(v)
    }
    return norm(a) === norm(b)
  }
  const cambios = {}
  Object.keys(row).forEach(k => {
    if (k === 'id') return
    if (!igual(actual[k], row[k])) cambios[k] = row[k]
  })
  if (!Object.keys(cambios).length) return { sinCambios: true }
  return supabase.update(tabla, id, cambios)
}

function serviceToRow(svc) {
  const row = {
    id: svc.id,
    cat_id: svc.catId,
    name: svc.name,
    tag: svc.tag || '',
    price: svc.price || '',
    duration: svc.duration || '',
    short_desc: svc.shortDesc || '',
    long_desc: svc.longDesc || '',
    img: svc.img || '',
    link: svc.link || '',
    includes: JSON.stringify(svc.includes || []),
    process_steps: JSON.stringify(svc.processSteps || []),
    highlighted: svc.highlighted || false,
    has_discount: svc.hasDiscount || false,
    discount_label: svc.discountLabel || '',
    discount_text: svc.discountText || '',
    original_price: svc.originalPrice || '',
    gc_discount_enabled: svc.gcDiscountEnabled || false,
    gc_discount_percent: svc.gcDiscountPercent || 0,
    gc_price: svc.gcPrice || ''
  }
  if (svc.sortOrder != null) row.sort_order = svc.sortOrder
  return row
}

function categoryToRow(cat) {
  const row = {
    id: cat.id,
    name: cat.name,
    tagline: cat.tagline || '',
    short_desc: cat.shortDesc || '',
    long_desc: cat.longDesc || '',
    card_img: cat.cardImg || '',
    hero_img: cat.heroImg || '',
    link: cat.link || ''
  }
  if (cat.sortOrder != null) row.sort_order = cat.sortOrder
  return row
}

/* ── Promo Events ─────────────────────────── */
/* URL limpia de la página de promo, derivada del nombre del evento.
   Ej: "Mes Renovación Total" → /promocion/mes-renovacion-total
   El slug es cosmético (la página siempre carga el evento activo). */
function promoSlugify(name) {
  return (name || '')
    .toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}
function promoPageUrl(cfg) {
  const slug = promoSlugify(cfg && (cfg.eventName || cfg.heroTitle))
  return slug ? `/promocion/${slug}` : '/promocion'
}

async function loadActivePromoEvent() {
  try {
    const rows = await supabase.fetch('promo_events', {
      filters: 'active=eq.true',
      order: 'id.desc'
    })
    return rows && rows.length ? rows[0] : null
  } catch (e) {
    console.warn('Promo event load failed:', e)
    return null
  }
}

async function loadAllPromoEvents() {
  try {
    return await supabase.fetch('promo_events', { order: 'id.desc' })
  } catch (e) {
    console.warn('Promo events load failed:', e)
    return []
  }
}

async function savePromoEvent(id, data) {
  if (id) {
    data.updated_at = new Date().toISOString()
    return supabase.update('promo_events', id, data)
  } else {
    return supabase.insert('promo_events', data)
  }
}

async function deletePromoEvent(id) {
  return supabase.delete('promo_events', id)
}

/* ── Page Content (CMS) ──────────────────── */
async function loadPageContent(pageId) {
  try {
    const rows = await supabase.fetch('page_content', {
      filters: `id=eq.${pageId}`
    })
    return rows && rows.length ? rows[0].content : null
  } catch (e) {
    console.warn('Page content load failed:', e)
    return null
  }
}

async function savePageContent(pageId, content) {
  const data = { content, updated_at: new Date().toISOString() }
  try {
    return await supabase.update('page_content', pageId, data)
  } catch (e) {
    // If row doesn't exist, insert
    return await supabase.insert('page_content', { id: pageId, ...data })
  }
}

/* ═══════════════════════════════════════════
   Autenticación (Supabase Auth) — usada por el panel
   ═══════════════════════════════════════════ */
const supaAuth = {
  async signIn(email, password) {
    const res = await fetch(SUPABASE_URL + '/auth/v1/token?grant_type=password', {
      method: 'POST',
      headers: { 'apikey': SUPABASE_ANON_KEY, 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    })
    const j = await res.json()
    if (!res.ok) throw new Error(j.error_description || j.msg || 'No pudimos iniciar sesión')
    this.setSession(j)
    return j
  },

  setSession(j) {
    SUPA_TOKEN = j.access_token
    localStorage.setItem('spa_session', JSON.stringify({
      access_token: j.access_token,
      refresh_token: j.refresh_token,
      expires_at: Date.now() + (j.expires_in || 3600) * 1000,
      user: j.user || null
    }))
  },

  session() {
    try { return JSON.parse(localStorage.getItem('spa_session') || 'null') } catch (e) { return null }
  },

  /* renueva el token si está por vencer; devuelve false si la sesión ya no sirve */
  async ensureFresh() {
    const s = this.session()
    if (!s) return false
    if (s.expires_at && s.expires_at - Date.now() > 120000) { SUPA_TOKEN = s.access_token; return true }
    try {
      const res = await fetch(SUPABASE_URL + '/auth/v1/token?grant_type=refresh_token', {
        method: 'POST',
        headers: { 'apikey': SUPABASE_ANON_KEY, 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: s.refresh_token })
      })
      const j = await res.json()
      if (!res.ok) { this.signOut(); return false }
      this.setSession(j)
      return true
    } catch (e) { return false }
  },

  async changePassword(newPassword) {
    const res = await fetch(SUPABASE_URL + '/auth/v1/user', {
      method: 'PUT',
      headers: { 'apikey': SUPABASE_ANON_KEY, 'Authorization': supaAuthHeader(), 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: newPassword })
    })
    const j = await res.json()
    if (!res.ok) throw new Error(j.msg || j.error_description || 'No pudimos cambiar la contraseña')
    return j
  },

  async resetPassword(email, redirectTo) {
    const body = { email, gotrue_meta_security: {} }
    if (redirectTo) body.redirect_to = redirectTo
    const res = await fetch(SUPABASE_URL + '/auth/v1/recover', {
      method: 'POST',
      headers: { 'apikey': SUPABASE_ANON_KEY, 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
    if (!res.ok) { const j = await res.json().catch(function () { return {} }); throw new Error(j.msg || 'No pudimos enviar el correo') }
    return true
  },

  signOut() { SUPA_TOKEN = null; localStorage.removeItem('spa_session'); localStorage.removeItem('spa_admin_auth') }
}
