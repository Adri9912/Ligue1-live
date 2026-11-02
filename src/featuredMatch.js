import { isLive, isFinished } from './football.js'
import { recentTeamResults } from './teamMatches.js'

// Même fuseau que le calendrier actuel ; aucune requête fournisseur.
const calendarDay = date => new Date(date).toLocaleDateString('en-CA', { timeZone: 'Europe/Paris' })
const clubName = name => name.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z]/g, '')
const isParis = name => ['parissaintgermain', 'psg'].includes(clubName(name))
const isMarseille = name => ['marseille', 'olympiquedemarseille'].includes(clubName(name))
const hasRank = row => row?.played > 0 && Number.isInteger(row.rank) && row.rank >= 1 && row.rank <= 18
const position = rank => `${rank}${rank === 1 ? 'er' : 'e'}`

function matchStakes(match, standings, now) {
  // Un enjeu de victoire exige un match à venir et un classement complet.
  // Une égalité de points ne suffit jamais à promettre une place : le départage reste inconnu.
  if (!['NS', 'TBD'].includes(match.status) || Date.parse(match.date) < now
    || standings.length !== 18 || new Set(standings.map(row => row.rank)).size !== 18
    || !standings.every(row => hasRank(row) && Number.isInteger(row.points))) return ''
  const teams = [match.home, match.away].map(team => ({ ...standings.find(row => row.team.id === team.id), team }))
  if (teams.some(row => !hasRank(row))) return ''
  const pointsAt = rank => standings.find(row => row.rank === rank).points
  const contender = (rank, eligible) => teams.filter(row => eligible(row) && row.points + 3 > pointsAt(rank))
  let clubs = contender(1, row => row.rank > 1)
  if (clubs.length) {
    if (teams.some(row => row.rank === 1)) return 'Un duel pour la première place.'
    return clubs.length === 2 ? 'La première place en ligne de mire pour les deux clubs.' : `${clubs[0].team.name} peut prendre provisoirement la tête en cas de victoire.`
  }
  clubs = contender(15, row => row.rank >= 16)
  if (clubs.length) return clubs.length === 2 ? 'La sortie de zone rouge en ligne de mire pour les deux clubs.' : `${clubs[0].team.name} peut sortir provisoirement de la zone rouge en cas de victoire.`
  clubs = contender(16, row => row.rank >= 17)
  if (clubs.length) return `${clubs[0].team.name} peut quitter les places de relégation directe en cas de victoire, selon les autres résultats.`
  if (teams.every(row => row.rank >= 14)) return 'Un duel entre deux équipes du bas de tableau.'
  clubs = contender(3, row => row.rank > 3)
  if (clubs.length) return clubs.length === 2 ? 'Le podium à portée de victoire pour les deux clubs.' : `${clubs[0].team.name} peut entrer provisoirement sur le podium en cas de victoire.`
  clubs = contender(5, row => row.rank > 5)
  return clubs.length ? `Le top 5 à portée de victoire pour ${clubs.map(row => row.team.name).join(' et ')}.` : ''
}

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
    let score = isLive(match) ? 40 : 0
    const reasons = []
    if (favoriteId && [match.home.id, match.away.id].includes(favoriteId)) {
      score += 8; reasons.push(`${match.home.id === favoriteId ? match.home.name : match.away.name}, votre équipe favorite, joue.`)
    }
    // Source LFP : https://ligue1.com/en/articles/l1_article_2088-le-classique-en-chiffres-l1
    if (isParis(match.home.name) && isMarseille(match.away.name) || isParis(match.away.name) && isMarseille(match.home.name)) {
      score += 30; reasons.push('Le Classique entre Paris et Marseille.')
    }
    const home = standings.find(row => row.team.id === match.home.id)
    const away = standings.find(row => row.team.id === match.away.id)
    const stakes = matchStakes(match, standings, now)
    if (stakes) reasons.unshift(stakes)
    if (home?.played > 0 && away?.played > 0) {
      if (Number.isInteger(home.points) && Number.isInteger(away.points)) {
        const gap = Math.abs(home.points - away.points)
        if (gap <= 3) {
          // Le bonus de sélection reste réservé aux clubs proches au classement.
          // L’écart de points reste pertinent même quand plusieurs places les séparent.
          if (hasRank(home) && hasRank(away) && Math.abs(home.rank - away.rank) <= 3) score += 10 - gap
          reasons.push(gap === 0 ? `À égalité au classement : ${home.points} point${home.points === 1 ? '' : 's'} chacun.`
            : gap === 1 ? 'Un seul point d’écart au classement.' : `Seulement ${gap} points d’écart au classement.`)
        }
      }
      const top = row => hasRank(row) && row.rank <= 5
      if (top(home) && top(away)) { score += 20; reasons.push('Deux équipes du top 5.') }
      else if (top(home) || top(away)) score += 6
    }
    if (reasons.length < 2 && standings.length === 18 && standings.every(row => row.played > 0 && Number.isInteger(row.goalsFor) && row.goalsFor >= 0 && Number.isInteger(row.goalsAgainst) && row.goalsAgainst >= 0)) {
      const attack = Math.max(...standings.map(row => row.goalsFor)), defense = Math.min(...standings.map(row => row.goalsAgainst))
      const bestAttack = standings.filter(row => row.goalsFor === attack), bestDefense = standings.filter(row => row.goalsAgainst === defense)
      if (bestAttack.length === 1 && bestDefense.length === 1 && home && away
        && (home === bestAttack[0] && away === bestDefense[0] || away === bestAttack[0] && home === bestDefense[0])) reasons.push('La meilleure attaque face à la meilleure défense du championnat.')
    }
    if (reasons.length < 2) {
      const before = Math.min(now, Date.parse(match.date))
      const form = [match.home, match.away].map(team => {
        const results = recentTeamResults(matches, team.id, { before, limit: 5 })
        return { team, games: results.length, wins: results.filter(result => result.result === 'W').length }
      }).filter(form => form.games >= 3 && form.wins >= 3)
        .sort((a, b) => b.wins / b.games - a.wins / a.games || b.games - a.games)
      if (form.length) reasons.push(`${form[0].team.name} : ${form[0].wins} victoires sur ses ${form[0].games} derniers matchs renseignés.`)
    }
    if (reasons.length < 2) {
      const before = Math.min(now, Date.parse(match.date))
      const last = recentTeamResults(matches.filter(game => [game.home.id, game.away.id].includes(match.away.id)), match.home.id, { before, limit: 1 })[0]
      if (last && last.result !== 'D') {
        const loser = last.result === 'L' ? match.home : match.away
        reasons.push(`${loser.name} retrouve son adversaire après sa défaite lors de leur dernier duel.`)
      }
    }
    if (!reasons.length) {
      if (hasRank(home) && hasRank(away)) reasons.push(`${match.home.name} (${position(home.rank)}) reçoit ${match.away.name} (${position(away.rank)}).`)
      else {
        const row = hasRank(home) ? home : hasRank(away) ? away : null
        if (row) reasons.push(`${row === home ? match.home.name : match.away.name} occupe la ${row.rank === 1 ? 'première' : position(row.rank)} place du classement.`)
      }
    }
    return { match, score, reason: reasons.slice(0, 2).join(' '), isToday }
  })
  return ranked.sort((a, b) => b.score - a.score || Date.parse(a.match.date) - Date.parse(b.match.date) || a.match.id - b.match.id)[0]
}
