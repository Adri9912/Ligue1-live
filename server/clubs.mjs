import { ApiError, normalizeTeam } from './provider.mjs'
const day = 86400000
export const playerPhoto = id => Number.isSafeInteger(id) && id > 0 ? `https://media.api-sports.io/football/players/${id}.png` : null
export const honoursSource = 'https://ligue1.com/en/articles/l1_article_289-le-palmares-des-champions-de-ligue-1'
// Années de fin des saisons de D1/Ligue 1, hors championnats de guerre.
// Palmarès LFP vérifié le 27 octobre 2026 ; les coupes et titres de D2 sont exclus.
const titles = {
  85: [1986,1994,2013,2014,2015,2016,2018,2019,2020,2022,2023,2024,2025,2026],
  81: [1937,1948,1971,1972,1989,1990,1991,1992,2010],
  91: [1961,1963,1978,1982,1988,1997,2000,2017],
  79: [1946,1954,2011,2021], 80: [2002,2003,2004,2005,2006,2007,2008],
  84: [1951,1952,1956,1959], 116: [1998], 108: [1996], 95: [1979],
  94: [],114: [],97: [],96: [],106: [],77: [],1298: [],110: [],111: [],
}
const aliases = { rennesfc:'rennes', lehavreac:'lehavre' }
const normalized = name => name.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]/g,'')
const positionOrder = { Goalkeeper:0, Defender:1, Midfielder:2, Attacker:3, Forward:3 }
const positions = { Goalkeeper:'Gardien', Defender:'Défenseur', Midfielder:'Milieu', Attacker:'Attaquant', Forward:'Attaquant' }
export function normalizeSquad(players) {
  return players.map(p=>({id:p.id,name:p.name,number:p.number ?? null,age:p.age ?? null,photo:playerPhoto(p.id),position:positions[p.position] || 'Poste non renseigné',order:positionOrder[p.position] ?? 4})).sort((a,b)=>a.order-b.order || (a.number ?? Infinity)-(b.number ?? Infinity) || a.name.localeCompare(b.name,'fr'))
}
export function clubHonours(id) {
  return { known:Object.hasOwn(titles,id), seasons:(titles[id] || []).map(end=>`${end-1}–${end}`).reverse(), source:honoursSource, verifiedAt:'2026-10-27' }
}
// Corrections documentées lorsque la fiche fournisseur est ancienne ou ambiguë.
const corrections = {
  96: { founded:1970, source:'https://billetterie.om.fr/fr/histoire/adversaires/om-toulouse-fc' },
  91: { founded:1924, source:'https://www.asmonaco.com/fr/club/histoire/naissance-de-l-as-monaco' },
  114: { stadium:'Stade Jean-Bouin', source:'https://parisfc.fr/club/communique-officiel-29/' },
}
export function createClubs(client, getCurrentTeams) {
  return {
    async player(clubId, playerId, options) {
      const details = await this.detail(clubId, options)
      if (details.squadError) throw new ApiError(details.squadError, details.squadErrorStatus || 503)
      const member = details.squad.find(p => p.id === playerId)
      if (!member) throw new ApiError('Ce joueur ne figure pas dans l’effectif actuel de ce club.', 404)
      const result = await client.request('players/profiles', { player: playerId }, 7 * day, options)
      const raw = result.data.find(r => r.player?.id === playerId)?.player
      if (!raw) throw new ApiError('La fiche de ce joueur n’est pas disponible.', 404)
      const fullName = [raw.firstname, raw.lastname].filter(Boolean).join(' ') || raw.name || member.name
      return { player: { id:playerId, name:fullName, shortName:member.name, position:member.position, number:member.number, photo:playerPhoto(playerId), age:raw.age ?? member.age, birthDate:raw.birth?.date || null, birthPlace:raw.birth?.place || null, birthCountry:raw.birth?.country || null, nationality:raw.nationality || null, height:raw.height || null, weight:raw.weight || null }, club:details.club, fetchedAt:result.fetchedAt, stale:result.stale || details.stale, clubBudget:client.status().budget }
    },
    async detail(id, options) {
      const teams = await getCurrentTeams(options)
      const current = teams.find(t=>t.id===id)
      if (!current) throw new ApiError('Club introuvable dans la saison actuelle de Ligue 1.',404)
      if (!client) throw new ApiError('La clé API-Football est nécessaire pour les effectifs et les informations des clubs.',503)
      const registry = await client.request('teams',{country:'France'},30*day,options)
      const name = normalized(current.name)
      const matching = registry.data.filter(r=>!r.team.national && normalized(r.team.name)===(aliases[name] || name))
      if(matching.length!==1) throw new ApiError('La correspondance de ce club entre les fournisseurs reste à vérifier.',422)
      const record = matching[0], apiId=record.team.id
      const correction=corrections[apiId]
      const club = { ...normalizeTeam(record.team), id:current.id, founded:correction?.founded || record.team.founded || null, stadium:correction?.stadium || record.venue?.name || null, city:record.venue?.city || null, correctionSource:correction?.source || null }
      let squad=[], squadError='', squadErrorStatus=null, fetchedAt=registry.fetchedAt, stale=registry.stale
      try {
        const result=await client.request('players/squads',{team:apiId},day,options)
        const row=result.data.find(r=>r.team?.id===apiId)
        if(!row?.players?.length) squadError='L’effectif n’est pas disponible pour ce club.'
        else squad=normalizeSquad(row.players)
        fetchedAt=result.fetchedAt;stale=stale||result.stale
      } catch(error) { squadError=error instanceof ApiError ? error.message : 'L’effectif ne peut pas être chargé pour le moment.'; squadErrorStatus=error.status || 502 }
      return { club, squad, squadError, squadErrorStatus, honours:clubHonours(apiId), fetchedAt, stale, clubSource:'API-Football', clubBudget:client.status().budget }
    },
  }
}
