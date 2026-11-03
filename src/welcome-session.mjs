export const WELCOME_KEY = 'l1-welcome-day-v1'

export function localDay(date = new Date()) {
  return `${date.getFullYear()}-${date.getMonth() + 1}-${date.getDate()}`
}

export function claimWelcome(storage, day = localDay()) {
  try {
    if (storage.getItem(WELCOME_KEY) === day) return false
    storage.setItem(WELCOME_KEY, day)
  } catch { /* Un stockage bloqué ne doit jamais empêcher de consulter les matchs. */ }
  return true
}
