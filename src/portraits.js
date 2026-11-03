// Une même recherche est partagée entre la liste, le terrain et la fiche joueur.
const requests = new Map()
export function lookupPortrait(url) {
  if (!/^\/api\/portraits\/\d+$/.test(url)) return Promise.resolve({ photo: null })
  if (!requests.has(url)) {
    requests.set(url, fetch(url, { headers: { Accept: 'application/json' } }).then(async response => {
      if (!response.ok) throw new Error('Portrait indisponible')
      const result = await response.json()
      if (result.retryAt) requests.delete(url)
      return result
    }).catch(() => { requests.delete(url); return { photo: null, retryAt: Date.now() + 65000 } }))
  }
  return requests.get(url)
}
