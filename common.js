/* ═══════════════════════════════════════════
   SPA INFINITY — Shared UI Components
   ═══════════════════════════════════════════ */

/* ── NAVBAR ─────────────────────────────────────────── */
function renderNavbar(activePage) {
  const pages = [
    { href: 'index.html',    label: 'Inicio' },
    { href: 'nosotros.html',  label: 'Nosotros' },
    { href: 'servicios.html',label: 'Servicios', hasMega: true },
    { href: 'giftcard.html', label: 'Gift Cards' },
    { href: 'contacto.html', label: 'Contacto' },
  ]

  const links = pages.map(p => {
    if (p.hasMega) {
      return `<li class="nav-has-mega">
        <a href="${p.href}" class="${activePage === p.label ? 'active' : ''}">${p.label}
          <svg class="nav-mega-arrow" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M6 9l6 6 6-6"/></svg>
        </a>
        <div class="nav-dd">
          ${CATEGORIES.map((c, i) => `
            <div class="nav-dd-cat">
              <div class="nav-dd-cat-link">
                <span class="nav-dd-cat-name">${c.name}</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6"/></svg>
              </div>
              <div class="nav-dd-svcs">
                ${c.services.map(s => `
                  <a href="servicio.html?id=${s.id}" class="nav-dd-svc">
                    <span class="nav-dd-svc-name">${s.name}</span>
                    <span class="nav-dd-svc-price">${s.price}</span>
                  </a>
                `).join('')}
                <a href="categoria.html?cat=${c.id}" class="nav-dd-svc-all">Ver categoría completa →</a>
              </div>
            </div>
          `).join('')}
          <a href="servicios.html" class="nav-dd-footer">Ver todos los servicios →</a>
        </div>
      </li>`
    }
    return `<li><a href="${p.href}" class="${activePage === p.label ? 'active' : ''}">${p.label}</a></li>`
  }).join('')

  return `
<nav class="site-nav" id="siteNav">
  <div class="nav-inner">
    <a href="index.html" class="nav-logo" aria-label="Spa Infinity Inicio">
      <img src="images/logo.webp" alt="Spa Infinity" class="nav-logo-img" />
    </a>
    <ul class="nav-links">${links}</ul>
    <div class="nav-search-container">
      <label class="nav-search" for="navSearchInput" aria-label="Buscar">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input type="text" id="navSearchInput" placeholder="Buscar servicio..." autocomplete="off" />
      </label>
      <div class="search-dropdown" id="searchDropdown"></div>
    </div>
    <div class="nav-mob-actions">
      <button class="nav-mob-search-btn" onclick="toggleMobSearch()" aria-label="Buscar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      </button>
      <button class="nav-mob-btn" onclick="toggleMobMenu()" aria-label="Menú">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>
<div class="mob-search-overlay" id="mobSearchOverlay">
  <div class="mob-search-bar">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" id="mobSearchInput" placeholder="Buscar servicio..." autocomplete="off" />
    <button class="mob-search-close" onclick="toggleMobSearch()">✕</button>
  </div>
  <div class="mob-search-results" id="mobSearchResults"></div>
</div>
<div class="mob-menu" id="mobMenu">
  <button class="mob-close" onclick="toggleMobMenu()">✕</button>

  <!-- Panel 0: Main links -->
  <div class="mob-panel mob-panel--main" id="mobPanelMain">
    ${pages.map(p => {
      if (p.hasMega) {
        return `<a href="javascript:void(0)" onclick="mobShowCats()" class="mob-link mob-link--arrow">
          <span>${p.label}</span>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 6l6 6-6 6"/></svg>
        </a>`
      }
      return `<a href="${p.href}" onclick="toggleMobMenu()" class="mob-link">${p.label}</a>`
    }).join('')}
    <a href="${SITE.booking}" target="_blank" class="mob-link" style="color:var(--gold)">Reservar</a>
  </div>

  <!-- Panel 1: Categories -->
  <div class="mob-panel mob-panel--sub" id="mobPanelCats">
    <button class="mob-back" onclick="mobBackToMain(event)">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M15 18l-6-6 6-6"/></svg>
      <span>Menú</span>
    </button>
    <div class="mob-panel-title">Servicios</div>
    <div class="mob-panel-list">
      ${CATEGORIES.map((c, i) => `
        <a href="javascript:void(0)" onclick="mobShowSvcs(${i})" class="mob-cat-item">
          <span>${c.name}</span>
          <span class="mob-cat-count">${c.services.length}</span>
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 6l6 6-6 6"/></svg>
        </a>
      `).join('')}
      <a href="servicios.html" onclick="toggleMobMenu()" class="mob-cat-all">Ver todos los servicios →</a>
    </div>
  </div>

  <!-- Panel 2: Services per category -->
  <div class="mob-panel mob-panel--sub" id="mobPanelSvcs">
    <button class="mob-back" onclick="mobBackToCats(event)">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M15 18l-6-6 6-6"/></svg>
      <span>Categorías</span>
    </button>
    <div class="mob-panel-title" id="mobSvcTitle"></div>
    <div class="mob-panel-list" id="mobSvcList"></div>
  </div>
</div>`
}


