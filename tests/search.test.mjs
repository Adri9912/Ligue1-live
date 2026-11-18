import test from 'node:test'
import assert from 'node:assert/strict'
import { searchSite } from '../src/search.js'

const paris = { id:1, name:'Paris Saint Germain', code:'PSG' }
const lyon = { id:2, name:'Lyon', fullName:'Olympique Lyonnais', code:'OL' }
const saintEtienne = { id:3, name:'Saint-Étienne', code:'ASSE' }
const now = Date.parse('2026-11-18T12:00:00Z')
const game = (id, date, status = 'NS') => ({ id, date, status, home:paris, away:lyon })

test('recherche : accents, codes, noms complets et mots des deux clubs', () => {
  const clubs = [paris, lyon, saintEtienne]
  assert.deepEqual(searchSite(clubs, [], 'saint etienne').clubs, [saintEtienne])
  assert.deepEqual(searchSite(clubs, [], 'OLYMPIQUE LYONNAIS').clubs, [lyon])
  assert.deepEqual(searchSite(clubs, [], 'PSG').clubs, [paris])
  const match = game(1, '2026-11-20T19:00:00Z')
  assert.deepEqual(searchSite(clubs, [match], 'lyon paris', now).matches, [match])
  assert.deepEqual(searchSite(clubs, [match], 'inconnu', now), { clubs:[], matches:[] })
  assert.deepEqual(searchSite(clubs, [match], ' ', now), { clubs:[], matches:[] })
})

test('recherche : direct, prochains matchs puis résultats récents, sans modifier les données', () => {
  const fixtures = [game(1, '2026-11-10T19:00:00Z', 'FT'), game(2, '2026-11-25T19:00:00Z'), game(3, '2026-11-18T11:00:00Z', '1H'), game(4, '2026-11-20T19:00:00Z'), game(5, '2026-11-15T19:00:00Z', 'FT'), game(6, 'invalid')]
  const original = [...fixtures]
  assert.deepEqual(searchSite([paris, lyon], fixtures, 'lyon', now).matches.map(match => match.id), [3,4,2,5,1])
  assert.deepEqual(fixtures, original)
  assert.equal(searchSite([], Array.from({length:15}, (_, i) => game(i, '2026-11-20T19:00:00Z')), 'paris', now).matches.length, 6)
})
