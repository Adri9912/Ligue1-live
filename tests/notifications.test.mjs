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
