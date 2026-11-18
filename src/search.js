import { isLive } from './football.js'

function normalize(value) {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr').replace(/[^a-z0-9]+/g, ' ').trim()
}

function clubText(team) {
  return `${team.name || ''} ${team.fullName || ''} ${team.code || ''}`
}

export function searchSite(teams, fixtures, query, now = Date.now()) {
  const normalized = normalize(query)
  if (normalized.length < 2) return { clubs: [], matches: [] }
  const terms = normalized.split(' ')
  const includesTerms = text => terms.every(term => normalize(text).includes(term))
  const priority = match => {
    if (isLive(match)) return 0
    if (['NS', 'TBD'].includes(match.status) && Date.parse(match.date) >= now) return 1
    return 2
  }
  const clubs = teams.filter(team => includesTerms(clubText(team)))
    .sort((a, b) => a.name.localeCompare(b.name, 'fr')).slice(0, 6)
  const matches = fixtures.filter(match => Number.isFinite(Date.parse(match.date)) && includesTerms(`${clubText(match.home)} ${clubText(match.away)}`))
    .sort((a, b) => {
      const group = priority(a) - priority(b)
      if (group) return group
      const difference = Date.parse(a.date) - Date.parse(b.date)
      return priority(a) === 1 ? difference : -difference
    }).slice(0, 6)
  return { clubs, matches }
}
