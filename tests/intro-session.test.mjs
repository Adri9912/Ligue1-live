import { test } from 'node:test'
import assert from 'node:assert/strict'
import { claimIntro, INTRO_KEY, INTRO_INTERVAL } from '../src/intro-session.mjs'

test('intro: first visit, shared tabs, reloads and the 30-minute boundary', () => {
  const data = new Map()
  const storage = { getItem: key => data.get(key) ?? null, setItem: (key, value) => data.set(key, value) }
  const now = Date.parse('2026-11-01T10:00:00Z')
  assert.equal(claimIntro(storage, now), true)
  assert.equal(claimIntro(storage, now + 1), false)
  assert.equal(claimIntro(storage, now + INTRO_INTERVAL - 1), false)
  assert.equal(data.get(INTRO_KEY), String(now)) // Visits do not extend the interval.
  assert.equal(claimIntro(storage, now + INTRO_INTERVAL), true)
  assert.equal(claimIntro(storage, now + INTRO_INTERVAL + 1), false)
  for (const invalid of ['broken', 'Infinity', String(now + INTRO_INTERVAL * 3)]) {
    data.set(INTRO_KEY, invalid)
    assert.equal(claimIntro(storage, now), true)
  }
})

test('intro: blocked storage never blocks access', () => {
  assert.equal(claimIntro({ getItem() { throw new Error('blocked') } }), true)
})
