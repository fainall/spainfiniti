// Pruebas de las fases 2 y 3 contra la base LOCAL. Antes: npx supabase db reset  ·  Uso: node dev/prueba-fase2.mjs .
import { execSync } from 'node:child_process'
const env = Object.fromEntries(execSync('npx supabase status -o env', { cwd: process.argv[2] }).toString()
  .split('\n').filter(l => l.includes('=')).map(l => { const i = l.indexOf('='); return [l.slice(0, i), l.slice(i + 1).replace(/^"|"$/g, '')] }))
const A = env.API_URL, K = env.ANON_KEY, MAIL = 'http://127.0.0.1:54324'
const sql = q => execSync(`docker exec -i supabase_db_spainfiniti psql -U postgres -tA`, { input: q }).toString().trim()
const j = async (url, o = {}) => { const r = await fetch(url, o); const t = await r.text(); try { return { s: r.status, b: JSON.parse(t) } } catch { return { s: r.status, b: t } } }
const H = t => ({ apikey: K, 'Content-Type': 'application/json', ...(t ? { Authorization: 'Bearer ' + t } : {}) })
const ok = (c, m) => console.log((c ? '  ✔ ' : '  ✘ ') + m)

async function registrar(email, password, data) {
  await j(`${A}/auth/v1/signup`, { method: 'POST', headers: H(), body: JSON.stringify({ email, password, data }) })
}
async function confirmar(email) {
  await new Promise(r => setTimeout(r, 800))
  const m = (await j(`${MAIL}/api/v1/search?query=to:${email}`)).b.messages[0]
  const txt = (await j(`${MAIL}/api/v1/message/${m.ID}`)).b.Text
  const link = txt.match(/https?:\/\/\S+verify\S+/)[0]
  await fetch(link, { redirect: 'manual' })
}
async function entrar(email, password) {
  return (await j(`${A}/auth/v1/token?grant_type=password`, { method: 'POST', headers: H(), body: JSON.stringify({ email, password }) })).b.access_token
}

console.log('1) Ana se registra con el correo de sus dos fichas duplicadas')
await registrar('ana.perez@ejemplo.cl', 'prueba-ana-1', { nombre: 'Ana', apellido: 'Pérez', telefono: '+56911112222' })
ok(sql(`select count(*) from clients where user_id is not null`) === '0', 'antes de confirmar el correo no se liga ninguna ficha')
await confirmar('ana.perez@ejemplo.cl')
ok(sql(`select count(*) from clients c join auth.users u on u.id=c.user_id where u.email='ana.perez@ejemplo.cl'`) === '2', 'al confirmar se ligan sus 2 fichas (una tenía el correo con mayúsculas y espacios)')
ok(sql(`select count(*) from appointments a join auth.users u on u.id=a.user_id where u.email='ana.perez@ejemplo.cl'`) === '3', 'y sus 3 reservas quedan con su cuenta')

const tAna = await entrar('ana.perez@ejemplo.cl', 'prueba-ana-1')
const citas = (await j(`${A}/rest/v1/rpc/mis_citas`, { method: 'POST', headers: H(tAna), body: '{}' })).b
ok(Array.isArray(citas) && citas.length === 3, 'mis_citas devuelve sus 3 citas')
ok(citas.every(c => !('notes' in c) && !('client_phone' in c)), 'sin notas internas ni teléfono: ' + Object.keys(citas[0]).join(', '))
ok(citas.filter(c => c.pasada).length === 2 && citas.filter(c => !c.pasada).length === 1, 'separa 2 pasadas y 1 próxima')
ok(!JSON.stringify(citas).includes('abono'), 'la nota "primera vez - abono 10 mil" no aparece')

console.log('2) Familia Soto: Marta se registra con el correo que comparte con Carlos')
await registrar('familia.soto@ejemplo.cl', 'prueba-marta-1', { nombre: 'Marta', apellido: 'Soto', telefono: '+56955556666' })
await confirmar('familia.soto@ejemplo.cl')
ok(sql(`select string_agg(c.name, ',') from clients c join auth.users u on u.id=c.user_id where u.email='familia.soto@ejemplo.cl'`) === 'Marta Soto', 'se liga solo la ficha de Marta')
ok(sql(`select c.name from vinculos_pendientes v join clients c on c.id=v.client_id`) === 'Carlos Soto', 'la de Carlos queda pendiente para que la revise el equipo')
const tMarta = await entrar('familia.soto@ejemplo.cl', 'prueba-marta-1')
const citasM = (await j(`${A}/rest/v1/rpc/mis_citas`, { method: 'POST', headers: H(tMarta), body: '{}' })).b
ok(citasM.length === 1 && citasM[0].servicio === 'Atención Podológica Integral', 'Marta ve solo su cita, no la de Carlos')

