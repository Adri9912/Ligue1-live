export const INTRO_KEY = 'l1-intro-last-shown-v1'
export const INTRO_INTERVAL = 30 * 60 * 1000

// Store the start, including skipped intros, so every tab shares the same window.
export function claimIntro(storage, now = Date.now()) {
  try {
    const previous = Number(storage.getItem(INTRO_KEY))
    if (Number.isFinite(previous) && previous > 0 && previous <= now && now - previous < INTRO_INTERVAL) return false
    storage.setItem(INTRO_KEY, String(now))
  } catch { /* Storage can be blocked; the introduction must never prevent access. */ }
  return true
}
