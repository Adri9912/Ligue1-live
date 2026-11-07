import { reactive } from 'vue'
import { recordInstallVisit, claimInstallOffer, postponeInstallOffer } from './install-session.mjs'

// Le navigateur garde la décision d’installer ; chaque invitation est à usage unique.
export function createInstall(browser) {
  const display = browser.matchMedia('(display-mode: standalone)')
  let storage
  try { storage = browser.localStorage } catch { /* Le menu reste utilisable sans stockage. */ }
  const state = reactive({ installed: display.matches || browser.navigator.standalone === true, available: false, pending: false, offerEligible: recordInstallVisit(storage) })
  let invitation = null
  browser.addEventListener('beforeinstallprompt', event => {
    event.preventDefault()
    invitation = event
    state.available = true
  })
  function installed() {
    state.installed = true
    state.available = false
    invitation = null
  }
  browser.addEventListener('appinstalled', installed)
  display.addEventListener('change', event => {
    state.installed = event.matches || browser.navigator.standalone === true
  })
  async function prompt() {
    if (!invitation || state.pending || state.installed) return false
    postpone()
    const event = invitation
    invitation = null
    state.available = false
    state.pending = true
    try {
      await event.prompt()
      await event.userChoice
      return true
    } catch {
      return false
    } finally {
      state.pending = false
    }
  }
  function postpone() {
    state.offerEligible = false
    postponeInstallOffer(storage)
  }
  function claimOffer() {
    if (!state.offerEligible || !state.available || state.installed) return false
    state.offerEligible = false
    return claimInstallOffer(storage)
  }
  return { state, prompt, claimOffer, postpone }
}

export const install = typeof window === 'undefined' ? null : createInstall(window)
