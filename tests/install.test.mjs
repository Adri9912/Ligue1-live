import test from 'node:test'
import assert from 'node:assert/strict'
import { createInstall } from '../src/install.js'

function browser(standalone = false) {
  const target = new EventTarget()
  const display = new EventTarget()
  display.matches = standalone
  target.navigator = {}
  target.matchMedia = () => display
  return { target, display }
}
function invitation(target, prompt) {
  const event = new Event('beforeinstallprompt', { cancelable: true })
  event.prompt = prompt
  event.userChoice = Promise.resolve({ outcome: 'dismissed' })
  target.dispatchEvent(event)
  assert.equal(event.defaultPrevented, true)
}

test('installation : invitation à usage unique, annulation, nouvel essai et confirmation réelle', async () => {
  const { target } = browser()
  const install = createInstall(target)
  assert.equal(await install.prompt(), false)
  let calls = 0
  invitation(target, async () => { calls++ })
  assert.equal(install.state.available, true)
  assert.equal(await install.prompt(), true)
  assert.equal(await install.prompt(), false)
  assert.equal(calls, 1)
  assert.equal(install.state.installed, false) // Une invitation fermée n’est pas une installation.
  invitation(target, async () => { throw Error('unavailable') })
  assert.equal(await install.prompt(), false)
  assert.equal(install.state.pending, false)
  invitation(target, async () => {})
  target.dispatchEvent(new Event('appinstalled'))
  assert.equal(install.state.installed, true)
  assert.equal(install.state.available, false)
  assert.equal(await install.prompt(), false)
})

test('installation : détection standalone, y compris Safari, et double clic bloqué', async () => {
  const { target, display } = browser(true)
  const install = createInstall(target)
  assert.equal(install.state.installed, true)
  display.dispatchEvent(Object.assign(new Event('change'), { matches: false }))
  assert.equal(install.state.installed, false)
  target.navigator.standalone = true
  assert.equal(createInstall(target).state.installed, true)
  let release
  invitation(target, () => new Promise(resolve => { release = resolve }))
  const pending = install.prompt()
  assert.equal(install.state.pending, true)
  assert.equal(await install.prompt(), false)
  release()
  await pending
  assert.equal(install.state.pending, false)
})

test('installation : un accès refusé au stockage ne bloque ni le site ni le bouton du menu', async () => {
  const { target } = browser()
  Object.defineProperty(target, 'localStorage', { get() { throw Error('SecurityError') } })
  const install = createInstall(target)
  assert.equal(install.state.offerEligible, false)
  invitation(target, async () => {})
  assert.equal(await install.prompt(), true)
})