console.log('3) Alguien sin fichas se registra')
await registrar('nadie@ejemplo.cl', 'prueba-nadie-1', { nombre: 'Nadie', apellido: 'Nuevo' })
await confirmar('nadie@ejemplo.cl')
const tN = await entrar('nadie@ejemplo.cl', 'prueba-nadie-1')
const citasN = (await j(`${A}/rest/v1/rpc/mis_citas`, { method: 'POST', headers: H(tN), body: '{}' })).b
ok(Array.isArray(citasN) && citasN.length === 0, 'su historial está vacío (y no ve el de nadie más)')

console.log('4) Seguridad')
const directo = (await j(`${A}/rest/v1/appointments?select=id`, { headers: H(tAna) })).b
ok(Array.isArray(directo) && directo.length === 0, 'Ana no puede leer la tabla de reservas directamente')
const ajeno = await j(`${A}/rest/v1/rpc/vincular_fichas`, { method: 'POST', headers: H(tN), body: JSON.stringify({ p_user: sql(`select id from auth.users where email='ana.perez@ejemplo.cl'`) }) })
ok(ajeno.s >= 400, 'un cliente no puede ejecutar la vinculación para otra cuenta (' + ajeno.s + ')')
const anon = await j(`${A}/rest/v1/rpc/mis_citas`, { method: 'POST', headers: H(), body: '{}' })
ok(anon.s >= 400, 'sin sesión no hay historial (' + anon.s + ')')
const pend = (await j(`${A}/rest/v1/vinculos_pendientes?select=*`, { headers: H(tMarta) })).b
ok(Array.isArray(pend) && pend.length === 0, 'los clientes no ven la lista de pendientes')
const cambiaFicha = await j(`${A}/rest/v1/clients?id=eq.c0000000-0000-0000-0000-00000000000c`, { method: 'PATCH', headers: { ...H(tN), Prefer: 'return=representation' }, body: JSON.stringify({ user_id: sql(`select id from auth.users where email='nadie@ejemplo.cl'`) }) })
ok(Array.isArray(cambiaFicha.b) ? cambiaFicha.b.length === 0 : cambiaFicha.s >= 400, 'un cliente no puede adueñarse de una ficha ajena')

console.log('5) El equipo agenda una hora nueva a Ana desde el panel')
const tKeidy = await entrar('keidy@prueba.local', 'prueba1234')
const nueva = await j(`${A}/rest/v1/appointments`, { method: 'POST', headers: { ...H(tKeidy), Prefer: 'return=representation' }, body: JSON.stringify({
  professional_id: '11111111-1111-1111-1111-111111111111', client_id: 'c0000000-0000-0000-0000-00000000000b', client_name: 'Ana Perez',
  service_name: 'Perfilación de Cejas', appt_date: new Date(Date.now() + 6 * 864e5).toISOString().slice(0, 10), start_time: '12:00', end_time: '12:20',
  status: 'reserved', origen: 'panel', notes: 'nota interna del equipo' }) })
ok(nueva.s === 201, 'la reserva se crea desde el panel')
const citas2 = (await j(`${A}/rest/v1/rpc/mis_citas`, { method: 'POST', headers: H(tAna), body: '{}' })).b
ok(citas2.length === 4 && citas2.filter(c => !c.pasada).length === 2, 'aparece sola en el historial de Ana (la cuenta se toma de la ficha)')

console.log('6) Fase 3: reservar con la cuenta')
const sinSesion = await j(`${A}/rest/v1/rpc/reservar_cliente_cuenta`, { method: 'POST', headers: H(), body: '{}' })
ok(sinSesion.s >= 400, 'sin sesión no se puede usar reservar_cliente_cuenta (' + sinSesion.s + ')')
const fichaAna = (await j(`${A}/rest/v1/rpc/reservar_cliente_cuenta`, { method: 'POST', headers: H(tAna), body: '{}' })).b
ok(sql(`select count(*) from clients where id='${fichaAna}' and user_id=(select id from auth.users where email='ana.perez@ejemplo.cl')`) === '1', 'con cuenta devuelve una ficha de la propia cuenta')
ok(sql(`select count(*) from clients c join auth.users u on u.id=c.user_id where u.email='ana.perez@ejemplo.cl'`) === '2', 'no crea fichas duplicadas si ya tiene')
const malFono = await j(`${A}/rest/v1/rpc/reservar_cliente_cuenta`, { method: 'POST', headers: H(tN), body: JSON.stringify({ p_fono: '123' }) })
ok(malFono.s >= 400 && /tel[eé]fono/i.test(malFono.b.message || ''), 'sin teléfono en el perfil, uno inválido se rechaza: "' + (malFono.b.message || '') + '"')
const fichaN = (await j(`${A}/rest/v1/rpc/reservar_cliente_cuenta`, { method: 'POST', headers: H(tN), body: JSON.stringify({ p_fono: '9 6666 7777' }) })).b
ok(sql(`select email||'|'||(user_id is not null) from clients where id='${fichaN}'`) === 'nadie@ejemplo.cl|true', 'sin fichas, crea una con su correo y su cuenta')
ok(sql(`select telefono from profiles p join auth.users u on u.id=p.id where u.email='nadie@ejemplo.cl'`) === '+56966667777', 'y el teléfono queda guardado en su perfil')
