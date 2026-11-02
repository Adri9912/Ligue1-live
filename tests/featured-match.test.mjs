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
  assert.equal(selected.reason, 'Un seul point d’écart au classement. Deux équipes du top 5.')
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
  assert.equal(selected.reason, '')
  assert.equal(featuredMatch([match(9), match(3)], [], null, now).match.id, 3)
})


test('explication personnalisée : écart de points prioritaire, même à plus de trois places de distance', () => {
  const game = match(1)
  const table = [standing(1, 8, 12), standing(2, 12, 9)]
  assert.equal(featuredMatch([game], table, null, now).reason, 'Seulement 3 points d’écart au classement.')
  assert.match(featuredMatch([game], table, 1, now).reason, /Club 1, votre équipe favorite.*Seulement 3 points/)
  table[1].points = 12
  assert.equal(featuredMatch([game], table, null, now).reason, 'À égalité au classement : 12 points chacun.')
  table[0].points = table[1].points = 1
  assert.equal(featuredMatch([game], table, null, now).reason, 'À égalité au classement : 1 point chacun.')
  table[0].points = 12; table[1].points = 11
  assert.match(featuredMatch([game], table, null, now).reason, /Un seul point/)
  game.status = '1H'
  assert.match(featuredMatch([game], table, null, now).reason, /Un seul point/)
})

test('sans écart serré : positions réelles, classement partiel et aucun rang inventé', () => {
  const game = match(1)
  assert.equal(featuredMatch([game], [standing(1, 8, 18), standing(2, 12, 9)], null, now).reason, 'Club 1 (8e) reçoit Club 2 (12e).')
  assert.equal(featuredMatch([game], [standing(1, 1, 18)], null, now).reason, 'Club 1 occupe la première place du classement.')
  assert.equal(featuredMatch([game], [standing(1, 0, null)], null, now).reason, '')
})

test('forme : résultats réellement disponibles avant le coup d’envoi, sans match courant, futur ni score absent', () => {
  const current = match(99)
  const games = [current]
  for (let i=1;i<=5;i++) games.push({...match(i, `2026-10-${30-i}T19:00:00Z`, 'FT', 1, i+10), homeGoals: i<=3 ? 1 : 0, awayGoals: i<=3 ? 0 : 1})
  const table = [standing(1, 8, 18), standing(2, 12, 9)]
  const original = JSON.stringify(games)
  assert.equal(featuredMatch(games, table, null, now).reason, 'Club 1 : 3 victoires sur ses 5 derniers matchs renseignés.')
  assert.equal(JSON.stringify(games), original)
  games[1].awayGoals = null
  assert.equal(featuredMatch(games, table, null, now).reason, 'Club 1 (8e) reçoit Club 2 (12e).')
  const sameDay = [{...match(1, '2026-10-31T13:00:00Z', 'FT', 1, 20),homeGoals:1,awayGoals:0}]
  const kickoff = Date.parse('2026-10-31T11:00:00Z')
  const played = {...match(99, '2026-10-31T11:00:00Z', 'FT'),homeGoals:1,awayGoals:0}
  // Un résultat reçu après le début de la rencontre ne sert pas à raconter sa forme d’avant-match.
  assert.equal(featuredMatch([played,...sameDay], [], null, kickoff+3600000).reason, '')
})


const fullTable = () => Array.from({ length: 18 }, (_, i) => standing(i + 1, i + 1, 100 - (i + 1) * 5))
test('enjeux : première place, podium, top 5 et maintien seulement si une victoire permet de franchir le seuil', () => {
  const table = fullTable()
  table[1].points = 94
  assert.match(featuredMatch([match(1, undefined, 'NS', 1, 2)], table, null, now).reason, /Un duel pour la première place/)
  table[1].points = 92 // Trois points : égalité possible, première place non garantie.
  assert.doesNotMatch(featuredMatch([match(1, undefined, 'NS', 1, 2)], table, null, now).reason, /première place/)
  table[1].points = 94; table[2].points = 93
  assert.match(featuredMatch([match(1, undefined, 'NS', 2, 3)], table, null, now).reason, /première place en ligne de mire pour les deux clubs/)
  table[2].points = 85; table[3].points = 84
  assert.match(featuredMatch([match(1, undefined, 'NS', 4, 8)], table, null, now).reason, /Club 4 peut entrer provisoirement sur le podium/)
  table[5].points = 74
  assert.match(featuredMatch([match(1, undefined, 'NS', 6, 9)], table, null, now).reason, /top 5 à portée de victoire pour Club 6/)
  table[15].points = 24; table[16].points = 23; table[17].points = 22
  assert.match(featuredMatch([match(1, undefined, 'NS', 17, 18)], table, null, now).reason, /Club 17 peut sortir provisoirement de la zone rouge/)
  table[14].points = 26 // 23 + 3 égale 26 : aucun départage supposé.
  const reason = featuredMatch([match(1, undefined, 'NS', 17, 9)], table, null, now).reason
  assert.doesNotMatch(reason, /sortir provisoirement de la zone rouge/)
  assert.match(reason, /quitter les places de relégation directe/)
  assert.match(featuredMatch([match(1, undefined, 'NS', 17, 18)], fullTable(), null, now).reason, /bas de tableau/)
})

test('enjeux : aucun futur supposé pour un match joué ou en direct, ni avec un classement incomplet', () => {
  const table = fullTable(); table[1].points = 94
  for (const status of ['FT', '1H']) assert.doesNotMatch(featuredMatch([match(1, undefined, status, 1, 2)], table, null, now).reason, /Un duel pour la première place/)
  assert.doesNotMatch(featuredMatch([match(1, undefined, 'NS', 1, 2)], table.slice(0, 17), null, now).reason, /Un duel pour la première place/)
  table[5].rank = table[4].rank
  assert.doesNotMatch(featuredMatch([match(1, undefined, 'NS', 1, 2)], table, null, now).reason, /Un duel pour la première place/)
})

test('opposition attaque/défense et précédent duel : faits issus du classement et des scores existants', () => {
  const table = fullTable().map(row => ({ ...row, goalsFor: 10, goalsAgainst: 10 }))
  table[7].goalsFor = 30; table[9].goalsAgainst = 2
  assert.match(featuredMatch([match(1, undefined, 'NS', 8, 10)], table, null, now).reason, /meilleure attaque face à la meilleure défense/)
  table[6].goalsFor = 30 // Ex aequo : pas de superlatif exclusif.
  assert.doesNotMatch(featuredMatch([match(1, undefined, 'NS', 8, 10)], table, null, now).reason, /meilleure attaque/)
  const previous = { ...match(2, '2026-10-01T19:00:00Z', 'FT', 2, 1), homeGoals: 2, awayGoals: 0 }
  assert.match(featuredMatch([match(1), previous], [], null, now).reason, /Club 1 retrouve son adversaire après sa défaite/)
  previous.homeGoals = previous.awayGoals = 0
  assert.equal(featuredMatch([match(1), previous], [], null, now).reason, '')
})
