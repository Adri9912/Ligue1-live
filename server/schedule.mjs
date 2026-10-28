const dayMs = 86400000
export const tenMinutes = 600000
export const hour = 3600000
const live = ['1H', '2H', 'HT', 'ET', 'BT', 'P', 'LIVE', 'INT']
const finished = ['FT', 'AET', 'PEN', 'AWD', 'WO']
export const parisDay = time => new Date(time).toLocaleDateString('en-CA', { timeZone: 'Europe/Paris' })
export const sameParisDay = (a, b) => parisDay(a) === parisDay(b)
export function nextParisDay(time) {
  const midnightUtc = Date.parse(`${parisDay(time)}T00:00:00Z`) + dayMs
  // À 00 h UTC, Paris est à 01 h ou 02 h, avant le changement d’heure européen.
  const offsetHours = Number(new Date(midnightUtc).toLocaleTimeString('en-GB', { timeZone: 'Europe/Paris', hour: '2-digit', hourCycle: 'h23' }))
  return midnightUtc - offsetHours * hour
}
export function activeMatch(data, now) {
  return data.some(item => {
    const start = Date.parse(item.fixture.date), status = item.fixture.status.short
    return now >= start && now < start + 4 * hour && (live.includes(status) || ['NS', 'TBD'].includes(status))
  })
}
export function fixturesFresh(entry, now, manual = false) {
  if (manual || activeMatch(entry.data, now)) return now - entry.time < tenMinutes
  return sameParisDay(entry.time, now)
}
export function nextFixturesCheck(entry, now) {
  if (!entry) return now
  let next = nextParisDay(entry.time)
  if (activeMatch(entry.data, now)) next = Math.min(next, entry.time + tenMinutes)
  for (const item of entry.data) {
    const start = Date.parse(item.fixture.date)
    if (['NS', 'TBD'].includes(item.fixture.status.short) && start > now) next = Math.min(next, start)
  }
  return Math.max(now, next)
}
export function completedSignature(data = []) {
  return data.filter(item => finished.includes(item.fixture.status.short)).map(item => `${item.fixture.id}:${item.goals.home}:${item.goals.away}`).sort().join('|')
}
export function standingsFresh(entry, signature, now) {
  return now - entry.time < hour || (sameParisDay(entry.time, now) && entry.signature === signature)
}
