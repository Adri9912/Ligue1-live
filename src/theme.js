export function themeAt(preference, date = new Date()) {
  if (preference === 'light' || preference === 'dark') return preference
  return date.getHours() >= 8 && date.getHours() < 18 ? 'light' : 'dark'
}