/* ── FOOTER ─────────────────────────────────────────── */
function renderFooter() {
  return `
<footer class="infinity-footer">
  <div class="footer-main-grid">
    <div class="footer-col footer-brand">
      <img src="images/logo.webp" alt="Spa Infinity" />
      <p>Centro Podológico y Spa Infinity: Un refugio de bienestar diseñado para tu cuidado integral.</p>
      <div class="footer-socials">
        <a href="https://web.facebook.com/podologia.spainfinity" class="social-fb" aria-label="Facebook" target="_blank"><i class="fab fa-facebook-f"></i></a>
        <a href="https://www.instagram.com/spa_infinity/" class="social-ig" aria-label="Instagram" target="_blank"><i class="fab fa-instagram"></i></a>
        <a href="https://wa.me/56986688771" class="social-wa" aria-label="WhatsApp" target="_blank"><i class="fab fa-whatsapp"></i></a>
      </div>
    </div>
    <div class="footer-col footer-info">
      <h4 class="footer-title">Contacto</h4>
      <ul class="footer-list">
        <li><span><i class="fas fa-map-marker-alt"></i> ${SITE.address}</span></li>
        <li><a href="tel:${SITE.phone.replace(/\s/g,'')}"><i class="fas fa-phone-alt"></i> ${SITE.phone}</a></li>
        <li><a href="mailto:${SITE.email}"><i class="fas fa-envelope"></i> ${SITE.email}</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4 class="footer-title">Menú</h4>
      <ul class="footer-list menu-list">
        <li><a href="index.html">Inicio</a></li>
        <li><a href="nosotros.html">Nosotros</a></li>
        <li><a href="servicios.html">Servicios</a></li>
        <li><a href="contacto.html">Contacto</a></li>
      </ul>
    </div>
    <div class="footer-col footer-info">
      <h4 class="footer-title">Atención</h4>
      <ul class="footer-list">
        <li><span><i class="far fa-clock"></i> <strong>Lun - Vie:</strong> 10:00 – 20:00 Hrs</span></li>
        <li><span><i class="far fa-calendar-check"></i> <strong>Sábado:</strong> 11:00 – 18:00 Hrs</span></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom-bar">
    <div class="copyright-area">
      <span class="copyright">© 2026 Spa Infinity. Todos los derechos reservados.</span>
      <a href="https://opusdigital.cl" class="designed-by" target="_blank">Diseñado por <strong>OpusDigital</strong></a>
    </div>
    <div class="policy-links">
      <button class="policy-btn" onclick="openPolicyModal('reservasModal')">Políticas de Reservación</button>
      <span class="policy-separator">|</span>
      <button class="policy-btn" onclick="openPolicyModal('giftModal')">Uso de Gift Cards</button>
    </div>
    <div class="payments-wrapper">
      <img src="https://tiendanube.s3.amazonaws.com/apps/10425-45-es_CL-flow-logo-square.jpg" alt="Flow" />
      <img src="https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg" alt="Mastercard" />
      <img src="https://www.webpay.cl/logos/institucion/98908?idx=1706675693654" alt="Webpay" />
    </div>
  </div>
</footer>

<!-- Policy Modals -->
<div class="policy-modal-overlay" id="reservasModal" onclick="if(event.target===this)closePolicyModal('reservasModal')">
  <div class="policy-modal-box">
    <button class="policy-close-btn" onclick="closePolicyModal('reservasModal')">&times;</button>
    <h3 class="policy-modal-title">Políticas de Reserva</h3>
    <div class="policy-modal-content">
      <p>En Spa Infinity valoramos su tiempo y el de nuestros profesionales. Por favor, lea atentamente nuestras políticas para agendamiento de citas.</p>
      <h4>1. Agendamiento</h4>
      <p>Las reservas se pueden realizar a través de nuestro sitio web, WhatsApp oficial o llamadas telefónicas. Su cita quedará confirmada una vez reciba nuestro mensaje de validación.</p>
      <h4>2. Puntualidad</h4>
      <p>Le solicitamos llegar con 10 minutos de anticipación a su cita. Si se presenta con un retraso superior a 15 minutos, nos reservamos el derecho de acortar el tiempo del servicio o reprogramar la cita para no afectar a los siguientes clientes.</p>
      <h4>3. Cancelaciones y Reprogramaciones</h4>
      <p>Si necesita cancelar o cambiar la fecha de su cita, le pedimos hacerlo con al menos 24 horas de anticipación. Las cancelaciones de última hora afectan directamente la agenda de nuestros especialistas. <strong>Tenga en cuenta que el abono realizado no es reembolsable en caso de no asistir a la cita.</strong></p>
    </div>
  </div>
</div>
<div class="policy-modal-overlay" id="giftModal" onclick="if(event.target===this)closePolicyModal('giftModal')">
  <div class="policy-modal-box">
    <button class="policy-close-btn" onclick="closePolicyModal('giftModal')">&times;</button>
    <h3 class="policy-modal-title">Uso de Gift Cards</h3>
    <div class="policy-modal-content">
      <p>Nuestras Gift Cards son el regalo perfecto para compartir bienestar. A continuación, detallamos las condiciones de uso.</p>
      <h4>1. Validez y Vencimiento</h4>
      <p>Toda Gift Card emitida por Spa Infinity tiene una validez estricta de <strong>45 días corridos</strong> contados a partir de la fecha de compra. Una vez superado este plazo, el código quedará inactivo y no se admitirán extensiones ni reembolsos.</p>
      <h4>2. Canje y Agendamiento</h4>
      <p>Para hacer efectiva su Gift Card, el portador debe contactarnos por nuestros canales oficiales mencionando el código único de su tarjeta. El servicio está sujeto a la disponibilidad de agenda de nuestro Spa.</p>
      <h4>3. Condiciones Generales</h4>
      <p>La Gift Card no es canjeable por dinero en efectivo. Si el portador decide tomar un servicio de menor valor al monto de la Gift Card, no se entregará vuelto, pero el saldo restante puede quedar a favor para una próxima visita dentro de los 45 días de validez original.</p>
    </div>
  </div>
</div>`
}

