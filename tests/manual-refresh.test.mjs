import test from 'node:test'
import assert from 'node:assert/strict'
import 'vue'

test('actualisation manuelle : conserve la page, la saison et le compteur après la réponse API', async () => {
  const names = ['window', 'document', 'localStorage', 'fetch']
  const saved = names.map(name => Object.getOwnPropertyDescriptor(globalThis, name))
  const meta = { mode: 'api', source: 'highlightly', season: 2026, refreshMode: 'auto', budget: { remaining: 80 }, nextRefreshAt: null }
  const fixtures = { matches: [], fetchedAt: 20000, stale: false }
  const overview = { teams: [], standings: [], fetchedAt: 10000, stale: false }
  const allowance = { remaining: 9, limit: 10, resetAt: 1793404800000 }
  const calls = []
  let postFailure = false
  const values = {
    window: {}, document: { documentElement: { dataset: { theme: 'light' } } },
    localStorage: { getItem: () => null, setItem: () => {} },
    fetch: async (path, options) => {
      calls.push({ path, method: options.method })
      const data = path === '/api/matches' ? { ...meta, ...fixtures }
        : path === '/api/overview' ? { ...meta, ...overview }
        : options.method === 'POST' ? { ...meta, fixtures, overview, manualRefresh: allowance }
        : { manualRefresh: allowance }
      if (postFailure && options.method === 'POST') return { ok: false, json: async () => ({ error: 'Accès refusé pour ce clic.' }) }
      return { ok: true, json: async () => data }
    },
  }
  for (const name of names) Object.defineProperty(globalThis, name, { configurable: true, value: values[name] })
  try {
    const { account } = await import('../src/account.js')
    account.ready = true; account.enabled = false
    const { state, load, refreshManually } = await import('../src/state.js')
    await load()
    assert.equal(state.mode, 'api')
    await refreshManually()
    assert.equal(state.mode, 'api') // RouterView doit rester visible après le clic.
    assert.equal(state.season, 2026)
    assert.equal(state.manualRefresh.remaining, 9)
    assert.equal(state.refreshMode, 'auto')
    assert.equal(state.refreshing, false)
    assert.equal(state.error, '')
    assert.equal(state.matchesFetchedAt, 20000)
    assert.equal(state.standingsFetchedAt, 10000)
    assert.equal(state.fetchedAt, 10000) // Compatibilité : la date globale reste la plus ancienne.
    assert.match(state.refreshNotice, /Vérifié à .*Données inchangées/)
    assert.equal(state.lastRefreshWasManual, true)
    assert.deepEqual(calls.filter(c => c.method === 'POST'), [{ path: '/api/refresh', method: 'POST' }])
    fixtures.fetchedAt = 30000
    await refreshManually()
    assert.match(state.refreshNotice, /Données plus récentes reçues : calendrier et scores/)
    overview.fetchedAt = 40000
    await refreshManually()
    assert.match(state.refreshNotice, /Données plus récentes reçues : classement/)
    fixtures.stale = true
    await refreshManually()
    assert.equal(state.matchesStale, true)
    assert.equal(state.standingsStale, false)
    assert.match(state.refreshNotice, /Mise à jour incomplète/)
    postFailure = true
    await refreshManually()
    assert.equal(state.refreshNotice, '')
    assert.equal(state.error, 'Accès refusé pour ce clic.')
    assert.equal(state.lastRefreshWasManual, false) // Un échec ne déclenche pas de recherches manuelles secondaires.
    assert.equal(state.matchesFetchedAt, 30000)
    postFailure = false
    fixtures.stale = false
    const before = calls.filter(c => c.method === 'POST').length
    const first = refreshManually(), duplicate = refreshManually()
    assert.equal(first, duplicate)
    await first
    assert.equal(calls.filter(c => c.method === 'POST').length, before + 1)

  } finally {
    names.forEach((name, i) => { if (saved[i]) Object.defineProperty(globalThis, name, saved[i]); else delete globalThis[name] })
  }
})
