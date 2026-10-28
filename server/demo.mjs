// Scénario fictif et figé : ni les scores, ni le classement ne sont des données réelles.
const clubs = [
  [85, 'Paris Saint-Germain', 'PSG', '#183b68', 'Parc des Princes', 1970],
  [81, 'Olympique de Marseille', 'OM', '#009bd7', 'Orange Vélodrome', 1899],
  [91, 'AS Monaco', 'ASM', '#cf2438', 'Stade Louis-II', 1924],
  [79, 'LOSC Lille', 'LOSC', '#c52c48', 'Stade Pierre-Mauroy', 1944],
  [80, 'Olympique Lyonnais', 'OL', '#264da0', 'Groupama Stadium', 1950],
  [116, 'RC Lens', 'RCL', '#b78215', 'Stade Bollaert-Delelis', 1906],
  [95, 'RC Strasbourg', 'RCSA', '#2780bd', 'Stade de la Meinau', 1906],
  [84, 'OGC Nice', 'OGCN', '#b52e36', 'Allianz Riviera', 1904],
  [94, 'Stade Rennais', 'SRFC', '#bd2939', 'Roazhon Park', 1901],
  [96, 'Toulouse FC', 'TFC', '#7151a1', 'Stadium de Toulouse', 1970],
  [106, 'Stade Brestois', 'SB29', '#c33b45', 'Stade Francis-Le Blé', 1950],
  [97, 'FC Lorient', 'FCL', '#dd7838', 'Stade du Moustoir', 1926],
  [83, 'FC Nantes', 'FCN', '#9b8d11', 'Stade de la Beaujoire', 1943],
  [108, 'AJ Auxerre', 'AJA', '#3172a3', 'Stade Abbé-Deschamps', 1905],
  [114, 'Paris FC', 'PFC', '#30496b', 'Stade Jean-Bouin', 1969],
  [111, 'Le Havre AC', 'HAC', '#4195b6', 'Stade Océane', 1872],
  [77, 'Angers SCO', 'SCO', '#5c6471', 'Stade Raymond-Kopa', 1919],
  [112, 'FC Metz', 'FCM', '#8d3047', 'Stade Saint-Symphorien', 1932],
]
export const teams = clubs.map(([id, name, code, color, stadium, founded]) => ({ id, name, code, color, stadium, founded, logo: null }))
const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Europe/Paris' })
function date(offset, hour) { const d = new Date(`${today}T12:00:00Z`); d.setUTCDate(d.getUTCDate() + offset); return `${d.toISOString().slice(0, 10)}T${hour}:00+01:00` }
function match(id, a, b, status, score, offset, hour, round = 9, elapsed = null) {
  return { id, home: teams[a], away: teams[b], status, homeGoals: score[0], awayGoals: score[1], date: date(offset, hour), round, elapsed, venue: teams[a].stadium }
}
export const matches = [
  match(9001, 1, 0, '2H', [1, 2], 0, '20:45', 9, 67),
  match(9002, 4, 2, 'HT', [0, 0], 0, '20:45', 9, 45),
  match(9003, 5, 3, 'FT', [2, 1], 0, '17:00'),
  match(9004, 6, 8, 'FT', [1, 1], 0, '15:00'),
  match(9005, 7, 9, 'NS', [null, null], 1, '19:00'),
  match(9006, 10, 12, 'NS', [null, null], 1, '17:00'),
  match(9007, 11, 13, 'NS', [null, null], 1, '17:00'),
  match(9008, 14, 15, 'NS', [null, null], 1, '17:00'),
  match(9009, 16, 17, 'NS', [null, null], 1, '17:00'),
  ...Array.from({ length: 8 }, (_, r) => {
    const rotation = [0, ...Array.from({ length: 17 }, (_, i) => 1 + (i + r) % 17)]
    return Array.from({ length: 9 }, (_, i) => match((r + 1) * 1000 + i, rotation[i], rotation[17 - i], 'FT', [(i + r) % 4, (i * 2 + r + 1) % 3], (r - 8) * 7, '17:00', r + 1))
  }).flat(),
  ...Array.from({ length: 9 }, (_, i) => match(10000 + i, (i * 2 + 1) % 18, (i * 2 + 2) % 18, 'NS', [null, null], 7, '17:00', 10)),
]
const table = teams.map(team => ({ team, played: 0, wins: 0, draws: 0, losses: 0, goalsFor: 0, goalsAgainst: 0, difference: 0, points: 0, form: '' }))
for (const fixture of [...matches].sort((a, b) => new Date(a.date) - new Date(b.date))) {
  if (fixture.status !== 'FT') continue
  for (const [team, scored, conceded] of [[fixture.home, fixture.homeGoals, fixture.awayGoals], [fixture.away, fixture.awayGoals, fixture.homeGoals]]) {
    const row = table.find(r => r.team.id === team.id)
    row.played++; row.goalsFor += scored; row.goalsAgainst += conceded
    if (scored > conceded) { row.wins++; row.points += 3; row.form += 'W' }
    else if (scored === conceded) { row.draws++; row.points++; row.form += 'D' }
    else { row.losses++; row.form += 'L' }
    row.difference = row.goalsFor - row.goalsAgainst
  }
}
export const standings = table.sort((a, b) => b.points - a.points || b.difference - a.difference || b.goalsFor - a.goalsFor).map((row, i) => ({ ...row, rank: i + 1 }))
export function demoDetails(id) {
  const fixture = matches.find(m => m.id === id)
  if (!fixture) return null
  const started = fixture.status !== 'NS'
  const events = []
  if (started) {
    for (let i = 0; i < (fixture.homeGoals ?? 0); i++) events.push({ minute: 23 + i * 20, teamId: fixture.home.id, player: ['A. Martin', 'T. Bernard'][i % 2], type: 'Goal', detail: 'But' })
    for (let i = 0; i < (fixture.awayGoals ?? 0); i++) events.push({ minute: 18 + i * 32, teamId: fixture.away.id, player: ['L. Moreau', 'N. Petit'][i % 2], type: 'Goal', detail: 'But' })
    events.push({ minute: 38, teamId: fixture.home.id, player: 'M. Laurent', type: 'Card', detail: 'Carton jaune' })
  }
  return { fixture, events: events.sort((a, b) => a.minute - b.minute), stats: started ? [
    { label: 'Possession', home: '46%', away: '54%' }, { label: 'Tirs', home: 9, away: 12 },
    { label: 'Tirs cadrés', home: 3, away: 5 }, { label: 'Corners', home: 4, away: 6 },
    { label: 'Fautes', home: 8, away: 10 }, { label: 'Cartons jaunes', home: 1, away: 0 },
  ] : [] }
}

// Noms et valeurs entièrement inventés pour les onglets joueurs.
export function demoPlayers(kind) {
  const names = ['A. Delcourt', 'I. Marchal', 'L. Ferrand', 'M. Bellanger', 'N. Aubert', 'S. Lemoine', 'T. Garnier', 'R. Dumas', 'E. Colin', 'Y. Mercier', 'H. Dumont', 'B. Fontaine']
  return names.map((name, i) => ({ id: 200 + i, name, team: teams[i], goals: Math.max(1, 10 - i), assists: 1 + (i * 3 + 2) % 8 })).sort((a, b) => kind === 'scorers' ? b.goals - a.goals : b.assists - a.assists)
}
