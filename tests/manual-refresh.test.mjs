import test from 'node:test'
import assert from 'node:assert/strict'
import 'vue'

test('actualisation manuelle : conserve la page, la saison et le compteur après la réponse API', async () => {
  const names = ['window', 'document', 'localStorage', 'fetch']
  const saved = names.map(name => Object.getOwnPropertyDescriptor(globalThis, name))
  const meta = { mode: 'api', source: 'highlightly', season: 2026, refreshMode: 'auto', budget: { remaining: 80 }, nextRefreshAt: null }
  const fixtures = { matches: [], fetchedAt: 10000, stale: false }
  const overview = { teams: [], standings: [], fetchedAt: 10000, stale: false }
  const allowance = { remaining: 9, limit: 10, resetAt: 1793404800000 }
  const calls = []
  const values = {
    window: {}, document: { documentElement: { dataset: { theme: 'light' } } },
    localStorage: { getItem: () => null, setItem: () => {} },
    fetch: async (path, options) => {
      calls.push({ path, method: options.method })
      const data = path === '/api/matches' ? { ...meta, ...fixtures }
        : path === '/api/overview' ? { ...meta, ...overview }
        : options.method === 'POST' ? { ...meta, fixtures, overview, manualRefresh: allowance }
        : { manualRefresh: allowance }
      return { ok: true, json: async () => data }
    },
  }
  for (const name of names) Object.defineProperty(globalThis, name, { configurable: true, value: values[name] })
  try {
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
    assert.deepEqual(calls.filter(c => c.method === 'POST'), [{ path: '/api/refresh', method: 'POST' }])
  } finally {
    names.forEach((name, i) => { if (saved[i]) Object.defineProperty(globalThis, name, saved[i]); else delete globalThis[name] })
  }
})
