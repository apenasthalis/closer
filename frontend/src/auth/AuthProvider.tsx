import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react'
import {
  login as loginRequest,
  logoutSession,
  refreshOnce,
  setAccessToken,
  setSessionExpiredHandler,
  type SessionResponse,
} from '@/lib/api'
import type { Permissions, User } from '@/lib/types'

interface AuthContextValue {
  user: User | null
  loading: boolean
  authenticated: boolean
  login: (email: string, password: string) => Promise<User>
  logout: () => Promise<void>
  refreshUser: (user?: User) => Promise<void>
  can: (permission: keyof Permissions) => boolean
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)
  const timer = useRef<number | null>(null)

  const scheduleRefresh = useCallback((expiresIn: number) => {
    if (timer.current) window.clearTimeout(timer.current)
    const delay = Math.max(30, expiresIn - 90) * 1000
    timer.current = window.setTimeout(async () => {
      const token = await refreshOnce()
      if (!token) setUser(null)
    }, delay)
  }, [])

  const applySession = useCallback((session: SessionResponse) => {
    setAccessToken(session.access_token)
    setUser(session.user)
    scheduleRefresh(session.expires_in)
  }, [scheduleRefresh])

  useEffect(() => {
    let mounted = true
    setSessionExpiredHandler(() => {
      if (mounted) {
        setAccessToken(null)
        setUser(null)
      }
    })

    const onRefresh = (event: Event) => {
      const session = (event as CustomEvent<SessionResponse>).detail
      if (mounted && session) applySession(session)
    }

    window.addEventListener('closer:session-refreshed', onRefresh)

    refreshOnce().then((token) => {
      if (!mounted) return
      if (!token) setUser(null)
      setLoading(false)
    })

    return () => {
      mounted = false
      window.removeEventListener('closer:session-refreshed', onRefresh)
      setSessionExpiredHandler(null)
      if (timer.current) window.clearTimeout(timer.current)
    }
  }, [applySession])

  const login = useCallback(async (email: string, password: string) => {
    const session = await loginRequest(email, password)
    applySession(session)
    return session.user
  }, [applySession])

  const logout = useCallback(async () => {
    await logoutSession()
    setAccessToken(null)
    setUser(null)
    if (timer.current) window.clearTimeout(timer.current)
  }, [])

  const refreshUser = useCallback(async (nextUser?: User) => {
    if (nextUser) {
      setUser(nextUser)
      return
    }
    const token = await refreshOnce()
    if (!token) setUser(null)
  }, [applySession])

  const can = useCallback((permission: keyof Permissions) => Boolean(user?.permissions?.[permission]), [user])

  return (
    <AuthContext.Provider value={{ user, loading, authenticated: Boolean(user), login, logout, refreshUser, can }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth deve ser usado dentro de AuthProvider')
  return context
}
