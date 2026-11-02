import test from 'node:test'
import assert from 'node:assert/strict'
import { themeAt } from '../src/theme.js'
import { clubRanking } from '../src/football.js'
test('thème automatique : horaires locaux à 8 h et 18 h, choix manuel prioritaire', () => {
 for(const [hour,minute,expected] of [[7,59,'dark'],[8,0,'light'],[17,59,'light'],[18,0,'dark'],[0,0,'dark']]) {
  const date=new Date(2026,10,2,hour,minute)
  assert.equal(themeAt('auto',date),expected)
  assert.equal(themeAt('light',date),'light')
  assert.equal(themeAt('dark',date),'dark')
 }
})
test('statistiques : totaux, ex aequo, valeurs manquantes et absence de mutation', () => {
 const rows=[{rank:2,played:8,goalsFor:20,goalsAgainst:4},{rank:1,played:8,goalsFor:20,goalsAgainst:8},{rank:3,played:8,goalsFor:10,goalsAgainst:4},{rank:4,played:0,goalsFor:0,goalsAgainst:0},{rank:5,played:8,goalsFor:null,goalsAgainst:null}]
 const original=JSON.stringify(rows)
 assert.deepEqual(clubRanking(rows,'attack').map(r=>r.rank),[1,2,3])
 assert.deepEqual(clubRanking(rows,'defense',2).map(r=>r.rank),[2,3])
 assert.deepEqual(clubRanking([],'attack'),[])
 assert.equal(JSON.stringify(rows),original)
})