function openPolicyModal(id) {
  document.getElementById(id).classList.add('active')
  document.body.style.overflow = 'hidden'
}
function closePolicyModal(id) {
  document.getElementById(id).classList.remove('active')
  document.body.style.overflow = 'auto'
}

/* ── FLOATING BUTTONS ───────────────────────────────── */
function renderFloating() {
  return `
<a href="${SITE.booking}" target="_blank" class="wa-fab" title="WhatsApp">
  <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.126 1.528 5.862L.057 23.26c-.073.277.015.573.228.768.163.149.373.228.587.228l5.745-1.469C8.094 23.501 10.03 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.891 0-3.736-.506-5.338-1.462l-.382-.225-3.305.851.875-3.214-.244-.394C2.582 15.85 2 13.977 2 12 2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/></svg>
</a>
<button class="back-top" id="backTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Volver arriba">↑</button>
<div id="promoFloatSlot"></div>`
}

/* ── PROMO FLOAT (async from Supabase) ─────────────── */
async function initPromoFloat() {
  if (typeof loadActivePromoEvent !== 'function') return
  try {
    const evt = await loadActivePromoEvent()
    if (!evt || !evt.active) return
    const cfg = evt.config
    const slot = document.getElementById('promoFloatSlot')
    if (!slot) return
    const icon = cfg.floatIcon || '♡'
    const text = cfg.floatText || 'Promoción Especial'
    slot.innerHTML = `
      <a href="promocion-dia-de-la-madre" class="promo-float" id="promoFloat" title="${text}">
        <span class="promo-float-icon">${icon}</span>
        <span class="promo-float-arrow">›</span>
        <span class="promo-float-text">${text}</span>
        <button class="promo-float-close" onclick="event.preventDefault();event.stopPropagation();document.getElementById('promoFloat').style.display='none'" aria-label="Cerrar">✕</button>
      </a>`
    // Mobile: toggle expand on tap
    const pf = document.getElementById('promoFloat')
    if (pf && window.innerWidth <= 860) {
      pf.addEventListener('click', function(e) {
        if (!pf.classList.contains('expanded')) {
          e.preventDefault()
          pf.classList.add('expanded')
        }
      })
    }
  } catch (e) {
    console.warn('Promo float init failed:', e)
  }
}

