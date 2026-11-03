import { reactive } from 'vue'
export const account = reactive({ ready: false, enabled: null, user: null, csrfToken: null, following: { primaryTeamId: null, source: null, teams: [] } })
let pending
export function syncAccount() {
  if (pending) return pending
  pending = accountRequest('/session').finally(() => { account.ready = true; pending = null })
  return pending
}
export async function accountRequest(path, { method = 'GET', body, timeoutMs = 20000 } = {}) {
  const response = await fetch(`/api/account${path}`, {
    method, credentials: 'same-origin', signal: AbortSignal.timeout(timeoutMs),
    headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}), ...(method !== 'GET' && account.csrfToken ? { 'X-CSRF-TOKEN': account.csrfToken } : {}) },
    ...(body ? { body: JSON.stringify(body) } : {}),
  })
  let data
  try { data = await response.json() } catch { throw new Error('Le service des comptes ne répond pas correctement. Réessayez dans un instant.') }
  if (!response.ok) {
    if (response.status === 401 || response.status === 419) { account.user = null; account.csrfToken = null; account.following = { primaryTeamId: null, source: null, teams: [] } }
    const error = new Error(response.status === 419 ? 'Votre session a expiré. Rechargez la page puis réessayez.' : response.status === 429 ? 'Trop de tentatives. Patientez quelques minutes avant de réessayer.' : response.status === 401 ? 'Connectez-vous pour accéder à votre compte.' : response.status >= 500 ? 'Le service des comptes est temporairement indisponible.' : Object.values(data.errors || {}).flat()[0] || data.message || 'La demande n’a pas abouti.')
    error.status = response.status
    throw error
  }
  if ('enabled' in data) account.enabled = data.enabled
  if ('user' in data) {
    if (account.user?.id !== data.user?.id) account.following = { primaryTeamId: null, source: null, teams: [] }
    account.user = data.user
  }
  if ('following' in data) account.following = data.following
  if ('csrfToken' in data) account.csrfToken = data.csrfToken
  return data
}
