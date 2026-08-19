/* ═══════════════════════════════════════════
   SPA INFINITY — Precios Inteligentes (sitio)
   Lee las reglas de descuento por franja horaria configuradas en el panel
   (bot_config.smart_pricing) y las muestra en las páginas de servicios.
   ═══════════════════════════════════════════ */
const SmartPricing = {
  rules: [],
  on: false,
  DOW: ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],

  async load() {
    try {
      /* vista pública: expone SOLO las reglas de precios, nada más de la configuración */
      const rows = await supabase.fetch('public_pricing')
      const cfg = rows && rows[0] && rows[0].smart_pricing
      const sp = typeof cfg === 'string' ? JSON.parse(cfg || '{}') : (cfg || {})
      this.on = !!sp.on
      this.rules = (sp.rules || []).filter(r => r.active)
    } catch (e) {
      this.on = false; this.rules = []
    }
    return this.rules
  },

  money(n) { return '$' + (n || 0).toLocaleString('es-CL') },
  toInt(p) { const n = String(p == null ? '' : p).replace(/[^0-9]/g, ''); return n ? parseInt(n) : 0 },

  /* reglas que aplican a un servicio concreto */
  rulesFor(serviceName) {
    if (!this.on) return []
    return this.rules.filter(r => r.services === 'all' || (r.services || []).includes(serviceName))
  },

  /* texto tipo "martes y jueves de 14:00 a 17:00" */
  whenText(r) {
    const dias = (r.days || []).map(d => this.DOW[d])
    let txt = dias.length > 1
      ? dias.slice(0, -1).join(', ') + ' y ' + dias[dias.length - 1]
      : (dias[0] || '')
    return `${txt} de ${r.from} a ${r.to}`
  },

  /* chip compacto para tarjetas: "−25% los martes de 14:00 a 17:00" */
  chip(serviceName) {
    const r = this.rulesFor(serviceName)[0]
    if (!r) return ''
    return `<span class="sp-chip">−${r.pct}% ${this.whenText(r)}</span>`
  },

  /* bloque completo para la ficha del servicio */
  banner(serviceName, basePrice) {
    const list = this.rulesFor(serviceName)
    if (!list.length) return ''
    const base = this.toInt(basePrice)
    return `<div class="sp-banner">
      <div class="sp-banner-head">✨ Precio rebajado en horarios de baja demanda</div>
      ${list.map(r => `<div class="sp-banner-row">
        <span class="sp-when">${this.whenText(r)}</span>
        <span class="sp-price">${this.money(Math.round(base * (100 - r.pct) / 100))}
          <span class="sp-off">−${r.pct}%</span></span>
      </div>`).join('')}
      <div class="sp-note">Reservando en esos horarios pagas menos por el mismo servicio.</div>
    </div>`
  },

  /* estilos, se inyectan una sola vez */
  styles() {
    if (document.getElementById('sp-styles')) return
    const css = document.createElement('style')
    css.id = 'sp-styles'
    css.textContent = `
      .sp-chip{display:inline-block;background:#e4f7ec;color:#1f7a4c;font-size:.68rem;font-weight:700;
        padding:3px 9px;border-radius:20px;letter-spacing:.02em;margin-top:6px}
      .sp-banner{border:1px solid #cfe9da;background:#f2fbf6;border-radius:12px;padding:16px 18px;margin:18px 0}
      .sp-banner-head{font-weight:700;font-size:.92rem;color:#1f7a4c;margin-bottom:10px}
      .sp-banner-row{display:flex;justify-content:space-between;align-items:center;gap:12px;
        padding:7px 0;border-bottom:1px solid #dcefe4;font-size:.9rem}
      .sp-banner-row:last-of-type{border-bottom:none}
      .sp-when{color:#2f4f43;text-transform:capitalize}
      .sp-price{font-weight:700;color:#1f7a4c;white-space:nowrap}
      .sp-off{background:#1f7a4c;color:#fff;font-size:.68rem;padding:2px 7px;border-radius:20px;margin-left:6px}
      .sp-note{font-size:.76rem;color:#4a6b5d;margin-top:10px}`
    document.head.appendChild(css)
  }
}
