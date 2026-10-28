import { mkdir, readFile, writeFile, rename } from 'node:fs/promises'
import { join } from 'node:path'
import { sameParisDay, fixturesFresh, nextFixturesCheck, nextParisDay, completedSignature, standingsFresh, hour, tenMinutes } from './schedule.mjs'
export class ApiError extends Error {
  constructor(message, status = 502) { super(message); this.status = status }
}
const labels = { 'Ball Possession': 'Possession', 'Total Shots': 'Tirs', 'Shots on Goal': 'Tirs cadrés', 'Corner Kicks': 'Corners', 'Fouls': 'Fautes', 'Yellow Cards': 'Cartons jaunes', 'Red Cards': 'Cartons rouges', 'Goalkeeper Saves': 'Arrêts' }
export function normalizeTeam(t) { return { id: t.id, name: t.name, code: t.code || t.name.slice(0, 3).toUpperCase(), logo: t.logo || null, color: '#365d88' } }
export function normalizeFixture(f) { return { id: f.fixture.id, home: normalizeTeam(f.teams.home), away: normalizeTeam(f.teams.away), status: f.fixture.status.short, elapsed: f.fixture.status.elapsed, homeGoals: f.goals.home, awayGoals: f.goals.away, date: f.fixture.date, round: Number(f.league.round?.match(/\d+$/)?.[0]) || null, venue: f.fixture.venue?.name || null } }
export function normalizeStandings(data) {
  return (data[0]?.league?.standings?.[0] || []).map(r => ({ rank: r.rank, team: normalizeTeam(r.team), points: r.points, difference: r.goalsDiff, form: r.form || '', played: r.all.played, wins: r.all.win, draws: r.all.draw, losses: r.all.lose, goalsFor: r.all.goals.for, goalsAgainst: r.all.goals.against }))
}
export function normalizeStats(data, fixture) {
  const home = data.find(s => s.team.id === fixture.home.id)?.statistics || []
  const away = data.find(s => s.team.id === fixture.away.id)?.statistics || []
  return Object.entries(labels).map(([key, label]) => ({ label, home: home.find(s => s.type === key)?.value ?? null, away: away.find(s => s.type === key)?.value ?? null })).filter(s => s.home !== null || s.away !== null)
}
export function normalizePlayers(data) {
  return data.flatMap(item => {
    const stats = item.statistics?.find(s => s.league?.id === 61)
    if (!stats?.team || !item.player?.id) return []
    return [{ id: item.player.id, name: item.player.name, team: normalizeTeam(stats.team), goals: stats.goals?.total ?? null, assists: stats.goals?.assists ?? null }]
  })
}
// ponytail: cache et compteur sur disque pour une seule instance Node ; stockage partagé si plusieurs instances.
export async function createProvider({ key, season, directory = '.cache', dailyLimit = 90, fetcher = fetch, getMode = () => 'auto', clock = Date.now, spacingMs = 6500, wait = ms => new Promise(resolve => setTimeout(resolve, ms)), baseUrl = 'https://v3.football.api-sports.io/', authHeader = 'x-apisports-key', decodeResponse }) {
  await mkdir(directory, { recursive: true })
  let disk = { cache: {}, day: '', used: 0 }
  try { disk = JSON.parse(await readFile(join(directory, 'football.json'), 'utf8')) } catch (e) { if (e.code !== 'ENOENT') console.warn('Cache illisible : nouveau cache.') }
  const inFlight = new Map()
  let queue = Promise.resolve(), lastCall = 0, saveQueue = Promise.resolve()
  const today = () => new Date(clock()).toISOString().slice(0, 10)
  const urlFor = (path, params) => { const url = new URL(path, baseUrl); Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, String(v))); return url }
  const seasonParams = { league: 61, season }
  const fixtureEntry = () => disk.cache[urlFor('fixtures', seasonParams).href]
  const tableEntry = () => disk.cache[urlFor('standings', seasonParams).href]
  function save() {
    const snapshot = JSON.stringify(disk)
    saveQueue = saveQueue.catch(() => {}).then(async () => { await writeFile(join(directory, 'football.tmp'), snapshot, { mode: 0o600 }); await rename(join(directory, 'football.tmp'), join(directory, 'football.json')) })
    return saveQueue
  }
  const canCall = manual => getMode() !== 'pause' && (getMode() === 'auto' || manual)
  function quota() {
    const day = today(), used = disk.day === day ? disk.used : 0
    const observed = disk.providerQuota?.day === day ? disk.providerQuota : null
    return { used, limit: dailyLimit, remaining: Math.max(0, dailyLimit - used), resetAt: Date.parse(`${day}T00:00:00Z`) + 86400000, providerRemaining: observed?.remaining ?? null, observedAt: observed?.observedAt ?? null }
  }
  function status() {
    const fixtures = fixtureEntry(), standings = tableEntry()
    let nextRefreshAt = nextFixturesCheck(fixtures, clock())
    if (standings && !standingsFresh(standings, completedSignature(fixtures?.data), clock())) nextRefreshAt = Math.min(nextRefreshAt, Math.max(clock(), standings.time + hour))
    else if (standings && standings.signature !== completedSignature(fixtures?.data)) nextRefreshAt = Math.min(nextRefreshAt, standings.time + hour)
    const budget = quota()
    return { budget, nextRefreshAt: getMode() === 'auto' && budget.remaining > 0 && budget.providerRemaining !== 0 ? nextRefreshAt : null }
  }
  async function request(path, params, fresh, { manual = false, signature } = {}) {
    const url = urlFor(path, params), cacheKey = url.href
    const cached = disk.cache[cacheKey]
    const valid = entry => typeof fresh === 'function' ? fresh(entry) : clock() - entry.time < fresh
    const cacheResult = () => ({ data: cached.data, fetchedAt: cached.time, stale: !valid(cached), signature: cached.signature })
    if (cached && (valid(cached) || !canCall(manual))) return cacheResult()
    if (!canCall(manual)) throw new ApiError(getMode() === 'pause' ? 'Actualisation en pause. Aucune donnée disponible en cache pour cette rubrique.' : 'Mode manuel : cliquez sur « Actualiser maintenant » pour récupérer les données.', 409)
    if (inFlight.has(cacheKey)) return inFlight.get(cacheKey)
    const task = queue.catch(() => {}).then(async () => {
      try {
        const pause = spacingMs - (clock() - lastCall)
        if (pause > 0) await wait(pause)
        // Relecture après l’attente : une pause bloque aussi les demandes déjà en file.
        if (!canCall(manual)) throw new ApiError('Actualisation interrompue par le réglage Manuel ou Pause.', 409)
        if (disk.day !== today()) { disk.day = today(); disk.used = 0 }
        if (disk.used >= dailyLimit || quota().providerRemaining === 0) throw new ApiError('Budget quotidien ou quota fournisseur atteint. Les données en cache restent disponibles.', 429)
        disk.used++; await save()
        if (!canCall(manual)) { disk.used--; await save(); throw new ApiError('Actualisation interrompue avant l’envoi.', 409) }
        lastCall = clock()
        const response = await fetcher(url, { headers: { [authHeader]: key }, signal: AbortSignal.timeout(20000) })
        const rawRemaining = response.headers?.get('x-ratelimit-requests-remaining')
        if (rawRemaining !== null && rawRemaining !== undefined && /^\d+$/.test(rawRemaining)) { disk.providerQuota = { day: today(), remaining: Number(rawRemaining), observedAt: clock() }; await save() }
        if (!response.ok) throw new ApiError(response.status === 429 ? 'Quota du fournisseur atteint. Réessayez plus tard.' : 'Le fournisseur de données est indisponible.', response.status === 429 ? 429 : 502)
        const body = await response.json()
        if (Object.keys(body.errors || {}).length) throw new ApiError('Accès API refusé : vérifiez la clé, le quota et les saisons incluses dans votre offre.', 502)
        const data = decodeResponse ? decodeResponse(body) : body.response
        if (data === undefined || (!decodeResponse && !Array.isArray(data))) throw new ApiError('Réponse inattendue du fournisseur.')
        disk.cache[cacheKey] = { data, time: clock(), ...(signature !== undefined ? { signature } : {}) }; await save()
        return { data, fetchedAt: disk.cache[cacheKey].time, stale: false, signature }
      } catch (error) {
        if (cached) return { data: cached.data, fetchedAt: cached.time, stale: true, signature: cached.signature }
        throw error instanceof ApiError ? error : new ApiError('Connexion au fournisseur impossible. Réessayez dans quelques instants.')
      }
    })
    queue = task; inFlight.set(cacheKey, task)
    try { return await task } finally { inFlight.delete(cacheKey) }
  }
  return {
    status, request,
    async overview(options = {}) {
      const dailyFresh = entry => sameParisDay(entry.time, clock())
      const coverage = await request('leagues', { id: 61, season }, dailyFresh, options)
      const available = coverage.data[0]?.seasons?.find(s => s.year === season)
      if (!available) throw new ApiError(`La saison ${season}–${season + 1} n'est pas accessible avec cette offre. Vérifiez les saisons autorisées dans votre compte API-Football.`, 422)
      const signature = completedSignature(fixtureEntry()?.data)
      const result = await request('standings', seasonParams, entry => standingsFresh(entry, signature, clock()), { ...options, signature })
      const standings = normalizeStandings(result.data)
      const clubData = await request('teams', seasonParams, dailyFresh, options)
      const teams = clubData.data.map(r => ({ ...normalizeTeam(r.team), founded: r.team.founded, stadium: r.venue?.name, city: r.venue?.city }))
      return { standings, teams, fetchedAt: result.fetchedAt, stale: result.stale || coverage.stale || clubData.stale }
    },
    async matches(options = {}) {
      const result = await request('fixtures', seasonParams, entry => fixturesFresh(entry, clock(), options.manual), options)
      return { matches: result.data.map(normalizeFixture), fetchedAt: result.fetchedAt, stale: result.stale }
    },
    async players(kind, options = {}) {
      if (!['scorers', 'assists'].includes(kind)) throw new ApiError('Classement inconnu.', 404)
      const result = await request(`players/${kind === 'scorers' ? 'topscorers' : 'topassists'}`, seasonParams, entry => sameParisDay(entry.time, clock()), options)
      const metric = kind === 'scorers' ? 'goals' : 'assists'
      return { players: normalizePlayers(result.data).filter(p => p[metric] !== null).sort((a, b) => b[metric] - a[metric]), fetchedAt: result.fetchedAt, stale: result.stale }
    },
    async detail(id, options = {}) {
      const list = await this.matches(options)
      const fixture = list.matches.find(f => f.id === id)
      if (!fixture) throw new ApiError('Match introuvable en Ligue 1 pour cette saison.', 404)
      if (['NS', 'TBD', 'PST', 'CANC'].includes(fixture.status)) return { fixture, events: [], stats: [], fetchedAt: list.fetchedAt, stale: list.stale }
      const events = await request('fixtures/events', { fixture: id }, 900000, options)
      const stats = await request('fixtures/statistics', { fixture: id }, 900000, options)
      return { fixture, events: events.data.map(e => ({ minute: e.time.elapsed, extra: e.time.extra, teamId: e.team.id, player: e.player?.name || 'Joueur non renseigné', type: e.type, detail: e.detail })), stats: normalizeStats(stats.data, fixture), fetchedAt: Math.min(list.fetchedAt, events.fetchedAt, stats.fetchedAt), stale: list.stale || events.stale || stats.stale }
    },
  }
}
