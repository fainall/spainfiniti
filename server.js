const express = require('express')
const crypto = require('crypto')
const path = require('path')
require('dotenv').config()

const app = express()
app.use(express.json())
app.use(express.urlencoded({ extended: true }))
app.use(express.static(path.join(__dirname)))

const FLOW_API_KEY = process.env.FLOW_API_KEY
const FLOW_SECRET_KEY = process.env.FLOW_SECRET_KEY
const FLOW_ENV = process.env.FLOW_ENV || 'sandbox'
const SITE_URL = process.env.SITE_URL || 'http://localhost:3000'

const FLOW_API_URL = FLOW_ENV === 'production'
  ? 'https://www.flow.cl/api'
  : 'https://sandbox.flow.cl/api'

const orders = new Map()

function flowSign(params) {
  const sorted = Object.keys(params).sort().map(k => `${k}${params[k]}`).join('')
  return crypto.createHmac('sha256', FLOW_SECRET_KEY).update(sorted).digest('hex')
}

async function flowPost(endpoint, params) {
  params.apiKey = FLOW_API_KEY
  params.s = flowSign(params)

  const body = new URLSearchParams()
  Object.entries(params).forEach(([k, v]) => body.append(k, v))

  const res = await fetch(`${FLOW_API_URL}${endpoint}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString()
  })

  if (!res.ok) {
    const text = await res.text()
    throw new Error(`Flow API error ${res.status}: ${text}`)
  }
  return res.json()
}

/* ── Create payment ────────────────────── */
app.post('/api/create-payment', async (req, res) => {
  try {
    const { service, price, duration, from, fromEmail, to, toEmail, message, code } = req.body

    if (!service || !price || !fromEmail || !to || !toEmail || !code) {
      return res.status(400).json({ error: 'Faltan campos obligatorios' })
    }

    const amount = parseInt(price.replace(/[^0-9]/g, ''))
    if (!amount || amount < 1) {
      return res.status(400).json({ error: 'Monto inválido' })
    }

    const commerceOrder = code
    orders.set(commerceOrder, {
      service, price, duration, from, fromEmail, to, toEmail, message, code,
      status: 'pending',
      createdAt: new Date().toISOString()
    })

    const params = {
      commerceOrder,
      subject: `Gift Card Spa Infinity — ${service}`,
      currency: 'CLP',
      amount: String(amount),
      email: fromEmail,
      urlConfirmation: `${SITE_URL}/api/flow/confirm`,
      urlReturn: `${SITE_URL}/api/flow/return`,
      optional: JSON.stringify({ to, toEmail, from: from, message: message || '' })
    }

    const data = await flowPost('/payment/create', params)

    res.json({
      success: true,
      paymentUrl: `${data.url}?token=${data.token}`,
      token: data.token
    })
  } catch (err) {
    console.error('Error creating payment:', err.message)
    res.status(500).json({ error: 'Error al crear el pago: ' + err.message })
  }
})

/* ── Flow confirmation (server-to-server) */
app.post('/api/flow/confirm', async (req, res) => {
  try {
    const { token } = req.body
    if (!token) return res.status(400).send('Missing token')

    const params = { token }
    params.apiKey = FLOW_API_KEY
    params.s = flowSign(params)

    const body = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => body.append(k, v))

    const response = await fetch(`${FLOW_API_URL}/payment/getStatus`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
    const data = await response.json()

    const order = orders.get(data.commerceOrder)
    if (order) {
      order.status = data.status === 2 ? 'paid' : 'failed'
      order.flowData = data
      console.log(`[Flow] Orden ${data.commerceOrder}: ${order.status}`)
    }

    res.send('OK')
  } catch (err) {
    console.error('Flow confirm error:', err.message)
    res.status(500).send('Error')
  }
})

/* ── Flow return (user redirect) ───────── */
app.get('/api/flow/return', async (req, res) => {
  try {
    const { token } = req.query
    if (!token) return res.redirect('/giftcard.html?status=error')

    const params = { token }
    params.apiKey = FLOW_API_KEY
    params.s = flowSign(params)

    const body = new URLSearchParams()
    Object.entries(params).forEach(([k, v]) => body.append(k, v))

    const response = await fetch(`${FLOW_API_URL}/payment/getStatus`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
    const data = await response.json()

    if (data.status === 2) {
      res.redirect(`/giftcard.html?status=success&order=${data.commerceOrder}`)
    } else {
      res.redirect(`/giftcard.html?status=failed&order=${data.commerceOrder}`)
    }
  } catch (err) {
    console.error('Flow return error:', err.message)
    res.redirect('/giftcard.html?status=error')
  }
})

/* ── Order status check ────────────────── */
app.get('/api/order/:code', (req, res) => {
  const order = orders.get(req.params.code)
  if (!order) return res.status(404).json({ error: 'Orden no encontrada' })
  res.json({ status: order.status, service: order.service, to: order.to })
})

const PORT = process.env.PORT || 3000
app.listen(PORT, () => {
  console.log(`Spa Infinity server running on http://localhost:${PORT}`)
  console.log(`Flow environment: ${FLOW_ENV}`)
})
