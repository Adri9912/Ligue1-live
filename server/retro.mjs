import { ApiError, normalizeFixture, normalizeStandings, normalizePlayers } from './provider.mjs'
export const retroSeasons = [2024, 2023, 2022]
const archiveTtl = 30 * 86400000
// Un seul client API-Football partagé entre saisons : même file et même quota.
export function createRetro(client) {
  const metadata = () => ({ available: !!client, seasons: retroSeasons, archiveSource: 'API-Football', retroBudget: client?.status().budget || null })
  return {
    metadata,
    async get(year, section, options) {
      if (!retroSeasons.includes(year)) throw new ApiError('Cette saison ne fait pas partie des archives proposées.', 404)
      if (!['standings', 'matches', 'scorers', 'assists'].includes(section)) throw new ApiError('Rubrique rétro introuvable.', 404)
      if (!client) throw new ApiError('Ajoutez la clé FOOTBALL_API_KEY dans le fichier privé .env pour accéder aux archives.', 503)
      const path = { standings: 'standings', matches: 'fixtures', scorers: 'players/topscorers', assists: 'players/topassists' }[section]
      const result = await client.request(path, { league: 61, season: year }, archiveTtl, options)
      let data
      if (section === 'standings') {
        const standings = normalizeStandings(result.data)
        if (!standings.length) throw new ApiError('Le classement de cette saison n’est pas disponible avec cette offre.', 422)
        data = { standings }
      } else if (section === 'matches') {
        const matches = result.data.filter(m => m.league?.round?.startsWith('Regular Season')).map(normalizeFixture)
        if (!matches.length) throw new ApiError('Les résultats de cette saison ne sont pas disponibles avec cette offre.', 422)
        data = { matches }
      } else {
        const metric = section === 'scorers' ? 'goals' : 'assists'
        data = { players: normalizePlayers(result.data).filter(p => p[metric] !== null).sort((a, b) => b[metric] - a[metric]) }
      }
      return { ...data, archiveSeason: year, archiveSource: 'API-Football', fetchedAt: result.fetchedAt, stale: result.stale, retroBudget: client.status().budget }
    },
  }
}
