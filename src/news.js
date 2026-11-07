import { reactive } from 'vue'

export const news = reactive({ articles: [], loading: false, loaded: false, error: '', stale: false, fetchedAt: null })
let pending, checkedAt = 0
export function loadNews({ force = false } = {}) {
  if (pending) return pending
  if (!force && news.loaded && Date.now() - checkedAt < 60000) return Promise.resolve()
  news.loading = true
  pending = (async () => {
    try {
      const response = await fetch('/api/news', { signal: AbortSignal.timeout(12000), headers: { Accept: 'application/json' } })
      const data = await response.json()
      if (!response.ok) throw new Error(data.error || 'Les actualités ne sont pas disponibles pour le moment.')
      news.articles = data.articles
      news.stale = data.stale
      news.fetchedAt = data.fetchedAt
      news.error = data.error || ''
    } catch {
      news.error = 'Les actualités ne sont pas disponibles pour le moment.'
      news.stale = true
    } finally {
      news.loading = false
      news.loaded = true
      checkedAt = Date.now()
      pending = null
    }
  })()
  return pending
}
