import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { createProvider } from '../server/provider.mjs'
import { createClubs } from '../server/clubs.mjs'
import { createHighlightlyProvider, normalizeLineup } from '../server/highlightly.mjs'
import { matchSquadPlayer } from '../src/players.js'
const team={id:73119,name:'Paris'}, away={id:68864,name:'Lyon'}
function lineup() {
 let id=0
 return {homeTeam:{id:team.id,formation:'4-3-3',initialLineup:[1,4,3,3].map((count,i)=>Array.from({length:count},()=>({id:++id,name:`Joueur ${id}`,number:id,position:i?'Defender':'Goalkeeper'})))},awayTeam:{id:away.id,formation:'Unknown',initialLineup:[]}}
}
const fixture=id=>({id,round:'Regular Season - 8',date:`2026-10-${20+id}T19:00:00Z`,league:{id:52695,season:2026},homeTeam:team,awayTeam:away,state:{description:'Finished',clock:90,score:{current:'2 - 0'}}})

test('composition : onze joueurs uniques, lignes conformes et formation connue obligatoires',()=>{
 assert.equal(normalizeLineup(lineup(),team.id).rows.flat().length,11)
 assert.equal(normalizeLineup(lineup(),away.id),null)
 const bad=lineup();bad.homeTeam.initialLineup[1].pop();assert.equal(normalizeLineup(bad,team.id),null)
 const duplicate=lineup();duplicate.homeTeam.initialLineup[1][0].id=1;assert.equal(normalizeLineup(duplicate,team.id),null)
})

test('recherche : dernière composition complète, trois appels maximum, cache et pause partagés',async()=>{
 const directory=await mkdtemp(join(tmpdir(),'l1-lineup-'));let calls=0,mode='auto'
 try{
  const service=await createHighlightlyProvider({directory,key:'secret',season:2026,spacingMs:0,clock:()=>Date.parse('2026-10-27T12:00Z'),getMode:()=>mode,fetcher:async(url,init)=>{
   calls++;assert.equal(init.headers['x-rapidapi-key'],'secret')
   let body
   if(url.pathname==='/matches')body={data:[fixture(1),fixture(2),fixture(3),fixture(4)],pagination:{totalCount:4}}
   else body=url.pathname==='/lineups/2'?lineup():{homeTeam:{id:team.id,formation:'Unknown',initialLineup:[]},awayTeam:{id:away.id,formation:'Unknown',initialLineup:[]}}
   return {ok:true,json:async()=>body}
  }})
  const data=await service.clubLineup(team.id)
  assert.deepEqual(data.checkedMatchIds,[4,3,2]);assert.equal(data.lineup.match.id,2);assert.equal(data.totalMatches,4);assert.equal(calls,4)
  await service.clubLineup(team.id);assert.equal(calls,4)
  const missing=await service.clubLineup(away.id);assert.equal(missing.lineup,null);assert.equal(missing.nextOffset,3);assert.equal(missing.checkedMatchIds.length,3)
  mode='pause';await assert.rejects(service.clubLineup(away.id,{offset:3,manual:true}),/pause/)
  await assert.rejects(service.clubLineup(team.id,{offset:-1}),e=>e.status===400)
 }finally{await rm(directory,{recursive:true,force:true})}
})

test('identifiants distincts : ne jamais attribuer une photo sur le seul numéro ou une identité ambiguë',()=>{
 const squad=[{id:2068,name:'M. Safonov',number:39},{id:9,name:'A. Hakimi',number:2}]
 assert.equal(matchSquadPlayer({id:333102,name:'Matvey Safonov',number:39},squad).id,2068)
 assert.equal(matchSquadPlayer({id:9,name:'Autre Joueur',number:2},squad),null)
 assert.equal(matchSquadPlayer({name:'Achraf Hakimi',number:22},squad),null)
 assert.equal(matchSquadPlayer({name:'M. Safonov',number:39},[...squad,{id:999,name:'M. Safonov',number:39}]),null)
})

test('fiche joueur : appartenance au club, photo sans clé et cache hebdomadaire',async()=>{
 const directory=await mkdtemp(join(tmpdir(),'l1-profile-'));let calls=0
 try{
  const raw={id:85,name:'Paris Saint Germain',founded:1970}
  const client=await createProvider({directory,key:'secret',season:2024,spacingMs:0,fetcher:async url=>{
   calls++
   const data=url.pathname==='/teams'?[{team:raw,venue:{name:'Parc des Princes'}}]:url.pathname==='/players/squads'?[{team:raw,players:[{id:1,name:'L. Chevalier',number:30,age:24,position:'Goalkeeper'}]}]:[{player:{id:1,firstname:'Lucas',lastname:'Chevalier',nationality:'France',birth:{date:'2001-11-06'},height:'189',weight:'78'}}]
   return {ok:true,json:async()=>({errors:[],response:data})}
  }})
  const clubs=createClubs(client,async()=>[{id:73119,name:'Paris Saint Germain'}])
  const profile=await clubs.player(73119,1,{})
  assert.equal(profile.player.name,'Lucas Chevalier');assert.equal(profile.player.photo,'https://media.api-sports.io/football/players/1.png');assert.equal(calls,3)
  await clubs.player(73119,1,{});assert.equal(calls,3)
  await assert.rejects(clubs.player(73119,999,{}),e=>e.status===404);assert.equal(calls,3)
 }finally{await rm(directory,{recursive:true,force:true})}
})
