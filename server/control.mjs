import { mkdir, readFile, writeFile, rename } from 'node:fs/promises'
import { join } from 'node:path'
export const refreshModes = ['auto', 'manual', 'pause']
export async function createControl(directory = '.cache') {
  let mode = 'auto'
  try { const saved = JSON.parse(await readFile(join(directory, 'control.json'), 'utf8')); if (refreshModes.includes(saved.mode)) mode = saved.mode } catch (error) { if (error.code !== 'ENOENT') console.warn('Réglage d’actualisation illisible : mode automatique.') }
  let saves = Promise.resolve()
  return {
    getMode: () => mode,
    async setMode(next) {
      if (!refreshModes.includes(next)) throw new Error('Mode invalide.')
      const previous = mode
      mode = next // Les appels en attente voient immédiatement une pause.
      saves = saves.catch(() => {}).then(async () => {
        await mkdir(directory, { recursive: true })
        await writeFile(join(directory, 'control.tmp'), JSON.stringify({ mode: next }), { mode: 0o600 })
        await rename(join(directory, 'control.tmp'), join(directory, 'control.json'))
      })
      try { await saves } catch (error) { if (mode === next) mode = previous; throw error }
      return mode
    },
  }
}
