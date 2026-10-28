import test from 'node:test'
import assert from 'node:assert/strict'
import { once } from 'node:events'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { createApp } from '../server/index.mjs'
import { createProvider, normalizeFixture, normalizeStats } from '../server/provider.mjs'

test('API démo : données explicites, liens de matchs cohérents et routes protégées', async () => {
  const server = await createApp({ key: '', season: 2026 })
  server.listen(0, '127.0.0.1'); await once(server, 'listening')
  const base = `http://127.0.0.1:${server.address().port}`
  try {
    const overview = await (await fetch(base + '/api/overview')).json()
    assert.equal(overview.mode, 'demo'); assert.equal(overview.teams.length, 18)
    assert.equal(overview.standings.length, 18)
    for (const row of overview.standings) { assert.equal(row.points, row.wins * 3 + row.draws); assert.equal(row.played, row.wins + row.draws + row.losses) }
    const list = await (await fetch(base + '/api/matches')).json()
    for (const fixture of list.matches) {
      const detail = await (await fetch(base + '/api/matches/' + fixture.id)).json()
      assert.equal(detail.fixture.id, fixture.id); assert.equal(detail.mode, 'demo')
      assert.ok(overview.teams.some(t => t.id === fixture.home.id)); assert.ok(overview.teams.some(t => t.id === fixture.away.id))
    }
    assert.equal((await fetch(base + '/api/matches/999999')).status, 404)
    assert.equal((await fetch(base + '/api/anything')).status, 404)
    assert.equal((await fetch(base + '/api/matches', { method: 'POST' })).status, 405)
    assert.equal((await fetch(base + '/api/matches/../.env')).status, 404)
    const pending = list.matches.find(f => f.status === 'NS')
    const detail = await (await fetch(base + '/api/matches/' + pending.id)).json()
    assert.deepEqual(detail.stats, []); assert.deepEqual(detail.events, [])
  } finally { await new Promise(resolve => server.close(resolve)) }
})

test('normalisation : scores nuls et statistiques absentes ne deviennent pas des zéros', () => {
  const fixture = normalizeFixture({ fixture: { id: 1, status: { short: 'NS', elapsed: null }, date: '2026-10-26', venue: {} }, teams: { home: { id: 1, name: 'A' }, away: { id: 2, name: 'B' } }, goals: { home: null, away: null }, league: { round: 'Regular Season - 9' } })
  assert.equal(fixture.round, 9); assert.equal(fixture.homeGoals, null)
  assert.deepEqual(normalizeStats([], fixture), [])
  assert.deepEqual(normalizeStats([{ team: { id: 1 }, statistics: [{ type: 'Total Shots', value: 0 }] }], fixture), [{ label: 'Tirs', home: 0, away: null }])
})

test('cache persistant, requêtes simultanées mutualisées et quota sans fuite de clé', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-test-'))
  let calls = 0
  const options = { directory, key: 'secret-test', season: 2026, dailyLimit: 1, fetcher: async url => {
    calls++; assert.ok(!String(url).includes('secret-test'))
    return { ok: true, json: async () => ({ errors: {}, response: [] }) }
  } }
  try {
    const p = await createProvider(options)
    const [a, b] = await Promise.all([p.matches(), p.matches()])
    assert.deepEqual(a, b); assert.equal(calls, 1)
    const rebooted = await createProvider(options)
    await rebooted.matches(); assert.equal(calls, 1)
    await assert.rejects(rebooted.overview(), error => error.status === 429)
    await assert.rejects(p.detail(123), /introuvable/)
  } finally { await rm(directory, { recursive: true, force: true }) }
})

test('une erreur du fournisseur reste une erreur et ne bascule jamais en démo', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-error-'))
  try {
    const p = await createProvider({ directory, key: 'invalid', season: 2026, fetcher: async () => ({ ok: true, json: async () => ({ errors: { token: 'bad' }, response: [] }) }) })
    await assert.rejects(p.matches(), /Accès API refusé/)
  } finally { await rm(directory, { recursive: true, force: true }) }
})
