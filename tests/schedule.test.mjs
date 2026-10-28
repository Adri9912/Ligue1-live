import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { join } from 'node:path'
import { tmpdir } from 'node:os'
import { once } from 'node:events'
import { createProvider } from '../server/provider.mjs'
import { createApp } from '../server/index.mjs'
import { fixturesFresh, nextFixturesCheck, nextParisDay, completedSignature, standingsFresh, hour } from '../server/schedule.mjs'
const now = Date.parse('2026-10-27T10:00:00Z')
function fixture(date = '2026-10-27T19:00:00Z', status = 'NS') { return { fixture: { id: 1, date, status: { short: status, elapsed: null }, venue: {} }, teams: { home: { id: 1, name: 'A' }, away: { id: 2, name: 'B' } }, goals: { home: status === 'NS' ? null : 1, away: status === 'NS' ? null : 0 }, league: { round: 'Regular Season - 9' } } }

test('horaires : calendrier quotidien, passage au direct au coup d’envoi et contrôle toutes les 10 minutes', () => {
  const entry = { data: [fixture()], time: now }
  assert.equal(fixturesFresh(entry, now + hour), true)
  assert.equal(fixturesFresh(entry, now + hour, true), false)
  assert.equal(nextFixturesCheck(entry, now), Date.parse('2026-10-27T19:00:00Z'))
  const kickoff = Date.parse('2026-10-27T19:01:00Z')
  assert.equal(fixturesFresh(entry, kickoff), false)
  const live = { data: [fixture(undefined, '2H')], time: kickoff }
  assert.equal(fixturesFresh(live, kickoff + 9 * 60000), true)
  assert.equal(fixturesFresh(live, kickoff + 10 * 60000), false)
  assert.equal(nextFixturesCheck(live, kickoff), kickoff + 600000)
  assert.equal(fixturesFresh(entry, Date.parse('2026-10-28T10:00:00Z')), false)
})

test('jours de Paris : les limites quotidiennes suivent aussi les changements d’heure', () => {
  assert.equal(new Date(nextParisDay(Date.parse('2026-03-28T12:00:00Z'))).toISOString(), '2026-03-28T23:00:00.000Z')
  assert.equal(new Date(nextParisDay(Date.parse('2026-03-29T12:00:00Z'))).toISOString(), '2026-03-29T22:00:00.000Z')
  assert.equal(new Date(nextParisDay(Date.parse('2026-10-24T12:00:00Z'))).toISOString(), '2026-10-24T22:00:00.000Z')
  assert.equal(new Date(nextParisDay(Date.parse('2026-10-25T12:00:00Z'))).toISOString(), '2026-10-25T23:00:00.000Z')
})

test('classement : actualisation après un résultat, sans dépasser un appel par heure', () => {
  const entry = { time: now, signature: '' }, signature = completedSignature([fixture(undefined, 'FT')])
  assert.equal(standingsFresh(entry, signature, now + 30 * 60000), true)
  assert.equal(standingsFresh(entry, signature, now + hour), false)
  assert.equal(standingsFresh(entry, '', now + 3 * hour), true)
  assert.equal(standingsFresh(entry, '', now + 24 * hour), false)
})

