// Les identifiants Highlightly et API-Football ne sont jamais interchangeables.
function nameKey(name) {
  const words = name.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim().split(/\s+/)
  return words.length > 1 ? `${words[0][0]} ${words.slice(1).join(' ')}` : words[0]
}
export function matchSquadPlayer(player, squad) {
  const candidates = squad.filter(p => nameKey(p.name) === nameKey(player.name) && (player.number == null || p.number === player.number))
  return candidates.length === 1 ? candidates[0] : null
}
