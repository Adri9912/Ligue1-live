import { spawn } from 'node:child_process'
import { loadEnvFile } from 'node:process'
try { loadEnvFile('.env') } catch (error) { if (error.code !== 'ENOENT') throw error }
// Le proxy Vite utilise toujours ce port en développement.
const api = spawn(process.execPath, ['--watch', 'server/index.mjs'], { stdio: 'inherit', env: { ...process.env, PORT: '3001' } })
const web = spawn(process.execPath, ['node_modules/vite/bin/vite.js'], { stdio: 'inherit' })
let stopping = false
function stop(code = 0) { if (stopping) return; stopping = true; api.kill(); web.kill(); process.exitCode = code }
process.on('SIGINT', () => stop())
process.on('SIGTERM', () => stop())
api.on('exit', code => stop(code || 0))
web.on('exit', code => stop(code || 0))