test('serveur : Pause bloque les appels sans cache ; Manuel exige un clic et les joueurs ne sont chargés qu’une fois par jour', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-policy-'))
  let mode = 'pause', calls = 0, clock = now
  try {
    const p = await createProvider({ directory, key: 'test', season: 2026, getMode: () => mode, clock: () => clock, spacingMs: 0, fetcher: async url => { calls++; return { ok: true, headers: new Headers({ 'x-ratelimit-requests-remaining': String(100 - calls) }), json: async () => ({ errors: {}, response: url.pathname === '/fixtures' ? [fixture()] : [] }) } } })
    await assert.rejects(p.matches(), /pause/); assert.equal(calls, 0)
    mode = 'manual'; await assert.rejects(p.matches(), /Mode manuel/); assert.equal(calls, 0)
    await p.matches({ manual: true }); assert.equal(calls, 1)
    clock += 20 * 60000
    await p.matches(); assert.equal(calls, 1)
    await p.matches({ manual: true }); assert.equal(calls, 2)
    await p.players('scorers', { manual: true }); assert.equal(calls, 3)
    clock += hour
    await p.players('scorers', { manual: true }); assert.equal(calls, 3)
    mode = 'pause'; await p.matches({ manual: true }); assert.equal(calls, 3)
    assert.equal(p.status().budget.used, 3); assert.equal(p.status().budget.providerRemaining, 97)
    assert.equal(p.status().nextRefreshAt, null)
    clock = Date.parse('2026-10-28T10:00:00Z')
    assert.equal(p.status().budget.used, 0); assert.equal(p.status().budget.providerRemaining, null)
  } finally { await rm(directory, { recursive: true, force: true }) }
})

test('une pause annule aussi une demande attendant son tour, sans consommer son budget', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-queued-'))
  let mode = 'auto', calls = 0, release, waiting
  const reachedWait = new Promise(resolve => { waiting = resolve })
  try {
    const p = await createProvider({ directory, key: 'test', season: 2026, getMode: () => mode, clock: () => now, spacingMs: 6500, wait: () => { waiting(); return new Promise(resolve => { release = resolve }) }, fetcher: async () => { calls++; return { ok: true, json: async () => ({ errors: {}, response: [] }) } } })
    await p.matches()
    const second = p.players('assists')
    const rejected = assert.rejects(second, /interrompue/)
    await reachedWait; mode = 'pause'; release(); await rejected
    assert.equal(calls, 1); assert.equal(p.status().budget.used, 1)
  } finally { await rm(directory, { recursive: true, force: true }) }
})

test('réglages HTTP : budget réel, validation, protection de l’origine et conservation après redémarrage', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-control-'))
  let calls = 0
  const options = { directory, key: 'test', season: 2026, spacingMs: 0, fetcher: async () => { calls++; return { ok: true, json: async () => ({ errors: {}, response: [] }) } } }
  let server
  try {
    server = await createApp(options); server.listen(0, '127.0.0.1'); await once(server, 'listening')
    let base = `http://127.0.0.1:${server.address().port}`
    const control = (mode, headers = {}) => fetch(base + '/api/control', { method: 'POST', headers: { 'Content-Type': 'application/json', ...headers }, body: JSON.stringify({ mode }) })
    assert.equal((await control('invalid')).status, 400)
    assert.equal((await control('pause', { Origin: 'https://other.example' })).status, 403)
    assert.equal((await control('pause', { 'Content-Type': 'text/plain' })).status, 415)
    assert.equal((await (await control('pause')).json()).refreshMode, 'pause')
    assert.equal((await fetch(base + '/api/matches')).status, 409); assert.equal(calls, 0)
    await control('manual')
    assert.equal((await fetch(base + '/api/matches')).status, 409)
    const data = await (await fetch(base + '/api/matches', { headers: { 'x-l1-manual-refresh': '1' } })).json()
    assert.equal(data.budget.used, 1); assert.equal(calls, 1)
    await control('pause')
    await new Promise(resolve => server.close(resolve))
    server = await createApp(options); server.listen(0, '127.0.0.1'); await once(server, 'listening'); base = `http://127.0.0.1:${server.address().port}`
    const status = await (await fetch(base + '/api/status')).json()
    assert.equal(status.refreshMode, 'pause'); assert.equal(status.budget.used, 1)
    assert.equal((await fetch(base + '/api/players/scorers', { headers: { 'x-l1-manual-refresh': '1' } })).status, 409)
    assert.equal(calls, 1)
  } finally { if (server?.listening) await new Promise(resolve => server.close(resolve)); await rm(directory, { recursive: true, force: true }) }
})
