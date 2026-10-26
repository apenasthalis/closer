import axios, { AxiosError, AxiosRequestConfig } from 'axios'
import type { User } from './types'

const baseURL = '/api/v1'

let accessToken: string | null = null
let refreshPromise: Promise<string | null> | null = null
let onSessionExpired: (() => void) | null = null

export const api = axios.create({
  baseURL,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

const refreshClient = axios.create({
  baseURL,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

export function setAccessToken(token: string | null) {
  accessToken = token
}

export function getAccessToken() {
  return accessToken
}

export function setSessionExpiredHandler(handler: (() => void) | null) {
  onSessionExpired = handler
}

export interface SessionResponse {
  access_token: string
  token_type: string
  expires_in: number
  expires_at: number
  user: User
}

export async function login(email: string, password: string): Promise<SessionResponse> {
  const { data } = await refreshClient.post<SessionResponse>('/auth/login', { email, password })
  return data
}

export async function refreshSession(): Promise<SessionResponse | null> {
  try {
    const { data } = await refreshClient.post<SessionResponse>('/auth/refresh')
    return data
  } catch {
    return null
  }
}

export async function logoutSession() {
  try {
    await refreshClient.post('/auth/logout')
  } catch {
    return
  }
}

export function refreshOnce(): Promise<string | null> {
  if (!refreshPromise) {
    refreshPromise = refreshSession()
      .then((session) => {
        if (!session) {
          accessToken = null
          onSessionExpired?.()
          return null
        }
        accessToken = session.access_token
        window.dispatchEvent(new CustomEvent('closer:session-refreshed', { detail: session }))
        return session.access_token
      })
      .finally(() => {
        refreshPromise = null
      })
  }

  return refreshPromise
}

api.interceptors.request.use((config) => {
  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const config = error.config as (AxiosRequestConfig & { _retried?: boolean }) | undefined
    const status = error.response?.status
    const url = String(config?.url || '')

    if (
      status === 401 &&
      config &&
      !config._retried &&
      !url.includes('/auth/login') &&
      !url.includes('/auth/refresh')
    ) {
      config._retried = true
      const token = await refreshOnce()
      if (token) {
        config.headers = config.headers || {}
        config.headers.Authorization = `Bearer ${token}`
        return api.request(config)
      }
    }

    return Promise.reject(error)
  },
)

export function apiMessage(error: unknown, fallback = 'Não foi possível concluir a operação.') {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as any
    if (data?.errors && typeof data.errors === 'object') {
      const first = Object.values(data.errors).flat()[0]
      if (typeof first === 'string') return first
    }
    if (typeof data?.message === 'string' && data.message) return data.message
    if (error.code === 'ERR_NETWORK') return 'Falha de conexão com o servidor.'
  }
  return fallback
}

export function apiErrorCode(error: unknown) {
  if (axios.isAxiosError(error)) {
    return (error.response?.data as any)?.code as string | undefined
  }
  return undefined
}

export async function download(url: string, filename: string) {
  const response = await api.get(url, { responseType: 'blob' })
  const blob = new Blob([response.data], { type: String(response.headers['content-type'] || 'application/octet-stream') })
  const href = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = href
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  setTimeout(() => URL.revokeObjectURL(href), 60000)
}
