import test from 'node:test'
import assert from 'node:assert/strict'

test('portraits : recherches partagées, URL locale imposée et cache réutilisé', async () => {
 const previous=globalThis.fetch;let calls=0
 globalThis.fetch=async()=>{calls++;return {ok:true,json:async()=>({photo:'https://r2.thesportsdb.com/images/media/player/cutout/a.png'})}}
 try {
  const {lookupPortrait}=await import('../src/portraits.js?cache')
  const [a,b]=await Promise.all([lookupPortrait('/api/portraits/1'),lookupPortrait('/api/portraits/1')]);assert.deepEqual(a,b);assert.equal(calls,1)
  await lookupPortrait('/api/portraits/1');assert.equal(calls,1)
  assert.deepEqual(await lookupPortrait('https://elsewhere.test'),{photo:null});assert.equal(calls,1)
 } finally {globalThis.fetch=previous}
})
test('portraits : une limite temporaire peut être retentée, absence définitive mémorisée', async () => {
 const previous=globalThis.fetch;let calls=0
 globalThis.fetch=async()=>({ok:true,json:async()=>++calls===1?{photo:null,retryAt:Date.now()+65000}:{photo:null}})
 try {
  const {lookupPortrait}=await import('../src/portraits.js?retry')
  assert((await lookupPortrait('/api/portraits/2')).retryAt)
  assert.deepEqual(await lookupPortrait('/api/portraits/2'),{photo:null});await lookupPortrait('/api/portraits/2');assert.equal(calls,2)
 } finally {globalThis.fetch=previous}
})
