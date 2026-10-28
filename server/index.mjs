import { createServer } from 'node:http'
import { readFile } from 'node:fs/promises'
import { resolve, extname, sep } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createProvider, ApiError } from './provider.mjs'
import { createHighlightlyProvider } from './highlightly.mjs'
import { createRetro } from './retro.mjs'
import { createClubs } from './clubs.mjs'
import * as demo from './demo.mjs'
import { createControl, refreshModes } from './control.mjs'
export async function createApp(options = {}) {
  const source = options.source || (options.key !== undefined ? 'api-football' : process.env.FOOTBALL_PROVIDER || 'api-football')
  if (!['api-football', 'highlightly'].includes(source)) throw new Error('FOOTBALL_PROVIDER invalide')
  const key = options.key ?? (source === 'highlightly' ? process.env.HIGHLIGHTLY_API_KEY : process.env.FOOTBALL_API_KEY)
  const now = new Date(), defaultSeason = now.getUTCMonth() >= 6 ? now.getUTCFullYear() : now.getUTCFullYear() - 1
  const season = Number(options.season || process.env.FOOTBALL_SEASON || defaultSeason)
  if (!Number.isInteger(season) || season < 2000 || season > 2100) throw new Error('FOOTBALL_SEASON invalide')
  const limit = Number(process.env.FOOTBALL_DAILY_LIMIT || 90)
  if (!Number.isInteger(limit) || limit < 1 || limit > 100) throw new Error('FOOTBALL_DAILY_LIMIT doit être entre 1 et 100')
  const control = await createControl(options.directory)
  const provider = key ? await (source === 'highlightly' ? createHighlightlyProvider : createProvider)({ key, season, dailyLimit: limit, directory: options.directory, getMode: control.getMode, ...(options.fetcher ? { fetcher: options.fetcher } : {}), ...(options.spacingMs !== undefined ? { spacingMs: options.spacingMs } : {}) }) : null
  const retroKey = options.retroKey ?? (options.key !== undefined ? '' : process.env.FOOTBALL_API_KEY)
  const retroClient = source === 'api-football' && provider ? provider : retroKey ? await createProvider({ key: retroKey, season: 2024, dailyLimit: limit, directory: options.directory, getMode: control.getMode, ...(options.retroFetcher ? { fetcher: options.retroFetcher } : {}), ...(options.spacingMs !== undefined ? { spacingMs: options.spacingMs } : {}) }) : null
  const retro = createRetro(retroClient)
  const clubs = createClubs(retroClient, async requestOptions => provider ? (await provider.overview(requestOptions)).teams : demo.teams)
  const mode = provider ? 'api' : 'demo'
  const dist = resolve(fileURLToPath(new URL('../dist/', import.meta.url)))
  const generated = Date.now()
  const meta = { mode, source: mode === 'demo' ? 'demo' : source, season, refreshMinutes: 10 }
  function runtime() { return { refreshMode: control.getMode(), ...(provider ? provider.status() : { budget: { used: 0, limit, remaining: limit, resetAt: new Date().setUTCHours(24, 0, 0, 0), providerRemaining: null, observedAt: null }, nextRefreshAt: null }) } }
  function json(res, status, body) { body = { ...meta, ...runtime(), ...body }; res.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8', 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' }); res.end(JSON.stringify(body)) }
  return createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://localhost')
      if (req.method === 'POST' && url.pathname === '/api/control') {
        if (req.headers['sec-fetch-site'] === 'cross-site') throw new ApiError('Origine non autorisée.', 403)
        if (req.headers.origin) {
          let origin
          try { origin = new URL(req.headers.origin) } catch { throw new ApiError('Origine non autorisée.', 403) }
          if (origin.host !== req.headers.host) throw new ApiError('Origine non autorisée.', 403)
        }
        if (req.headers['content-type']?.split(';')[0] !== 'application/json') throw new ApiError('Un contenu JSON est requis.', 415)
        let text = '', size = 0
        for await (const chunk of req) { size += chunk.length; if (size > 1024) throw new ApiError('Réglage trop volumineux.', 413); text += chunk.toString('utf8') }
        let body
        try { body = JSON.parse(text) } catch { throw new ApiError('Réglage JSON invalide.', 400) }
        if (!refreshModes.includes(body?.mode)) throw new ApiError('Choisissez Auto, Manuel ou Pause.', 400)
        await control.setMode(body.mode)
        return json(res, 200, {})
      }
      if (req.method !== 'GET') return json(res, 405, { error: 'Méthode non autorisée.' })
      const requestOptions = { manual: req.headers['x-l1-manual-refresh'] === '1' }
      if (url.pathname.startsWith('/api/')) {
        let data
        if (/^\/api\/clubs\/\d{1,10}\/players\/\d{1,10}$/.test(url.pathname)) {
          const parts = url.pathname.split('/')
          return json(res, 200, await clubs.player(Number(parts[3]), Number(parts[5]), requestOptions))
        }
        if (/^\/api\/clubs\/\d{1,10}\/lineup$/.test(url.pathname)) {
          if (!provider?.clubLineup) throw new ApiError('Les compositions de la saison actuelle nécessitent la connexion Highlightly.', 503)
          const rawOffset = url.searchParams.get('offset') || '0'
          if (!/^\d{1,3}$/.test(rawOffset)) throw new ApiError('Position de recherche invalide.', 400)
          return json(res, 200, await provider.clubLineup(Number(url.pathname.split('/')[3]), { ...requestOptions, offset:Number(rawOffset) }))
        }
        if (/^\/api\/clubs\/\d{1,10}$/.test(url.pathname)) return json(res, 200, await clubs.detail(Number(url.pathname.split('/').at(-1)), requestOptions))
        if (url.pathname === '/api/retro') return json(res, 200, retro.metadata())
        if (/^\/api\/retro\/\d{4}\/(standings|matches|scorers|assists)$/.test(url.pathname)) {
          const [, , , year, section] = url.pathname.split('/')
          return json(res, 200, await retro.get(Number(year), section, requestOptions))
        }
        if (url.pathname === '/api/status') return json(res, 200, meta)
        if (url.pathname === '/api/overview') data = provider ? await provider.overview(requestOptions) : { standings: demo.standings, teams: demo.teams, fetchedAt: generated, stale: false }
        else if (url.pathname === '/api/matches') data = provider ? await provider.matches(requestOptions) : { matches: demo.matches, fetchedAt: generated, stale: false }
        else if (/^\/api\/players\/(scorers|assists)$/.test(url.pathname)) data = provider ? await provider.players(url.pathname.split('/').at(-1), requestOptions) : { players: demo.demoPlayers(url.pathname.split('/').at(-1)), fetchedAt: generated, stale: false }
        else if (/^\/api\/matches\/\d{1,10}$/.test(url.pathname)) {
          const id = Number(url.pathname.split('/').at(-1))
          data = provider ? await provider.detail(id, requestOptions) : demo.demoDetails(id)
          if (!data) throw new ApiError('Match introuvable.', 404)
          data = { fetchedAt: generated, stale: false, ...data }
        } else throw new ApiError('Route introuvable.', 404)
        return json(res, 200, { ...meta, ...data })
      }
      const pathname = decodeURIComponent(url.pathname)
      let file = resolve(dist, '.' + pathname)
      if (file !== dist && !file.startsWith(dist + sep)) throw new ApiError('Accès refusé.', 403)
      if (!extname(file)) file = resolve(dist, 'index.html')
      const types = { '.html': 'text/html; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8', '.svg': 'image/svg+xml', '.png': 'image/png', '.woff2': 'font/woff2' }
      const body = await readFile(file)
      res.writeHead(200, { 'Content-Type': types[extname(file)] || 'application/octet-stream', 'X-Content-Type-Options': 'nosniff', 'Cache-Control': extname(file) === '.html' ? 'no-cache' : 'public, max-age=3600', 'Referrer-Policy': 'strict-origin-when-cross-origin', 'Content-Security-Policy': "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' https://media.api-sports.io https://highlightly.net data:; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'" }); res.end(body)
    } catch (error) { json(res, error.status || (error.code === 'ENOENT' ? 404 : 500), { error: error instanceof ApiError ? error.message : error.code === 'ENOENT' ? 'Page introuvable. Lancez npm run build avant npm start.' : 'Une erreur est survenue. Réessayez.' }) }
  })
}
if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const server = await createApp()
  server.on('error', error => { console.error(`Impossible de lancer le serveur : ${error.code}`); process.exitCode = 1 })
  server.listen(Number(process.env.PORT || 3001), process.env.HOST || '127.0.0.1', () => console.log(`Ligue 1 Live — http://${process.env.HOST || '127.0.0.1'}:${process.env.PORT || 3001} (${(process.env.FOOTBALL_PROVIDER === 'highlightly' ? process.env.HIGHLIGHTLY_API_KEY : process.env.FOOTBALL_API_KEY) ? 'API réelle' : 'démonstration'})`))
}
