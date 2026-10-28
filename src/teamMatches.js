import { isLive, isFinished } from './football.js'
export function nextTeamMatch(matches, now) {
  return matches.filter(match => isLive(match) || ['NS', 'TBD'].includes(match.status) && Number.isFinite(Date.parse(match.date)) && Date.parse(match.date) >= now - 4 * 3600000)
    .sort((a,b) => Number(isLive(b)) - Number(isLive(a)) || Date.parse(a.date) - Date.parse(b.date))[0] || null
}
export function kickoffCountdown(date, now) {
  const delta = Date.parse(date) - now
  if (!Number.isFinite(delta)) return 'Horaire à confirmer'
  if (delta <= 0) return 'Coup d’envoi attendu'
  const minutes = Math.ceil(delta / 60000), days = Math.floor(minutes / 1440), hours = Math.floor(minutes % 1440 / 60)
  if (days) return `Dans ${days} j ${hours} h`
  if (hours) return `Dans ${hours} h ${minutes % 60} min`
  return `Dans ${minutes} min`
}

// Les scores absents et les matchs non terminés ne comptent jamais comme des nuls.
export function recentTeamResults(matches, teamId, { before = Date.now(), limit = 5 } = {}) {
  return matches.filter(match => [match.home.id, match.away.id].includes(teamId) && isFinished(match)
    && Number.isInteger(match.homeGoals) && match.homeGoals >= 0 && Number.isInteger(match.awayGoals) && match.awayGoals >= 0
    && Number.isFinite(Date.parse(match.date)) && Date.parse(match.date) < before)
    .sort((a,b) => Date.parse(b.date) - Date.parse(a.date)).slice(0,limit)
    .map(match => {
      const home = match.home.id === teamId, scored = home ? match.homeGoals : match.awayGoals, conceded = home ? match.awayGoals : match.homeGoals
      return { match, scored, conceded, result: scored > conceded ? 'W' : scored === conceded ? 'D' : 'L' }
    })
}
export function previousMeetings(matches, fixture, limit = 5) {
  const before = Math.min(Date.now(), Date.parse(fixture.date))
  return recentTeamResults(matches.filter(match => match.id !== fixture.id && [match.home.id, match.away.id].includes(fixture.away.id)), fixture.home.id, { before, limit })
}
