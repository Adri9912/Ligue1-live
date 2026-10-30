import test from 'node:test'
import assert from 'node:assert/strict'

test('notifications : première ouverture silencieuse, détection puis dédoublonnage et désactivation', async () => {
  const a = { id: 1, name: 'Club A', code: 'A' }, b = { id: 2, name: 'Club B', code: 'B' }
  const old = { id: 1, status: 'NS', home: a, away: b, homeGoals: null, awayGoals: null }
  const current = { ...old, status: '2H', homeGoals: 1, awayGoals: 0 }
  const values = new Map([
    ['l1-alert-settings', JSON.stringify({ enabled: true, start: true, goals: true, final: true })],
    ['l1-alert-snapshot', JSON.stringify({ favorite: 1, matches: [old], seen: [] })],
  ])
  const names = ['window', 'navigator', 'localStorage']
  const descriptors = names.map(name => Object.getOwnPropertyDescriptor(globalThis, name))
  Object.defineProperty(globalThis, 'window', { configurable: true, value: {} })
  Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { locks: { request: async (_name, run) => run() } } })
  Object.defineProperty(globalThis, 'localStorage', { configurable: true, value: { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) } })
  try {
    const { alerts, observeMatches, resetAlertBaseline, toggleAlerts, allowSystemAlerts } = await import('../src/notifications.js')
    await observeMatches([current], 1, 'demo')
    assert.equal(alerts.items.length, 0) // Pas d’alerte pour un but survenu pendant la fermeture du site.
    const next = { ...current, homeGoals: 2 }
    await observeMatches([next], 1, 'demo')
    assert.equal(alerts.items.length, 1); assert.match(alerts.items[0].title, /^\[Démo\]/)
    await observeMatches([next], 1, 'demo')
    assert.equal(alerts.items.length, 1)
    await observeMatches([{ ...next, homeGoals: 3 }], 1, 'demo', true)
    assert.equal(alerts.items.length, 1) // Pas de notification à partir d’un cache périmé.
    resetAlertBaseline(2, [next]); assert.equal(alerts.items.length, 0)
    toggleAlerts(2, [next]); assert.equal(alerts.enabled, false)
    await observeMatches([{ ...next, homeGoals: 3 }], 2, 'demo')
    assert.equal(alerts.items.length, 0)
    assert.equal(JSON.parse(values.get('l1-alert-settings')).enabled, false)
    await allowSystemAlerts(); assert.equal(alerts.permission, 'unsupported')
  } finally {
    names.forEach((name, i) => { if (descriptors[i]) Object.defineProperty(globalThis, name, descriptors[i]); else delete globalThis[name] })
  }
})

test('notifications système : autorisation, début/but/fin, dédoublonnage et repli dans le site', async () => {
  const values = new Map([['l1-alert-settings', JSON.stringify({ enabled: true })]])
  const names = ['window', 'navigator', 'localStorage', 'Notification']
  const saved = names.map(name => Object.getOwnPropertyDescriptor(globalThis, name))
  const delivered = [], registrations = []
  let denied = false, broken = false
  class FakeNotification {
    static permission = 'default'
    static async requestPermission() { return this.permission = denied ? 'denied' : 'granted' }
  }
  const registration = { showNotification: async (title, options) => {
    if (broken) throw new Error('Notification unavailable')
    delivered.push({ title, ...options })
  } }
  const globals = {
    window: { isSecureContext: true, Notification: FakeNotification }, Notification: FakeNotification,
    navigator: { locks: { request: async (_name, run) => run() }, serviceWorker: {
      register: async path => { registrations.push(path); return registration }, ready: Promise.resolve(registration),
    } },
    localStorage: { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) },
  }
  for (const name of names) Object.defineProperty(globalThis, name, { configurable: true, value: globals[name] })
  try {
    const { alerts, allowSystemAlerts, testAlert, observeMatches } = await import('../src/notifications.js?system-notifications-test')
    await allowSystemAlerts()
    assert.equal(alerts.permission, 'granted')
    assert.deepEqual(registrations, ['/notifications-sw.js'])
    const home = { id: 1, name: 'Club A' }, away = { id: 2, name: 'Club B' }
    const pending = { id: 50, home, away, status: 'NS', homeGoals: null, awayGoals: null }
    const started = { ...pending, status: '1H', homeGoals: 0, awayGoals: 0 }
    const goal = { ...started, homeGoals: 1 }, finished = { ...goal, status: 'FT' }
    await observeMatches([pending], 1, 'demo')
    await observeMatches([started], 1, 'demo')
    await observeMatches([goal], 1, 'demo')
    await observeMatches([goal], 1, 'demo')
    await observeMatches([finished], 1, 'demo')
    assert.equal(delivered.length, 3)
    assert.ok(delivered.every(item => item.title.startsWith('[Démo] ') && item.data.url === '/matchs/50'))
    assert.equal(new Set(delivered.map(item => item.tag)).size, 3)
    broken = true
    await testAlert(home, false)
    assert.equal(alerts.items[0].title, 'Notification de test')
    assert.match(alerts.message, /visible dans le site/)
    denied = true
    await allowSystemAlerts()
    await testAlert(home, false)
    assert.equal(alerts.permission, 'denied')
    assert.equal(delivered.length, 3)
    assert.match(alerts.items[0].body, /Aucun événement réel/)
  } finally {
    names.forEach((name, i) => { if (saved[i]) Object.defineProperty(globalThis, name, saved[i]); else delete globalThis[name] })
  }
})
