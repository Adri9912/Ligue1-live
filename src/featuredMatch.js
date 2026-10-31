import { isLive, isFinished } from './football.js'

// Même fuseau que le calendrier actuel ; aucune requête fournisseur.
const calendarDay = date => new Date(date).toLocaleDateString('en-CA', { timeZone: 'Europe/Paris' })
const clubName = name => name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z]/g, '')
const isParis = name => ['parissaintgermain', 'psg'].includes(clubName(name))
const isMarseille = name => ['marseille', 'olympiquedemarseille'].includes(clubName(name))

export function featuredMatch(matches, standings, favoriteId, now = Date.now()) {
  const today = calendarDay(now)
  const eligible = matches.filter(match => Number.isFinite(Date.parse(match.date))
    && (isLive(match) || isFinished(match) || ['NS', 'TBD'].includes(match.status)))
  let candidates = eligible.filter(match => calendarDay(match.date) === today)
  const isToday = candidates.length > 0
  if (!isToday) {
    const future = eligible.filter(match => ['NS', 'TBD'].includes(match.status) && Date.parse(match.date) > now)
      .sort((a, b) => Date.parse(a.date) - Date.parse(b.date) || a.id - b.id)
    if (!future.length) return null
    const nextDay = calendarDay(future[0].date)
    candidates = future.filter(match => calendarDay(match.date) === nextDay)
  }
  const ranked = candidates.map(match => {
    let score = 0
    const reasons = []
    if (isLive(match)) { score += 40; reasons.push('Le match est en cours.') }
    // Source LFP : https://ligue1.com/en/articles/l1_article_2088-le-classique-en-chiffres-l1
    if (isParis(match.home.name) && isMarseille(match.away.name) || isParis(match.away.name) && isMarseille(match.home.name)) {
      score += 30; reasons.push('Le Classique entre Paris et Marseille.')
    }
    const home = standings.find(row => row.team.id === match.home.id)
    const away = standings.find(row => row.team.id === match.away.id)
    if (home?.played > 0 && away?.played > 0) {
      const top = row => Number.isInteger(row.rank) && row.rank >= 1 && row.rank <= 5
      if (top(home) && top(away)) { score += 20; reasons.push('Deux équipes du top 5.') }
      else if (top(home) || top(away)) { score += 6; reasons.push(`${top(home) ? match.home.name : match.away.name} figure dans le top 5.`) }
      if (Number.isInteger(home.points) && Number.isInteger(away.points) && Math.abs(home.rank - away.rank) <= 3) {
        const gap = Math.abs(home.points - away.points)
        if (gap <= 3) {
          score += 10 - gap
          reasons.push(gap === 0 ? 'Les deux équipes ont le même nombre de points.' : `${gap} point${gap > 1 ? 's' : ''} sépare${gap > 1 ? 'nt' : ''} les deux équipes.`)
        }
      }
    }
    if (favoriteId && [match.home.id, match.away.id].includes(favoriteId)) {
      score += 8; reasons.unshift('Votre équipe favorite joue.')
    }
    return { match, score, reason: reasons.slice(0, 2).join(' ') || (isToday ? 'Une rencontre de Ligue 1 au programme aujourd’hui.' : 'Une rencontre de la prochaine date au calendrier.'), isToday }
  })
  return ranked.sort((a, b) => b.score - a.score || Date.parse(a.match.date) - Date.parse(b.match.date) || a.match.id - b.match.id)[0]
}
