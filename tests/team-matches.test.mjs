import test from 'node:test'
import assert from 'node:assert/strict'
import { nextTeamMatch, kickoffCountdown } from '../src/teamMatches.js'
const now=Date.parse('2026-10-28T12:00:00Z')
const match=(id,status,date)=>({id,status,date})
test('prochain rendez-vous : priorité au direct, exclusion des reports et horaires périmés',()=>{
 const future=match(1,'NS','2026-10-30T12:00:00Z'), live=match(2,'1H','2026-10-28T11:00:00Z')
 assert.equal(nextTeamMatch([future,live],now).id,2)
 assert.equal(nextTeamMatch([match(3,'PST','2026-10-29T12:00:00Z'),future,match(4,'NS','2026-10-25T12:00:00Z')],now).id,1)
 assert.equal(nextTeamMatch([match(5,'NS','2026-10-28T11:59:00Z'),future],now).id,5)
 assert.equal(nextTeamMatch([match(6,'FT','2026-10-27T12:00:00Z'),match(7,'NS','invalid')],now),null)
})
test('compte à rebours : jours, heures, dernière minute et coup d’envoi attendu',()=>{
 assert.equal(kickoffCountdown('2026-10-30T14:00:00Z',now),'Dans 2 j 2 h')
 assert.equal(kickoffCountdown('2026-10-28T13:15:00Z',now),'Dans 1 h 15 min')
 assert.equal(kickoffCountdown('2026-10-28T12:00:01Z',now),'Dans 1 min')
 assert.equal(kickoffCountdown('2026-10-28T11:59:00Z',now),'Coup d’envoi attendu')
 assert.equal(kickoffCountdown('invalid',now),'Horaire à confirmer')
})
