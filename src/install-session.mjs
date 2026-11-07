export const INSTALL_SESSION_KEY = 'l1-install-offer-v1'
export const INSTALL_VISIT_GAP = 30 * 60 * 1000
export const INSTALL_OFFER_GAP = 7 * 24 * 60 * 60 * 1000

function read(storage) {
  const value = JSON.parse(storage.getItem(INSTALL_SESSION_KEY) || '{}')
  return {
    visits: Number.isInteger(value?.visits) && value.visits >= 0 ? Math.min(value.visits, 3) : 0,
    lastVisit: Number.isFinite(value?.lastVisit) ? value.lastVisit : null,
    lastOffer: Number.isFinite(value?.lastOffer) ? value.lastOffer : null,
  }
}

function eligible(value, now) {
  return value.visits >= 3 && (value.lastOffer === null || now - value.lastOffer >= INSTALL_OFFER_GAP)
}

export function recordInstallVisit(storage, now = Date.now()) {
  try {
    let value
    try { value = read(storage) } catch { value = { visits: 0, lastVisit: null, lastOffer: null } }
    if (value.lastVisit === null || now - value.lastVisit >= INSTALL_VISIT_GAP) {
      value.visits = Math.min(3, value.visits + 1)
      value.lastVisit = now
    }
    storage.setItem(INSTALL_SESSION_KEY, JSON.stringify(value))
    return eligible(value, now)
  } catch { return false } // Sans mémorisation fiable, seul le menu propose l’installation.
}

export function claimInstallOffer(storage, now = Date.now()) {
  try {
    const value = read(storage)
    if (!eligible(value, now)) return false
    value.lastOffer = now
    storage.setItem(INSTALL_SESSION_KEY, JSON.stringify(value))
    return true
  } catch { return false }
}

export function postponeInstallOffer(storage, now = Date.now()) {
  try {
    const value = read(storage)
    value.lastOffer = now
    storage.setItem(INSTALL_SESSION_KEY, JSON.stringify(value))
  } catch { /* Le menu et l’accès au site restent disponibles. */ }
}
