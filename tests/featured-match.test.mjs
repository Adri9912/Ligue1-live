import test from 'node:test'
import assert from 'node:assert/strict'
import { featuredMatch } from '../src/featuredMatch.js'
const now = Date.parse('2026-10-31T12:00:00Z')
const match = (id, date = '2026-10-31T19:00:00Z', status = 'NS', home = 1, away = 2) => ({ id, date, status, home: { id: home, name: `Club ${home}` }, away: { id: away, name: `Club ${away}` } })
const standing = (id, rank, points, played = 8) => ({ team: { id }, rank, points, played })
test('sélection : top 5, explication factuelle, ordre stable et aucune mutation', () => {
  const games = [match(2, undefined, 'NS', 3, 4), match(1)]
  const table = [standing(1, 1, 20), standing(2, 3, 19), standing(3, 12, 8), standing(4, 15, 4)]
  const before = JSON.stringify({ games, table })
  const selected = featuredMatch(games, table, null, now)
  assert.equal(selected.match.id, 1)
  assert.equal(selected.reason, 'Deux équipes du top 5. 1 point sépare les deux équipes.')
  assert.equal(featuredMatch([...games].reverse(), table, null, now).match.id, 1)
  assert.equal(JSON.stringify({ games, table }), before)
})
test('direct prioritaire et bonus favori modéré', () => {
  const live = match(1, undefined, '1H'), favorite = match(2, undefined, 'NS', 3, 4)
  assert.equal(featuredMatch([favorite, live], [], 3, now).match.id, 1)
  assert.equal(featuredMatch([match(1), favorite], [], 3, now).match.id, 2)
  assert.match(featuredMatch([favorite], [], 3, now).reason, /favorite/)
})
test('Classique reconnu dans les deux sens et aucune confusion avec Paris FC', () => {
  const classique = match(2)
  classique.home.name = 'Paris Saint Germain'; classique.away.name = 'Marseille'
  assert.match(featuredMatch([match(1), classique], [], null, now).reason, /Classique/)
  ;[classique.home, classique.away] = [classique.away, classique.home]
  assert.match(featuredMatch([classique], [], null, now).reason, /Classique/)
  classique.away.name = 'Paris FC'
  assert.doesNotMatch(featuredMatch([classique], [], null, now).reason, /Classique/)
})
test('sans match aujourd’hui : prochaine date seulement, avec le meilleur match de cette date', () => {
  const first = match(1, '2026-11-02T17:00:00Z')
  const sameDay = match(2, '2026-11-02T20:00:00Z', 'NS', 3, 4)
  const later = match(3, '2026-11-03T20:00:00Z', 'NS', 3, 4)
  const chosen = featuredMatch([later, sameDay, first], [], 3, now)
  assert.equal(chosen.match.id, 2)
  assert.equal(chosen.isToday, false)
})
test('annulations, reports, dates invalides et anciens matchs exclus ; fin de calendrier explicite', () => {
  const games = [match(1, undefined, 'CANC'), match(2, undefined, 'PST'), match(3, 'invalid'), match(4, '2026-10-30T19:00:00Z', 'FT')]
  assert.equal(featuredMatch(games, [], null, now), null)
  assert.equal(featuredMatch([], [], null, now), null)
  assert.equal(featuredMatch([match(5, undefined, 'FT')], [], null, now).isToday, true)
})
test('journée selon Paris, y compris autour du passage à minuit', () => {
  const chosen = featuredMatch([match(1, '2026-11-01T00:15:00+01:00')], [], null, Date.parse('2026-10-31T23:05:00Z'))
  assert.equal(chosen.isToday, true)
})
test('classement absent ou sans match joué : aucun enjeu ni classement inventé', () => {
  const selected = featuredMatch([match(1)], [standing(1, 1, 0, 0), standing(2, 2, 0, 0)], null, now)
  assert.equal(selected.reason, 'Une rencontre de Ligue 1 au programme aujourd’hui.')
  assert.equal(featuredMatch([match(9), match(3)], [], null, now).match.id, 3)
})
