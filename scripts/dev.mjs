import { spawn } from 'node:child_process'
import { loadEnvFile } from 'node:process'
try { loadEnvFile('.env') } catch (error) { if (error.code !== 'ENOENT') throw error }
// Le proxy Vite utilise le serveur Laravel sur le port 8000.
const api = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1'], { stdio: 'inherit' })
const web = spawn(process.execPath, ['node_modules/vite/bin/vite.js'], { stdio: 'inherit' })
let stopping = false
function stop(code = 0) { if (stopping) return; stopping = true; api.kill(); web.kill(); process.exitCode = code }
process.on('SIGINT', () => stop())
process.on('SIGTERM', () => stop())
api.on('error', error => { console.error(`Impossible de lancer PHP : ${error.message}`); stop(1) })
web.on('error', error => { console.error(`Impossible de lancer Vite : ${error.message}`); stop(1) })
api.on('exit', code => stop(code || 0))
web.on('exit', code => stop(code || 0))
