import test from 'node:test'
import assert from 'node:assert/strict'
import { recentTeamResults, previousMeetings } from '../src/teamMatches.js'
const home={id:1,name:'A'},away={id:2,name:'B'},other={id:3,name:'C'}
const game=(id,date,status,homeGoals,awayGoals,h=home,a=away)=>({id,date,status,homeGoals,awayGoals,home:h,away:a})
const before=Date.parse('2024-10-10T12:00:00Z')
test('forme : perspective du club, nul 0–0, ordre, limite et exclusion des scores absents, directs ou futurs',()=>{
 const games=[game(1,'2024-10-01','FT',0,0),game(2,'2024-10-02','FT',2,0,away,home),game(3,'2024-10-03','FT',1,3,away,home),game(4,'2024-10-04','FT',null,null),game(5,'2024-10-05','1H',1,0),game(6,'2024-10-11','FT',2,0),game(7,'invalid','FT',1,0),game(8,'2024-10-06','FT',-1,0),game(9,'2024-10-07','FT',2,0,other,away)]
 assert.deepEqual(recentTeamResults(games,1,{before}).map(r=>[r.match.id,r.result,r.scored,r.conceded]),[[3,'W',3,1],[2,'L',0,2],[1,'D',0,0]])
 assert.equal(recentTeamResults(games,1,{before,limit:2}).length,2)
 assert.deepEqual(recentTeamResults([],1,{before}),[])
})
test('confrontations : deux clubs exacts, aller/retour, uniquement avant la rencontre',()=>{
 const fixture=game(50,'2024-10-10','NS',null,null)
 const games=[game(1,'2024-10-01','FT',2,0),game(2,'2024-10-02','FT',1,0,away,home),game(3,'2024-10-03','FT',2,0,home,other),game(4,'2024-10-11','FT',0,0),fixture]
 assert.deepEqual(previousMeetings(games,fixture).map(r=>r.match.id),[2,1])
 assert.deepEqual(previousMeetings(games,game(51,'invalid','NS',null,null)),[])
})
