import { join } from 'node:path'
import { createProvider, ApiError, normalizeTeam, normalizeFixture } from './provider.mjs'
import { fixturesFresh, nextFixturesCheck, completedSignature, standingsFresh, hour } from './schedule.mjs'
const leagueId = 52695 // Ligue 1 France, identifiant vérifié auprès de Highlightly.
const statuses = { 'Not started': 'NS', 'First half': '1H', 'Second half': '2H', 'Half time': 'HT', 'Extra time': 'ET', 'Break time': 'BT', Penalties: 'P', Finished: 'FT', 'Finished after penalties': 'PEN', 'Finished after extra time': 'AET', Postponed: 'PST', Suspended: 'SUSP', Cancelled: 'CANC', Awarded: 'AWD', Interrupted: 'INT', Abandoned: 'ABD', 'In progress': 'LIVE', Unknown: 'TBD', 'To be announced': 'TBD' }
export function highlightlyFixture(m) {
  const score = m.state?.score?.current?.match(/^(\d+)\s*-\s*(\d+)$/)
  return { fixture: { id: m.id, date: m.date, status: { short: statuses[m.state?.description] || 'TBD', elapsed: m.state?.clock ?? null }, venue: { name: m.venue?.name || null } }, teams: { home: m.homeTeam, away: m.awayTeam }, goals: { home: score ? Number(score[1]) : null, away: score ? Number(score[2]) : null }, league: { round: m.round } }
}
export function highlightlyTable(body) {
  const group = body.groups?.find(g => g.standings?.length === 18) || body.groups?.[0]
  if (!group?.standings?.length) throw new ApiError('Le classement Ligue 1 est indisponible chez Highlightly.')
  return group.standings.map(r => ({ rank: r.position, team: normalizeTeam(r.team), points: r.points, difference: r.total.scoredGoals - r.total.receivedGoals, form: '', played: r.total.games, wins: r.total.wins, draws: r.total.draws, losses: r.total.loses, goalsFor: r.total.scoredGoals, goalsAgainst: r.total.receivedGoals }))
}
export function highlightlyDetails(m) {
  const names = { Possession: 'Possession', 'Shots on target': 'Tirs cadrés', Corners: 'Corners', Fouls: 'Fautes', 'Yellow cards': 'Cartons jaunes', 'Red cards': 'Cartons rouges', 'Goalkeeper saves': 'Arrêts' }
  const value = (id, key) => {
    const n = m.statistics?.find(s => s.team.id === id)?.statistics?.find(s => s.displayName === key)?.value
    return n == null ? null : key === 'Possession' ? `${Math.round(n * 100)}%` : n
  }
  const events = (m.events || []).map(e => {
    const minute = String(e.time).match(/^(\d+)(?:\s*\+\s*(\d+))?/)
    const type = /Card/.test(e.type) ? 'Card' : e.type === 'Substitution' ? 'subst' : /Goal|Penalty/.test(e.type) ? 'Goal' : e.type
    return { minute: minute ? Number(minute[1]) : null, extra: minute?.[2] ? Number(minute[2]) : null, teamId: e.team?.id, player: e.player || 'Joueur non renseigné', type, detail: e.type === 'Own Goal' ? 'Own Goal' : e.type === 'Penalty missed' ? 'Missed Penalty' : e.type }
  })
  return { fixture: normalizeFixture(highlightlyFixture(m)), events, stats: Object.entries(names).map(([key, label]) => ({ label, home: value(m.homeTeam.id, key), away: value(m.awayTeam.id, key) })).filter(s => s.home !== null || s.away !== null) }
}
export function normalizeLineup(body, clubId) {
  const root = Array.isArray(body) ? body[0] : body
  const team = [root?.homeTeam, root?.awayTeam].find(t => t?.id === clubId)
  if (!team || typeof team.formation !== 'string' || !Array.isArray(team.initialLineup)) return null
  const shape = team.formation.split('-').map(Number)
  if (!shape.length || !shape.every(n => Number.isInteger(n) && n > 0 && n <= 5) || shape.reduce((a,b) => a+b,0) !== 10) return null
  const counts = [1, ...shape]
  if (team.initialLineup.length !== counts.length || team.initialLineup.some((row,i) => !Array.isArray(row) || row.length !== counts[i])) return null
  const players = team.initialLineup.flat()
  if (players.some(p => !p || typeof p.name !== 'string' || !p.name.trim()) || new Set(players.map(p => p.id ?? `${p.name}:${p.number}`)).size !== 11) return null
  const positions = { Goalkeeper:'Gardien', Defender:'Défenseur', Midfielder:'Milieu', Forward:'Attaquant', Attacker:'Attaquant' }
  return { formation:team.formation, rows:team.initialLineup.map(row => row.map(p => ({ id:p.id ?? null, name:p.name, number:p.number ?? null, position:positions[p.position] || 'Poste non renseigné' }))) }
}
export async function createHighlightlyProvider(options) {
  const clock = options.clock || Date.now
  const client = await createProvider({ ...options, directory: join(options.directory || '.cache', 'highlightly'), baseUrl: 'https://soccer.highlightly.net/', authHeader: 'x-rapidapi-key', decodeResponse: body => {
    if (!Array.isArray(body) && !Array.isArray(body.data) && !Array.isArray(body.groups) && !body.homeTeam && !body.awayTeam) throw new ApiError('Réponse Highlightly inattendue. Vérifiez votre accès Football API.')
    if (body.plan?.message && /hidden|restricted|limited/i.test(body.plan.message)) throw new ApiError('Highlightly indique que certaines données sont masquées par votre offre. Chargement incomplet refusé.')
    return body
  } })
  const params = { leagueId, season: options.season }
  let fixtures, table
  const service = {
    status() {
      const status = client.status()
      let next = nextFixturesCheck(fixtures, clock())
      if (table && !standingsFresh(table, completedSignature(fixtures?.data), clock())) next = Math.min(next, Math.max(clock(), table.time + hour))
      else if (table && table.signature !== completedSignature(fixtures?.data)) next = Math.min(next, table.time + hour)
      return { ...status, nextRefreshAt: status.nextRefreshAt === null ? null : next }
    },
    async matches(requestOptions = {}) {
      const pages = [], records = []
      let total = 1
      for (let offset = 0; offset < total; offset += 100) {
        const page = await client.request('matches', { ...params, limit: 100, offset }, entry => fixturesFresh({ time: entry.time, data: entry.data.data.map(highlightlyFixture) }, clock(), requestOptions.manual), requestOptions)
        if (!Array.isArray(page.data.data) || !Number.isInteger(page.data.pagination?.totalCount)) throw new ApiError('Calendrier Highlightly incomplet.')
        total = page.data.pagination.totalCount
        if (total > 1000 || (offset < total && !page.data.data.length)) throw new ApiError('Pagination Highlightly incohérente.')
        pages.push(page); records.push(...page.data.data)
      }
      if (!records.length) throw new ApiError(`Aucun match Ligue 1 disponible pour ${options.season}–${options.season + 1}.`)
      if (records.some(m => m.league?.id !== leagueId || m.league?.season !== options.season)) throw new ApiError('Le fournisseur a retourné une autre compétition ou saison.')
      const unique = [...new Map(records.map(m => [m.id, m])).values()]
      if (unique.length !== total) throw new ApiError('Calendrier incomplet : actualisez pour récupérer toutes les pages.')
      fixtures = { data: unique.map(highlightlyFixture), time: Math.min(...pages.map(p => p.fetchedAt)) }
      return { matches: fixtures.data.map(normalizeFixture).sort((a, b) => Date.parse(a.date) - Date.parse(b.date)), fetchedAt: fixtures.time, stale: pages.some(p => p.stale) }
    },
    async overview(requestOptions = {}) {
      const signature = completedSignature(fixtures?.data)
      const result = await client.request('standings', params, entry => standingsFresh(entry, signature, clock()), { ...requestOptions, signature })
      if (result.data.league?.id !== leagueId || result.data.league?.season !== options.season) throw new ApiError('Classement reçu pour une autre compétition ou saison.')
      const standings = highlightlyTable(result.data)
      table = { time: result.fetchedAt, signature: result.signature }
      return { standings, teams: standings.map(r => r.team), fetchedAt: result.fetchedAt, stale: result.stale }
    },
    async clubLineup(clubId, { offset = 0, ...requestOptions } = {}) {
      if (!Number.isInteger(offset) || offset < 0 || offset > 400) throw new ApiError('Position de recherche invalide.',400)
      const list = await service.matches(requestOptions)
      const clubGames = list.matches.filter(m => m.home.id === clubId || m.away.id === clubId)
      if (!clubGames.length) throw new ApiError('Club introuvable dans cette saison de Ligue 1.',404)
      const games = clubGames.filter(m => ['FT','AET','PEN'].includes(m.status) && Date.parse(m.date) <= clock()).sort((a,b) => Date.parse(b.date)-Date.parse(a.date) || b.id-a.id)
      const checkedMatchIds = []
      let stale = list.stale
      for (const match of games.slice(offset, offset + 3)) {
        const result = await client.request(`lineups/${match.id}`, {}, entry => clock() - entry.time < (normalizeLineup(entry.data,clubId) ? 30*86400000 : 6*hour), requestOptions)
        checkedMatchIds.push(match.id); stale = stale || result.stale
        const lineup = normalizeLineup(result.data,clubId)
        if (lineup) return { lineup:{...lineup,match}, checkedMatchIds, totalMatches:games.length, nextOffset:null, fetchedAt:result.fetchedAt, stale, lineupBudget:client.status().budget, lineupSource:'Highlightly' }
      }
      const next = offset + checkedMatchIds.length
      return { lineup:null, checkedMatchIds, totalMatches:games.length, nextOffset:next < games.length ? next : null, fetchedAt:clock(), stale, lineupBudget:client.status().budget, lineupSource:'Highlightly' }
    },
    async players() {
      return { players: [], unavailable: 'Highlightly ne fournit pas de classement global des buteurs et passeurs dans son API documentée. Aucun joueur d’une autre saison n’est affiché.', fetchedAt: clock(), stale: false }
    },
    async detail(id, requestOptions = {}) {
      const list = await service.matches(requestOptions)
      const fixture = list.matches.find(m => m.id === id)
      if (!fixture) throw new ApiError('Match introuvable en Ligue 1 pour cette saison.', 404)
      if (['NS', 'TBD', 'PST', 'CANC'].includes(fixture.status)) return { fixture, events: [], stats: [], fetchedAt: list.fetchedAt, stale: list.stale }
      const result = await client.request(`matches/${id}`, {}, 900000, requestOptions)
      const match = Array.isArray(result.data) ? result.data[0] : result.data.data?.[0]
      if (!match || match.id !== id || match.league?.id !== leagueId || match.league?.season !== options.season) throw new ApiError('Détail de match Highlightly incohérent.')
      return { ...highlightlyDetails(match), fetchedAt: result.fetchedAt, stale: result.stale || list.stale }
    },
  }
  return service
}
