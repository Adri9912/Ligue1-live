// Calculs purs partagés par l’interface et les tests.
export const liveStatuses = ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE', 'INT']
export const finishedStatuses = ['FT', 'AET', 'PEN', 'AWD', 'WO']
export const isLive = match => liveStatuses.includes(match.status)
export const isFinished = match => finishedStatuses.includes(match.status)

export function provisionalStandings(official, fixtures) {
  const rows = official.map(row => ({ ...row, previousRank: row.rank, played: 0, wins: 0, draws: 0, losses: 0, goalsFor: 0, goalsAgainst: 0, difference: 0, points: 0, form: '', liveMatchId: null }))
  for (const match of [...fixtures].sort((a, b) => new Date(a.date) - new Date(b.date))) {
    if ((!isFinished(match) && !isLive(match)) || !Number.isInteger(match.homeGoals) || !Number.isInteger(match.awayGoals)) continue
    for (const [teamId, scored, conceded] of [[match.home.id, match.homeGoals, match.awayGoals], [match.away.id, match.awayGoals, match.homeGoals]]) {
      const row = rows.find(r => r.team.id === teamId)
      if (!row) continue
      row.played++; row.goalsFor += scored; row.goalsAgainst += conceded
      if (scored > conceded) { row.wins++; row.points += 3; row.form += 'W' }
      else if (scored === conceded) { row.draws++; row.points++; row.form += 'D' }
      else { row.losses++; row.form += 'L' }
      row.difference = row.goalsFor - row.goalsAgainst
      if (isLive(match)) row.liveMatchId = match.id
    }
  }
  // Repartir des matchs évite de compter deux fois un direct déjà intégré par le fournisseur.
  // Les sanctions administratives et départages avancés ne sont pas déduits des scores.
  return rows.sort((a, b) => b.points - a.points || b.difference - a.difference || b.goalsFor - a.goalsFor || a.previousRank - b.previousRank).map((row, i) => ({ ...row, rank: i + 1 }))
}

export function rankZone(rank, total = 18) {
  if (rank <= 3) return 'champions'
  if (rank === 4) return 'champions-qualifier'
  if (rank === 5) return 'europa'
  if (rank === 6) return 'conference'
  if (rank > total - 2) return 'relegation'
  if (rank === total - 2) return 'playoff'
  return ''
}

export function matchAlerts(previous, current, favoriteId, settings) {
  if (!settings.enabled || !favoriteId) return []
  const alerts = []
  for (const match of current) {
    if (![match.home.id, match.away.id].includes(favoriteId)) continue
    const before = previous.find(m => m.id === match.id)
    if (!before) continue // Pas de rattrapage des anciens événements au premier chargement.
    const score = `${match.home.code} ${match.homeGoals ?? '–'} – ${match.awayGoals ?? '–'} ${match.away.code}`
    const base = { matchId: match.id, url: `/matchs/${match.id}`, body: score }
    const finished = !isFinished(before) && isFinished(match)
    if (settings.start && ['NS', 'TBD'].includes(before.status) && isLive(match)) alerts.push({ ...base, id: `${match.id}-start`, title: 'C’est parti !', type: 'start' })
    const goal = Number.isInteger(before.homeGoals) && Number.isInteger(before.awayGoals) && Number.isInteger(match.homeGoals) && Number.isInteger(match.awayGoals) && (match.homeGoals > before.homeGoals || match.awayGoals > before.awayGoals)
    if (settings.goals && goal && !isFinished(before) && (isLive(match) || isFinished(match)) && !(finished && settings.final)) alerts.push({ ...base, id: `${match.id}-score-${match.homeGoals}-${match.awayGoals}`, title: 'Le score a évolué', type: 'goals' })
    if (settings.final && finished) alerts.push({ ...base, id: `${match.id}-final`, title: 'Coup de sifflet final', type: 'final' })
  }
  return alerts
}