/* ── PROMO POPUP (once per session) ───────────────── */
async function initPromoPopup() {
  if (typeof loadActivePromoEvent !== 'function') return
  if (sessionStorage.getItem('spaPromoPopupSeen')) return
  try {
    const evt = await loadActivePromoEvent()
    if (!evt || !evt.active) return
    const cfg = evt.config
    if (!cfg.popupEnabled) return
    const img = cfg.popupImage || ''
    const text = cfg.popupText || ''
    const btnText = cfg.popupButtonText || 'Ver Promoción'
    const btnLink = cfg.popupButtonLink || 'promocion-dia-de-la-madre'
    if (!img && !text) return

    sessionStorage.setItem('spaPromoPopupSeen', '1')

    const overlay = document.createElement('div')
    overlay.className = 'promo-popup-overlay'
    overlay.innerHTML = `
      <div class="promo-popup">
        <button class="promo-popup-close" onclick="this.closest('.promo-popup-overlay').remove()" aria-label="Cerrar">✕</button>
        ${img ? `<img class="promo-popup-img" src="${img}" alt="Promoción" />` : ''}
        ${text || btnText ? `<div class="promo-popup-body">
          ${text ? `<p class="promo-popup-text">${text}</p>` : ''}
          ${btnLink ? `<a href="${btnLink}" class="promo-popup-btn">${btnText}</a>` : ''}
        </div>` : ''}
      </div>`
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove() })
    document.body.appendChild(overlay)
  } catch (e) {
    console.warn('Promo popup init failed:', e)
  }
}

/* ── SHARED JS ──────────────────────────────────────── */
function initShared() {
  const nav = document.getElementById('siteNav')
  const bt  = document.getElementById('backTop')
  initPromoFloat()
  initPromoPopup()
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', scrollY > 60)
    if (bt) bt.classList.toggle('show', scrollY > 400)
  })

  // Scroll animations
  const obs = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('vis') })
  }, { threshold: 0.07, rootMargin: '0px 0px -20px 0px' })
  document.querySelectorAll('.fu').forEach(el => obs.observe(el))

  // Live search with dropdown
  const searchInput = document.getElementById('navSearchInput')
  const searchDrop = document.getElementById('searchDropdown')
  if (searchInput && searchDrop) {
    function doSearch(q) {
      if (!q) { searchDrop.classList.remove('open'); return }
      const results = []
      const ql = q.toLowerCase()
      for (const cat of CATEGORIES) {
        for (const svc of cat.services) {
          if (svc.name.toLowerCase().includes(ql) || cat.name.toLowerCase().includes(ql) || svc.shortDesc.toLowerCase().includes(ql)) {
            results.push({ svc, cat })
          }
        }
        if (results.length >= 8) break
      }
      if (!results.length) {
        searchDrop.innerHTML = '<div class="sd-empty">No se encontraron servicios</div>'
      } else {
        searchDrop.innerHTML = results.slice(0, 8).map(r => `
          <a href="servicio.html?id=${r.svc.id}" class="sd-item">
            <div class="sd-info">
              <span class="sd-cat">${r.cat.name}</span>
              <span class="sd-name">${r.svc.name}</span>
            </div>
            <div class="sd-meta">
              ${r.svc.hasDiscount && r.svc.originalPrice ? `<span class="sd-dur" style="text-decoration:line-through">${r.svc.originalPrice}</span>` : ''}
              <span class="sd-price">${r.svc.price}</span>
              <span class="sd-dur">${r.svc.duration}</span>
              ${r.svc.hasDiscount && r.svc.discountLabel ? `<span style="background:#E74C3C;color:#fff;font-size:0.55rem;padding:1px 6px;border-radius:3px;font-weight:700">${r.svc.discountLabel}</span>` : ''}
            </div>
          </a>
        `).join('') + `<a href="servicios.html?q=${encodeURIComponent(q)}" class="sd-all">Ver todos los resultados →</a>`
      }
      searchDrop.classList.add('open')
    }
    searchInput.addEventListener('input', () => doSearch(searchInput.value.trim()))
    searchInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && searchInput.value.trim()) {
        searchDrop.classList.remove('open')
        window.location.href = `servicios.html?q=${encodeURIComponent(searchInput.value.trim())}`
      }
      if (e.key === 'Escape') searchDrop.classList.remove('open')
    })
    document.addEventListener('click', e => {
      if (!e.target.closest('.nav-search-container')) searchDrop.classList.remove('open')
    })
  }

  // Mobile search
  const mobInput = document.getElementById('mobSearchInput')
  const mobResults = document.getElementById('mobSearchResults')
  if (mobInput && mobResults) {
    function doMobSearch(q) {
      if (!q) { mobResults.innerHTML = ''; return }
      const results = []
      const ql = q.toLowerCase()
      for (const cat of CATEGORIES) {
        for (const svc of cat.services) {
          if (svc.name.toLowerCase().includes(ql) || cat.name.toLowerCase().includes(ql) || svc.shortDesc.toLowerCase().includes(ql)) {
            results.push({ svc, cat })
          }
        }
        if (results.length >= 8) break
      }
      if (!results.length) {
        mobResults.innerHTML = '<div class="mob-search-empty">No se encontraron servicios</div>'
      } else {
        mobResults.innerHTML = results.slice(0, 8).map(r => `
          <a href="servicio.html?id=${r.svc.id}" class="mob-search-item">
            <div class="mob-search-info">
              <span class="mob-search-cat">${r.cat.name}</span>
              <span class="mob-search-name">${r.svc.name}</span>
            </div>
            <div class="mob-search-meta">
              ${r.svc.hasDiscount && r.svc.originalPrice ? `<span style="text-decoration:line-through;opacity:0.5;font-size:0.75rem">${r.svc.originalPrice}</span>` : ''}
              <span class="mob-search-price">${r.svc.price}</span>
            </div>
          </a>
        `).join('')
      }
    }
    mobInput.addEventListener('input', () => doMobSearch(mobInput.value.trim()))
    mobInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' && mobInput.value.trim()) {
        toggleMobSearch()
        window.location.href = `servicios.html?q=${encodeURIComponent(mobInput.value.trim())}`
      }
    })
  }
}

