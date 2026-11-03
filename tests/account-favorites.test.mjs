import test from 'node:test'
import assert from 'node:assert/strict'
import { nextTick } from 'vue'
import { account, accountRequest } from '../src/account.js'

test('favoris : stockage serveur, déconnexion, changement de compte et mode inconnu sans fuite de préférences', async () => {
  const names = ['window','document','localStorage','fetch']
  const saved = names.map(name => Object.getOwnPropertyDescriptor(globalThis,name))
  const storage = new Map([['l1-favorite','2'],['l1-favorite-source','highlightly']])
  const calls = []
  let response
  const values = {
    window: {}, document: { documentElement: { dataset: { theme: 'dark' } } },
    localStorage: { getItem: key=>storage.get(key)||null, setItem:(key,value)=>storage.set(key,value) },
    fetch: async(path,options)=>{calls.push({path,options});return Response.json(response)},
  }
  names.forEach(name=>Object.defineProperty(globalThis,name,{configurable:true,value:values[name]}))
  try {
    const { state, chooseFavorite, refreshManually, syncManualRefresh } = await import('../src/state.js')
    state.source='highlightly';account.ready=true;account.enabled=true;account.user={id:1};account.csrfToken='csrf-test-only'
    await nextTick();assert.equal(state.favorite,null)
    response={following:{primaryTeamId:11,source:'highlightly',teams:[{id:11,providerId:1,name:'Club A'}]}}
    await chooseFavorite(1);await nextTick()
    assert.equal(state.favorite,1)
    assert.equal(calls[0].path,'/api/account/primary-team')
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'],'csrf-test-only')
    assert.equal(storage.get('l1-favorite'),'2') // Le compte n'écrase pas l'ancien choix local.
    response={enabled:true,user:{id:2},csrfToken:'next-test-only'}
    await accountRequest('/login',{method:'POST',body:{}});await nextTick()
    assert.equal(state.favorite,null);assert.deepEqual(account.following.teams,[])
    response={enabled:true,user:null,following:{primaryTeamId:null,source:'highlightly',teams:[]}}
    await accountRequest('/logout',{method:'POST',body:{}});await nextTick()
    const count=calls.length
    await chooseFavorite(2);assert.equal(state.pickerOpen,true);assert.equal(state.favorite,null)
    await refreshManually();await syncManualRefresh();assert.equal(calls.length,count);assert.equal(state.manualRefresh,null)
    account.enabled=null;state.pickerOpen=false
    await chooseFavorite(1);await nextTick();assert.equal(state.favorite,null);assert.equal(storage.get('l1-favorite'),'2')
    account.enabled=false;await nextTick();assert.equal(state.favorite,2) // Compatibilité explicite avec la version sans comptes.
    account.enabled=true;account.user={id:3};account.csrfToken='account-csrf-test';await nextTick()
    response={mode:'api',season:2026,refreshMode:'auto',fixtures:{matches:[],fetchedAt:100,stale:false},overview:{teams:[],standings:[],fetchedAt:100,stale:false},manualRefresh:{remaining:9,limit:10}}
    await refreshManually()
    const post=calls.findLast(c=>c.path==='/api/account/refresh'&&c.options.method==='POST')
    assert(post);assert.equal(post.options.headers['X-CSRF-TOKEN'],'account-csrf-test')
    assert.equal(state.manualRefresh.remaining,9)
    globalThis.fetch=async()=>Response.json({message:'Unauthenticated.'},{status:401})
    await refreshManually();await nextTick()
    assert.equal(account.user,null);assert.equal(state.favorite,null);assert.equal(state.manualRefresh,null)
    assert.match(state.error,/Connectez-vous/)

  } finally {
    account.ready=false;await nextTick()
    names.forEach((name,i)=>{if(saved[i])Object.defineProperty(globalThis,name,saved[i]);else delete globalThis[name]})
  }
})
