import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm, readFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { createHighlightlyProvider, highlightlyDetails, highlightlyFixture } from '../server/highlightly.mjs'
const team = { id: 1, name: 'Paris', logo: null }, other = { id: 2, name: 'Lyon' }
const match = id => ({ id, round: 'Regular Season - 8', date: '2026-10-26T19:30:00Z', state: { description: 'Finished', score: { current: '2 - 0' }, clock: 90 }, league: { id: 52695, season: 2026 }, homeTeam: team, awayTeam: other })

test('Highlightly : calendrier paginé, authentification privée, cache, pause, budget et classements indisponibles', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-hl-'))
  let mode = 'auto', calls = 0
  const options = { directory, key: 'secret-hl', season: 2026, spacingMs: 0, getMode: () => mode, clock: () => Date.parse('2026-10-27T12:00:00Z'), fetcher: async (url, init) => {
    calls++
    assert.equal(url.origin, 'https://soccer.highlightly.net'); assert.equal(init.headers['x-rapidapi-key'], 'secret-hl'); assert.ok(!url.href.includes('secret-hl'))
    const offset = Number(url.searchParams.get('offset'))
    return { ok: true, headers: new Headers({ 'x-ratelimit-requests-remaining': String(100 - calls) }), json: async () => ({ data: Array.from({ length: offset ? 1 : 100 }, (_, i) => match(offset + i + 1)), pagination: { totalCount: 101 }, plan: { message: 'All data available with current plan.' } }) }
  } }
  try {
    const p = await createHighlightlyProvider(options)
    const [a,b] = await Promise.all([p.matches(), p.matches()])
    assert.equal(calls, 2); assert.equal(a.matches.length, 101); assert.deepEqual(a,b)
    assert.equal(a.matches[0].homeGoals, 2); assert.equal(a.matches[0].round, 8)
    assert.equal(p.status().budget.used, 2)
    assert.equal((await p.players('scorers')).players.length, 0); assert.equal(calls, 2)
    mode = 'pause'
    const reboot = await createHighlightlyProvider(options)
    assert.equal((await reboot.matches()).matches.length, 101); assert.equal(calls, 2)
    await assert.rejects(reboot.detail(1), /pause/); assert.equal(calls, 2)
    assert.equal(reboot.status().nextRefreshAt, null)
    assert.ok(!(await readFile(join(directory, 'highlightly/football.json'), 'utf8')).includes('secret-hl'))
  } finally { await rm(directory, { recursive: true, force: true }) }
})

test('Highlightly : format des statistiques, scores absents et minutes additionnelles', () => {
  const m = match(1)
  m.events = [{ team, time: '90+2', type: 'Goal', player: 'A' }, {team, time: '40', type: 'Red Card', player: 'B'}]
  m.statistics = [{team, statistics:[{ displayName:'Possession', value:0.68 }, { displayName:'Corners', value:0 }]}]
  const d = highlightlyDetails(m)
  assert.deepEqual(d.events.map(e => [e.minute, e.extra, e.type]), [[90,2,'Goal'],[40,null,'Card']])
  assert.deepEqual(d.stats, [{label:'Possession',home:'68%',away:null},{label:'Corners',home:0,away:null}])
  m.state = { description:'Not started',score:{current:null} }
  assert.equal(highlightlyFixture(m).goals.home, null)
  assert.equal(highlightlyFixture(m).fixture.status.short, 'NS')
})

test('Highlightly : refuse une réponse partielle ou une mauvaise compétition', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'l1-hl-partial-'))
  try {
    const p = await createHighlightlyProvider({ directory, key:'test', season:2026, spacingMs:0, fetcher: async () => ({ok:true,json:async()=>({data:[match(1)],pagination:{totalCount:1},plan:{message:'Some results might be hidden with FREE tier.'}})}) })
    await assert.rejects(p.matches(), /masquées/)
  } finally { await rm(directory, {recursive:true,force:true}) }
})