function toggleMobSearch() {
  const overlay = document.getElementById('mobSearchOverlay')
  const input = document.getElementById('mobSearchInput')
  overlay.classList.toggle('open')
  document.body.style.overflow = overlay.classList.contains('open') ? 'hidden' : ''
  if (overlay.classList.contains('open')) {
    setTimeout(() => input.focus(), 100)
  } else {
    input.value = ''
    document.getElementById('mobSearchResults').innerHTML = ''
  }
}

function toggleMobMenu() {
  const m = document.getElementById('mobMenu')
  m.classList.toggle('open')
  document.body.style.overflow = m.classList.contains('open') ? 'hidden' : ''
  if (!m.classList.contains('open')) {
    // Reset to main panel on close
    document.getElementById('mobPanelMain').className = 'mob-panel mob-panel--main'
    document.getElementById('mobPanelCats').className = 'mob-panel mob-panel--sub'
    document.getElementById('mobPanelSvcs').className = 'mob-panel mob-panel--sub'
  }
}

function mobShowCats() {
  document.getElementById('mobPanelMain').classList.add('mob-panel--exit')
  document.getElementById('mobPanelCats').classList.add('mob-panel--active')
}

function mobBackToMain(e) {
  if (e) e.stopPropagation()
  document.getElementById('mobPanelCats').classList.remove('mob-panel--active')
  document.getElementById('mobPanelMain').classList.remove('mob-panel--exit')
}

function mobShowSvcs(catIdx) {
  const cat = CATEGORIES[catIdx]
  document.getElementById('mobSvcTitle').textContent = cat.name
  document.getElementById('mobSvcList').innerHTML =
    cat.services.map(s => `
      <a href="servicio.html?id=${s.id}" onclick="toggleMobMenu()" class="mob-svc-item">
        <div class="mob-svc-info">
          <span class="mob-svc-name">${s.name}</span>
          <span class="mob-svc-dur">${s.duration}</span>
        </div>
        <span class="mob-svc-price">${s.price}</span>
      </a>
    `).join('') +
    `<a href="categoria.html?cat=${cat.id}" onclick="toggleMobMenu()" class="mob-cat-all">Ver categoría completa →</a>`
  document.getElementById('mobPanelCats').classList.add('mob-panel--exit')
  document.getElementById('mobPanelSvcs').classList.add('mob-panel--active')
}

function mobBackToCats(e) {
  if (e) e.stopPropagation()
  document.getElementById('mobPanelSvcs').classList.remove('mob-panel--active')
  document.getElementById('mobPanelCats').classList.remove('mob-panel--exit')
}

/* ── Re-render navbar when Supabase data arrives ──── */
window.addEventListener('categories-updated', () => {
  const navEl = document.getElementById('navContainer')
  if (!navEl) return
  const activeLink = navEl.querySelector('.nav-link.active, a.active')
  const activePage = activeLink ? activeLink.textContent.trim() : ''
  navEl.innerHTML = renderNavbar(activePage)
  initShared()
})
