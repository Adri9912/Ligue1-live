import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp,rm } from 'node:fs/promises'
import { join } from 'node:path'
import { tmpdir } from 'node:os'
import { once } from 'node:events'
import { createProvider } from '../server/provider.mjs'
import { createRetro } from '../server/retro.mjs'
import { createClubs, normalizeSquad, clubHonours } from '../server/clubs.mjs'
import { createApp } from '../server/index.mjs'
const rawTeam={id:85,name:'Paris Saint Germain',founded:1970}
const row={rank:1,team:rawTeam,points:84,goalsDiff:57,all:{played:34,win:26,draw:6,lose:2,goals:{for:92,against:35}}}

test('archives : saisons validées, long cache, quota commun entre saisons et séparation des clés',async()=>{
 const directory=await mkdtemp(join(tmpdir(),'l1-retro-'));let calls=0,now=Date.parse('2026-10-27T10:00Z'),mode='auto'
 const options={directory,key:'retro-secret',season:2024,spacingMs:0,clock:()=>now,getMode:()=>mode,fetcher:async(url,init)=>{calls++;assert.equal(init.headers['x-apisports-key'],'retro-secret');return {ok:true,json:async()=>({errors:[],response:[{league:{standings:[[row]]}}]})}}}
 try{
  const client=await createProvider(options),retro=createRetro(client)
  assert.equal(retro.metadata().available,true)
  await assert.rejects(retro.get(2026,'standings',{}),e=>e.status===404);assert.equal(calls,0)
  const result=await retro.get(2024,'standings',{});assert.equal(result.archiveSeason,2024);assert.equal(result.standings[0].points,84)
  await retro.get(2023,'standings',{});assert.equal(calls,2);assert.equal(retro.metadata().retroBudget.used,2)
  now+=7*86400000;await retro.get(2024,'standings',{});assert.equal(calls,2)
  const reboot=createRetro(await createProvider(options));await reboot.get(2023,'standings',{});assert.equal(calls,2)
  mode='pause';await assert.rejects(reboot.get(2022,'standings',{manual:true}),/pause/);assert.equal(calls,2)
 }finally{await rm(directory,{recursive:true,force:true})}
})

test('clubs : postes triés, numéros absents conservés et titres historiques sourcés',()=>{
 const players=normalizeSquad([{id:1,name:'Attaquant',position:'Attacker',number:9},{id:2,name:'Milieu',position:'Midfielder',number:8},{id:3,name:'Gardien',position:'Goalkeeper',number:null},{id:4,name:'Défenseur',position:'Defender',number:2}])
 assert.deepEqual(players.map(p=>p.position),['Gardien','Défenseur','Milieu','Attaquant']);assert.equal(players[0].number,null)
 assert.equal(clubHonours(85).seasons.length,14);assert.equal(clubHonours(81).seasons.length,9);assert.ok(!clubHonours(81).seasons.includes('1992–1993'))
 assert.deepEqual(clubHonours(94).seasons,[]);assert.equal(clubHonours(999).known,false)
})

test('clubs : accès limité aux clubs actuels, correspondance des fournisseurs et effectif actuel',async()=>{
 let calls=[]
 const client={status:()=>({budget:{used:calls.length}}),request:async(path,params)=>{calls.push([path,params]);return {fetchedAt:1,stale:false,data:path==='teams'?[{team:rawTeam,venue:{name:'Parc des Princes',city:'Paris'}}]:[{team:rawTeam,players:[{id:1,name:'Gardien',position:'Goalkeeper',number:30}]}]}}}
 const service=createClubs(client,async()=>[{id:73119,name:'Paris Saint Germain'}])
 await assert.rejects(service.detail(85,{}),e=>e.status===404);assert.equal(calls.length,0)
 const data=await service.detail(73119,{})
 assert.equal(data.club.id,73119);assert.equal(data.club.stadium,'Parc des Princes');assert.equal(data.club.founded,1970)
 assert.equal(data.squad[0].number,30);assert.equal(data.honours.seasons.length,14);assert.equal(calls[1][1].team,85)
})

test('HTTP : archives et fiches ne changent pas la saison ni la source du direct',async()=>{
 const directory=await mkdtemp(join(tmpdir(),'l1-retro-http-'))
 const server=await createApp({directory,key:'',retroKey:'test',season:2026,spacingMs:0,retroFetcher:async()=>({ok:true,json:async()=>({errors:[],response:[{league:{standings:[[row]]}}]})})})
 try{
  server.listen(0,'127.0.0.1');await once(server,'listening');const base=`http://127.0.0.1:${server.address().port}`
  const data=await(await fetch(base+'/api/retro/2024/standings')).json()
  assert.equal(data.season,2026);assert.equal(data.mode,'demo');assert.equal(data.archiveSeason,2024);assert.equal(data.archiveSource,'API-Football')
  assert.equal((await fetch(base+'/api/retro/2099/standings')).status,404)
  assert.equal((await fetch(base+'/api/clubs/999999')).status,404)
 }finally{await new Promise(r=>server.close(r));await rm(directory,{recursive:true,force:true})}
})
