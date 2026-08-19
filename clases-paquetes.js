/* ═══════════════════════════════════════════
   SPA INFINITY — Clases y paquetes en el sitio
   Lee lo que el administrador creó en el panel (vista public_pricing)
   y lo muestra al final de la página de servicios.
   ═══════════════════════════════════════════ */
const ClasesPaquetes = {
  DOW: ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'],
  clases: [],
  paquetes: [],

  money(n) { return '$' + (Number(n) || 0).toLocaleString('es-CL') },

  async load() {
    try {
      const rows = await supabase.fetch('public_pricing')
      const r = (rows && rows[0]) || {}
      const cls = typeof r.svc_classes === 'string' ? JSON.parse(r.svc_classes || '[]') : (r.svc_classes || [])
      const pks = typeof r.svc_packages === 'string' ? JSON.parse(r.svc_packages || '[]') : (r.svc_packages || [])
      this.clases = cls.filter(c => c.online !== false)
      this.paquetes = pks.filter(p => p.online !== false)
    } catch (e) { this.clases = []; this.paquetes = [] }
    return this
  },

  diasTexto(c) {
    const d = (c.days || []).map(x => this.DOW[x])
    if (!d.length) return 'Consultar horarios'
    const dias = d.length > 1 ? d.slice(0, -1).join(', ') + ' y ' + d[d.length - 1] : d[0]
    return `${dias} · ${c.time} hrs`
  },

  styles() {
    if (document.getElementById('cp-styles')) return
    const css = document.createElement('style')
    css.id = 'cp-styles'
    css.textContent = `
      .cp-section{padding:0 0 80px}
      .cp-title{font-family:'Playfair Display',serif;font-size:2rem;text-align:center;margin-bottom:8px;color:var(--dark)}
      .cp-sub{text-align:center;color:var(--mid);font-size:.9rem;margin-bottom:34px}
      .cp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:22px}
      .cp-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:24px;display:flex;flex-direction:column}
      .cp-tag{font-size:.66rem;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);font-weight:700;margin-bottom:8px}
      .cp-name{font-family:'Playfair Display',serif;font-size:1.25rem;margin-bottom:8px;color:var(--dark)}
      .cp-desc{font-size:.86rem;color:var(--mid);line-height:1.6;margin-bottom:14px}
      .cp-meta{font-size:.8rem;color:var(--mid);border-top:1px solid var(--border);padding-top:12px;margin-top:auto;display:flex;flex-direction:column;gap:5px}
      .cp-meta strong{color:var(--dark)}
      .cp-price{font-size:1.35rem;font-weight:700;color:var(--gold);margin-top:10px}
      .cp-items{list-style:none;margin:0 0 12px;padding:0}
      .cp-items li{font-size:.84rem;color:var(--mid);padding:5px 0;border-bottom:1px dashed var(--border);display:flex;justify-content:space-between;gap:10px}
      .cp-items li:last-child{border-bottom:none}
      .cp-cta{display:inline-block;margin-top:16px;text-align:center;background:var(--gold);color:#fff;padding:12px;border-radius:30px;
        font-size:.78rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;text-decoration:none}
      .cp-cta:hover{background:var(--gold-d,#a8883f)}`
    document.head.appendChild(css)
  },

  /* pinta la sección dentro del contenedor indicado */
  render(container) {
    if (!this.clases.length && !this.paquetes.length) return
    this.styles()
    const wa = (typeof SITE !== 'undefined' && SITE.booking) ? SITE.booking : 'https://wa.me/56986688771'

    const clasesHtml = this.clases.map(c => `
      <article class="cp-card">
        <span class="cp-tag">Clase grupal</span>
        <h3 class="cp-name">${c.name}</h3>
        ${c.desc ? `<p class="cp-desc">${c.desc}</p>` : ''}
        <div class="cp-meta">
          <span>🗓 ${this.diasTexto(c)}</span>
          ${c.showDur === false ? '' : `<span>⏱ ${c.dur} minutos</span>`}
          <span>👥 Hasta <strong>${c.cap} personas</strong> por clase</span>
        </div>
        ${c.price ? `<div class="cp-price">${c.price}</div>` : ''}
        <a class="cp-cta" href="${wa}" target="_blank" rel="noopener">Reservar mi cupo</a>
      </article>`).join('')

    const paquetesHtml = this.paquetes.map(p => {
      const total = (p.items || []).reduce((a, i) => a + Number(i.price || 0), 0)
      return `
      <article class="cp-card">
        <span class="cp-tag">Paquete</span>
        <h3 class="cp-name">${p.name}</h3>
        ${p.desc ? `<p class="cp-desc">${p.desc}</p>` : ''}
        <ul class="cp-items">
          ${(p.items || []).map(i => `<li><span>${i.name}</span><span>${this.money(i.price)}</span></li>`).join('')}
        </ul>
        <div class="cp-meta"><span>Incluye <strong>${(p.items || []).length} servicios</strong></span></div>
        <div class="cp-price">${this.money(total)}</div>
        <a class="cp-cta" href="${wa}" target="_blank" rel="noopener">Reservar el paquete</a>
      </article>`
    }).join('')

    container.innerHTML = `
      <section class="cp-section">
        <h2 class="cp-title">Clases y paquetes</h2>
        <p class="cp-sub">Experiencias grupales y combinaciones de servicios pensadas para ti.</p>
        <div class="cp-grid">${clasesHtml}${paquetesHtml}</div>
      </section>`
  }
}
