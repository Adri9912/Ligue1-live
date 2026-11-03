import test from 'node:test'
import assert from 'node:assert/strict'
import { account, accountRequest, syncAccount } from '../src/account.js'

test('compte : session en cookie, CSRF des écritures et aucune donnée de connexion persistée', async t => {
  const original = globalThis.fetch
  t.after(() => { globalThis.fetch = original })
  account.csrfToken = 'csrf-test-only'
  let sent
  globalThis.fetch = async (url, options) => {
    sent = { url, ...options }
    return Response.json({ enabled: true, user: { id: 1, role: 'user' }, csrfToken: 'renewed-test-only' })
  }
  await accountRequest('/login', { method: 'POST', body: { email: 'test@example.test', password: 'test-only-password' } })
  assert.equal(sent.url, '/api/account/login')
  assert.equal(sent.credentials, 'same-origin')
  assert.equal(sent.headers['X-CSRF-TOKEN'], 'csrf-test-only')
  assert.equal(account.csrfToken, 'renewed-test-only')
  assert.equal(account.user.role, 'user')
  assert.equal('password' in account, false)
})

test('compte : erreurs de session, validation et limite de tentatives explicites', async t => {
  const original = globalThis.fetch
  t.after(() => { globalThis.fetch = original })
  for (const [status, payload, text] of [[419, {}, 'session a expiré'], [422, { errors: { email: ['Adresse invalide.'] } }, 'Adresse invalide'], [429, {}, 'Trop de tentatives']]) {
    account.user = { id: 1 }; account.csrfToken = 'test-only'
    globalThis.fetch = async () => Response.json(payload, { status })
    await assert.rejects(accountRequest('/login', { method: 'POST', body: {} }), e => e.status === status && e.message.includes(text))
    if (status === 419) { assert.equal(account.user, null); assert.equal(account.csrfToken, null) }
  }
})

test('compte : deux lectures simultanées partagent la même demande de session', async t => {
  const original = globalThis.fetch
  t.after(() => { globalThis.fetch = original })
  let calls = 0
  globalThis.fetch = async () => { calls++; return Response.json({ enabled: false, user: null }) }
  await Promise.all([syncAccount(), syncAccount()])
  assert.equal(calls, 1)
  assert.equal(account.ready, true)
  assert.equal(account.enabled, false)
  assert.equal(account.user, null)
})
