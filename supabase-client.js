/* ═══════════════════════════════════════════
   SPA INFINITY — Supabase Client
   ═══════════════════════════════════════════ */

const SUPABASE_URL = 'https://bxwamppamqxtncvfdycy.supabase.co'
const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImJ4d2FtcHBhbXF4dG5jdmZkeWN5Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgzMzg1MjAsImV4cCI6MjA5MzkxNDUyMH0.UiSFfFCU8GusDWqfgf3c9PL10ctHwZtaWvHFY8VghzA'

/* ── Lightweight Supabase REST wrapper ──── */
const supabase = {
  async fetch(table, { select = '*', filters = '', order = '' } = {}) {
    let url = `${SUPABASE_URL}/rest/v1/${table}?select=${encodeURIComponent(select)}`
    if (filters) url += `&${filters}`
    if (order) url += `&order=${order}`
    const res = await fetch(url, {
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': `Bearer ${SUPABASE_ANON_KEY}`,
        'Content-Type': 'application/json'
      }
    })
    if (!res.ok) throw new Error(`Supabase ${table}: ${res.status}`)
    return res.json()
  },

  async insert(table, data) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${table}`, {
      method: 'POST',
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': `Bearer ${SUPABASE_ANON_KEY}`,
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
        'Authorization': `Bearer ${SUPABASE_ANON_KEY}`,
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

  async delete(table, id) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${table}?id=eq.${encodeURIComponent(id)}`, {
      method: 'DELETE',
      headers: {
        'apikey': SUPABASE_ANON_KEY,
        'Authorization': `Bearer ${SUPABASE_ANON_KEY}`,
        'Content-Type': 'application/json'
      }
    })
    if (!res.ok) throw new Error(`Supabase delete ${table}: ${res.status}`)
    return true
  }
}

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
          img: s.img,
          link: s.link,
          includes: typeof s.includes === 'string' ? JSON.parse(s.includes) : (s.includes || []),
          processSteps: typeof s.process_steps === 'string' ? JSON.parse(s.process_steps) : (s.process_steps || []),
          highlighted: s.highlighted || false,
          hasDiscount: s.has_discount || false,
          discountLabel: s.discount_label || '',
          originalPrice: s.original_price || ''
        }))
    }))

    return categories
  } catch (err) {
    console.warn('Supabase load failed, using static data:', err)
    return null
  }
}

/* ── Convert JS service object → DB row ── */
function serviceToRow(svc) {
  return {
    id: svc.id,
    cat_id: svc.catId,
    name: svc.name,
    tag: svc.tag || '',
    price: svc.price,
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
    original_price: svc.originalPrice || ''
  }
}

function categoryToRow(cat) {
  return {
    id: cat.id,
    name: cat.name,
    tagline: cat.tagline || '',
    short_desc: cat.shortDesc || '',
    long_desc: cat.longDesc || '',
    card_img: cat.cardImg || '',
    hero_img: cat.heroImg || '',
    link: cat.link || ''
  }
}

/* ── Promo Events ─────────────────────────── */
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
