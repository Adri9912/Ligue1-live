import test from 'node:test'
import assert from 'node:assert/strict'
import { provisionalStandings, rankZone, matchAlerts } from '../src/football.js'
const a = { id: 1, name: 'Club A', code: 'A' }, b = { id: 2, name: 'Club B', code: 'B' }
const fixture = (id, status, hg, ag) => ({ id, status, home: a, away: b, homeGoals: hg, awayGoals: ag, date: `2026-10-${id === 1 ? '20' : '27'}T20:00:00Z` })
const official = [{ team: a, rank: 1, played: 2, points: 6 }, { team: b, rank: 2, played: 2, points: 0 }]

test('classement provisoire : aucun double comptage et recalcul après changement du score', () => {
  const original = structuredClone(official)
  const fixtures = [fixture(1, 'FT', 2, 0), fixture(2, '2H', 1, 0)]
  let rows = provisionalStandings(official, fixtures)
  assert.equal(rows[0].points, 6) // La référence contient déjà le match en cours : surtout pas 9.
  assert.equal(rows[0].played, 2); assert.equal(rows[0].liveMatchId, 2)
  fixtures[1].awayGoals = 2
  rows = provisionalStandings(official, fixtures)
  assert.equal(rows.find(r => r.team.id === 1).points, 3)
  assert.equal(rows.find(r => r.team.id === 2).points, 3)
  assert.equal(rows.reduce((s, r) => s + r.goalsFor, 0), rows.reduce((s, r) => s + r.goalsAgainst, 0))
  assert.deepEqual(official, original)
  assert.equal(provisionalStandings(official, [fixture(2, 'NS', null, null)])[0].played, 0)
  assert.equal(provisionalStandings(official, [fixture(2, '2H', null, null)])[0].played, 0)
})

test('repères : places européennes distinctes du barrage de maintien et des relégations', () => {
  assert.equal(rankZone(1), 'champions'); assert.equal(rankZone(3), 'champions')
  assert.equal(rankZone(4), 'champions-qualifier'); assert.equal(rankZone(5), 'europa')
  assert.equal(rankZone(6), 'conference'); assert.equal(rankZone(15), '')
  assert.equal(rankZone(16), 'playoff'); assert.equal(rankZone(17), 'relegation'); assert.equal(rankZone(18), 'relegation')
})

test('alertes : seulement le club favori, sans rattrapage ni faux but après une correction', () => {
  const options = { enabled: true, start: true, goals: true, final: true }
  const pending = fixture(2, 'NS', null, null), start = fixture(2, '1H', 0, 0), goal = fixture(2, '2H', 0, 1), end = fixture(2, 'FT', 0, 2)
  assert.deepEqual(matchAlerts([], [goal], 1, options), [])
  assert.deepEqual(matchAlerts([start], [goal], 3, options), [])
  assert.deepEqual(matchAlerts([start], [goal], 1, { ...options, enabled: false }), [])
  assert.equal(matchAlerts([pending], [start], 1, options)[0].type, 'start')
  assert.equal(matchAlerts([start], [goal], 1, options)[0].type, 'goals') // But adverse : le favori est aussi concerné.
  assert.deepEqual(matchAlerts([goal], [start], 1, options), [])
  assert.deepEqual(matchAlerts([goal], [goal], 1, options), [])
  assert.deepEqual(matchAlerts([goal], [end], 1, options).map(n => n.type), ['final'])
  assert.deepEqual(matchAlerts([goal], [end], 1, { ...options, final: false }).map(n => n.type), ['goals'])
  assert.deepEqual(matchAlerts([start], [goal], 1, { ...options, goals: false }), [])
})
