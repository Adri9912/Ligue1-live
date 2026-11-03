import test from 'node:test'
import assert from 'node:assert/strict'
import { claimWelcome, localDay, WELCOME_KEY } from '../src/welcome-session.mjs'

test('proposition : une fois par jour local, y compris entre onglets', () => {
  const values = new Map(), storage = { getItem: key => values.get(key), setItem: (key, value) => values.set(key, value) }
  const evening = localDay(new Date(2026, 10, 4, 23, 59))
  assert.equal(claimWelcome(storage, evening), true)
  assert.equal(values.get(WELCOME_KEY), evening)
  assert.equal(claimWelcome(storage, evening), false)
  assert.equal(claimWelcome(storage, localDay(new Date(2026, 10, 5, 0, 0))), true)
})

test('proposition : le stockage bloqué ne bloque pas l’accès libre', () => {
  assert.equal(claimWelcome({ getItem() { throw new Error('blocked') } }), true)
  assert.equal(claimWelcome({ getItem() { return null }, setItem() { throw new Error('blocked') } }), true)
})
