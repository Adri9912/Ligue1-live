import { reactive, computed } from 'vue'
import { liveStatuses, finishedStatuses, isLive, isFinished } from './football'
import { observeMatches, resetAlertBaseline } from './notifications'
export { liveStatuses, finishedStatuses, isLive, isFinished }
function read(key, fallback) { try { return localStorage.getItem(key) || fallback } catch { return fallback } }
export const state = reactive({
  mode: null, source: null, season: null, teams: [], standings: [], matches: [], fetchedAt: null, stale: false,
  refreshMode: 'auto', budget: null, nextRefreshAt: null, controlReady: false, refreshSerial: 0, lastRefreshWasManual: false,
  loading: true, refreshing: false, error: '', favorite: Number(read('l1-favorite', '0')) || null,
  matchView: read('l1-match-view', 'cards') === 'compact' ? 'compact' : 'cards',
  theme: document.documentElement.dataset.theme || 'light', pickerOpen: false, storageError: '',
})
export const favoriteTeam = computed(() => state.teams.find(t => t.id === state.favorite))
export function chooseFavorite(id) { state.favorite = id; persist('l1-favorite', id || ''); persist('l1-favorite-source', state.source || 'demo'); state.pickerOpen = false; resetAlertBaseline(id, state.matches) }
function persist(key, value) { try { localStorage.setItem(key, String(value)) } catch { state.storageError = 'Le navigateur ne permet pas de mémoriser vos préférences. Elles restent actives pendant cette visite.' } }
export function setMatchView(value) { state.matchView = value === 'compact' ? 'compact' : 'cards'; persist('l1-match-view', state.matchView) }
export function toggleTheme() { state.theme = state.theme === 'dark' ? 'light' : 'dark'; document.documentElement.dataset.theme = state.theme; persist('l1-theme', state.theme) }
export async function api(path, { manual = false, method = 'GET', body } = {}) {
  const response = await fetch(path, { method, signal: AbortSignal.timeout(90000), headers: { ...(manual ? { 'x-l1-manual-refresh': '1' } : {}), ...(body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) })
  let data
  try { data = await response.json() } catch { throw new Error('Le serveur ne répond pas correctement. Vérifiez qu’il est démarré.') }
  if (data.refreshMode) { state.refreshMode = data.refreshMode; state.budget = data.budget; state.nextRefreshAt = data.nextRefreshAt; state.controlReady = true }
  if (!response.ok) throw new Error(data.error || 'Impossible de charger les données.')
  return data
}
let pending
export function load({ manual = false } = {}) {
  if (pending) return pending
  state.refreshing = true; state.error = ''
  pending = (async () => {
    // Les résultats sont récupérés d’abord pour savoir si le classement doit être mis à jour.
    const fixtures = await api('/api/matches', { manual })
    const overview = await api('/api/overview', { manual })
    Object.assign(state, { teams: overview.teams, standings: overview.standings, matches: fixtures.matches, mode: overview.mode, season: overview.season, fetchedAt: Math.min(overview.fetchedAt, fixtures.fetchedAt), stale: overview.stale || fixtures.stale })
    observeMatches(fixtures.matches, state.favorite, state.mode, fixtures.stale).catch(() => {})
  })().catch(error => { state.error = error.name === 'TimeoutError' ? 'Le chargement a pris trop de temps. Vous pouvez réessayer.' : error.message }).finally(() => { state.loading = false; state.refreshing = false; state.lastRefreshWasManual = manual; state.refreshSerial++; pending = null })
  return pending
}
export async function syncStatus() {
  const data = await api('/api/status')
  if (state.source !== data.source && read('l1-favorite-source', 'api-football') !== data.source) { state.favorite = null; persist('l1-favorite', ''); resetAlertBaseline(null, []) }
  state.source = data.source; state.mode = data.mode; state.season = data.season
  return data
}
export async function setRefreshMode(mode) {
  await api('/api/control', { method: 'POST', body: { mode } })
  if (mode === 'auto') await load()
}
export const isUpcoming = match => ['NS', 'TBD', 'PST'].includes(match.status)
export function statusLabel(match) {
  if (match.status === 'HT') return 'Mi-temps'
  if (isLive(match)) return match.elapsed ? `${match.elapsed}′` : 'En cours'
  if (isFinished(match)) return 'Terminé'
  return ({ PST: 'Reporté', CANC: 'Annulé', ABD: 'Abandonné', SUSP: 'Suspendu', TBD: 'À confirmer' })[match.status] || time(match.date)
}
export function time(date) { return new Date(date).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Paris' }) }
export function day(date, options = {}) { return new Date(date).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', timeZone: 'Europe/Paris', ...options }) }
export function dateKey(date) { return new Date(date).toLocaleDateString('en-CA', { timeZone: 'Europe/Paris' }) }
export const currentRound = computed(() => {
  const live = state.matches.find(isLive)
  const next = [...state.matches].filter(isUpcoming).sort((a, b) => new Date(a.date) - new Date(b.date))[0]
  const last = [...state.matches].filter(isFinished).sort((a, b) => new Date(b.date) - new Date(a.date))[0]
  return (live || next || last)?.round || 1
})
