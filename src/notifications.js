import { reactive } from 'vue'
import { matchAlerts } from './football.js'
function read(key, fallback) { try { return JSON.parse(localStorage.getItem(key)) ?? fallback } catch { return fallback } }
const saved = read('l1-alert-settings', {})
export const alerts = reactive({
  enabled: saved.enabled === true, start: saved.start !== false, goals: saved.goals !== false, final: saved.final !== false,
  permission: 'Notification' in window ? Notification.permission : 'unsupported',
  message: '', busy: false, items: [],
})
function write(key, value) { try { localStorage.setItem(key, JSON.stringify(value)) } catch { alerts.message = 'Les réglages ne peuvent pas être mémorisés dans ce navigateur.' } }
export function saveAlertSettings() { write('l1-alert-settings', { enabled: alerts.enabled, start: alerts.start, goals: alerts.goals, final: alerts.final }) }
export function resetAlertBaseline(favorite, matches) {
  write('l1-alert-snapshot', { favorite, matches: matches.filter(m => [m.home.id, m.away.id].includes(favorite)), seen: [] })
  alerts.items = []
}
export function toggleAlerts(favorite, matches) {
  alerts.enabled = !alerts.enabled; alerts.message = ''
  resetAlertBaseline(favorite, matches); saveAlertSettings()
}
let worker
async function registration() {
  if (!('serviceWorker' in navigator)) return null
  if (!worker) worker = navigator.serviceWorker.register('/notifications-sw.js').then(() => navigator.serviceWorker.ready).catch(error => { worker = null; throw error })
  return worker
}
export async function allowSystemAlerts() {
  alerts.busy = true; alerts.message = ''
  try {
    if (!('Notification' in window) || !window.isSecureContext) { alerts.permission = 'unsupported'; alerts.message = 'Notifications système indisponibles ici. Les alertes dans le site restent disponibles. Essayez Chrome ou Firefox en HTTPS, ou en local.'; return }
    // Demande déclenchée seulement par le bouton explicite de l’utilisateur.
    alerts.permission = await Notification.requestPermission()
    if (alerts.permission === 'granted') { await registration(); alerts.message = 'Notifications système autorisées. Gardez le site ouvert pour recevoir les alertes.' }
    else alerts.message = 'Autorisation non accordée. Vous pouvez continuer avec les alertes dans le site.'
  } catch { alerts.message = 'Ce navigateur ne permet pas les notifications système. Les alertes dans le site restent disponibles.' }
  finally { alerts.busy = false }
}
export function dismissAlert(id) { alerts.items = alerts.items.filter(item => item.id !== id) }
async function publish(item, demo) {
  const shown = { ...item, title: `${demo ? '[Démo] ' : ''}${item.title}` }
  alerts.items = [shown, ...alerts.items.filter(n => n.id !== shown.id)].slice(0, 3)
  if (!('Notification' in window) || Notification.permission !== 'granted') return
  try {
    const reg = await registration()
    if (reg) await reg.showNotification(shown.title, { body: shown.body, tag: `l1-${shown.id}`, data: { url: shown.url } })
    else { const notification = new Notification(shown.title, { body: shown.body, tag: `l1-${shown.id}` }); notification.onclick = () => { window.focus(); window.location.assign(shown.url) } }
  } catch { alerts.message = 'La notification système n’a pas pu être affichée. L’alerte est visible dans le site.' }
}
let firstObservation = true
export async function observeMatches(matches, favorite, mode, stale = false) {
  if (stale) return
  // Web Locks évite que deux onglets émettent le même événement en même temps.
  const process = async () => {
    const previous = read('l1-alert-snapshot', { favorite: null, matches: [], seen: [] })
    if (firstObservation) { firstObservation = false; resetAlertBaseline(favorite, matches); return }
    if (previous.favorite !== favorite) { resetAlertBaseline(favorite, matches); return }
    const events = matchAlerts(previous.matches || [], matches, favorite, alerts)
    const seen = new Set(previous.seen || [])
    const fresh = events.filter(e => !seen.has(e.id))
    for (const event of fresh) seen.add(event.id)
    write('l1-alert-snapshot', { favorite, matches: matches.filter(m => [m.home.id, m.away.id].includes(favorite)), seen: [...seen].slice(-100) })
    for (const event of fresh) await publish(event, mode === 'demo')
  }
  if (navigator.locks) await navigator.locks.request('l1-club-alerts', process)
  else await process()
}
export function testAlert(team, demo) {
  return publish({ id: 'test', title: 'Notification de test', body: `${team.name} : vos alertes apparaîtront ici. Aucun événement réel n’a eu lieu.`, url: '/mon-equipe' }, demo)
}
