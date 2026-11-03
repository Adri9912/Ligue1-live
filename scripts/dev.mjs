import { spawn } from 'node:child_process'
import { loadEnvFile } from 'node:process'
import { createConnection } from 'node:net'
try { loadEnvFile('.env') } catch (error) { if (error.code !== 'ENOENT') throw error }
// Option locale : lancer uniquement une instance MySQL privée déjà initialisée.
let mysql
function socketReady() {
  return new Promise(resolve => {
    const socket = createConnection(process.env.DB_SOCKET)
    socket.setTimeout(1000)
    socket.once('connect', () => { socket.destroy(); resolve(true) })
    socket.once('error', () => { socket.destroy(); resolve(false) })
    socket.once('timeout', () => { socket.destroy(); resolve(false) })
  })
}
if (process.env.LOCAL_MYSQL_DATADIR && process.env.DB_SOCKET && !await socketReady()) {
  const folder = process.env.LOCAL_MYSQL_DATADIR
  mysql = spawn('mysqld', ['--no-defaults', `--datadir=${folder}`, `--socket=${process.env.DB_SOCKET}`, '--skip-networking', '--mysqlx=OFF', `--pid-file=${folder}/local.pid`, `--log-error=${folder}/local.log`], { stdio: 'ignore' })
  let failed = false
  mysql.once('error', () => { failed = true })
  let ready = false
  for (let i = 0; i < 50 && !failed; i++) {
    if (await socketReady()) { ready = true; break }
    await new Promise(resolve => setTimeout(resolve, 200))
  }
  if (!ready) { mysql.kill(); throw new Error('MySQL local indisponible. Vérifiez son installation et le dossier privé configuré.') }
}
// Le proxy Vite utilise le serveur Laravel sur le port 8000.
const api = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1'], { stdio: 'inherit', env: { ...process.env, APP_URL: 'http://127.0.0.1:5173' } })
const web = spawn(process.execPath, ['node_modules/vite/bin/vite.js'], { stdio: 'inherit' })
let stopping = false
function stop(code = 0) { if (stopping) return; stopping = true; api.kill(); web.kill(); mysql?.kill(); process.exitCode = code }
process.on('SIGINT', () => stop())
process.on('SIGTERM', () => stop())
api.on('error', error => { console.error(`Impossible de lancer PHP : ${error.message}`); stop(1) })
web.on('error', error => { console.error(`Impossible de lancer Vite : ${error.message}`); stop(1) })
api.on('exit', code => stop(code || 0))
web.on('exit', code => stop(code || 0))
