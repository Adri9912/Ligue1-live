// Notifications uniquement : aucun cache hors ligne ni suivi quand le site est fermé.
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()))
self.addEventListener('notificationclick', event => {
  event.notification.close()
  const path = event.notification.data?.url
  const url = new URL(/^\/matchs\/\d+$/.test(path) ? path : '/mon-equipe', self.location.origin).href
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(async clients => {
    const existing = clients.find(client => new URL(client.url).origin === self.location.origin)
    if (existing) { await existing.navigate(url); return existing.focus() }
    return self.clients.openWindow(url)
  }))
})
