import test from 'node:test'
import assert from 'node:assert/strict'
import { recordInstallVisit, claimInstallOffer, postponeInstallOffer, INSTALL_VISIT_GAP as VISIT, INSTALL_OFFER_GAP as WEEK, INSTALL_SESSION_KEY } from '../src/install-session.mjs'

function storage() {
  const values = new Map()
  return { getItem: key => values.get(key) ?? null, setItem: (key, value) => values.set(key, value) }
}
test('installation : trois visites espacées, les rechargements et onglets rapprochés ne comptent pas', () => {
  const s = storage()
  assert.equal(recordInstallVisit(s, 0), false)
  assert.equal(recordInstallVisit(s, 1), false)
  assert.equal(recordInstallVisit(s, VISIT), false)
  assert.equal(recordInstallVisit(s, VISIT + 1), false)
  assert.equal(recordInstallVisit(s, VISIT * 2), true)
  assert.equal(claimInstallOffer(s, VISIT * 2), true)
  assert.equal(claimInstallOffer(s, VISIT * 2), false)
  assert.equal(recordInstallVisit(s, VISIT * 2 + WEEK - 1), false)
  assert.equal(recordInstallVisit(s, VISIT * 2 + WEEK), true)
})
test('installation : Plus tard ou une tentative dans le menu redémarre les sept jours', () => {
  const s = storage()
  for (let i = 0; i < 3; i++) recordInstallVisit(s, VISIT * i)
  claimInstallOffer(s, VISIT * 2)
  postponeInstallOffer(s, VISIT * 3)
  assert.equal(claimInstallOffer(s, VISIT * 2 + WEEK), false)
  assert.equal(claimInstallOffer(s, VISIT * 3 + WEEK), true)
})
test('installation : stockage indisponible ou corrompu, aucune invitation répétée', () => {
  const blocked = { getItem() { throw Error('blocked') }, setItem() { throw Error('blocked') } }
  assert.equal(recordInstallVisit(blocked), false)
  assert.equal(claimInstallOffer(blocked), false)
  assert.doesNotThrow(() => postponeInstallOffer(blocked))
  const s = storage()
  s.setItem(INSTALL_SESSION_KEY, 'invalid JSON')
  assert.equal(recordInstallVisit(s), false)
  assert.equal(claimInstallOffer(s), false)
})
