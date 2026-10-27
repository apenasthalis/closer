import { useCallback, useEffect, useRef, useState } from 'react'
import { api, apiMessage } from './api'
import type { Niche, PaymentSource, Team, User } from './types'

export function useApi<T>(url: string | null, params?: Record<string, unknown>, enabled = true) {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(Boolean(url && enabled))
  const [error, setError] = useState('')
  const [version, setVersion] = useState(0)
  const key = JSON.stringify(params || {})

  const reload = useCallback(() => setVersion((current) => current + 1), [])

  useEffect(() => {
    if (!url || !enabled) {
      setLoading(false)
      return
    }
    let active = true
    setLoading(true)
    setError('')
    api.get<T>(url, { params })
      .then((response) => {
        if (active) setData(response.data)
      })
      .catch((requestError) => {
        if (active) setError(apiMessage(requestError))
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [url, key, enabled, version])

  return { data, loading, error, reload, setData }
}

export function useDebounced<T>(value: T, delay = 350) {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const timeout = window.setTimeout(() => setDebounced(value), delay)
    return () => window.clearTimeout(timeout)
  }, [value, delay])
  return debounced
}

export function useLookups(enabled = true) {
  const [niches, setNiches] = useState<Niche[]>([])
  const [teams, setTeams] = useState<Team[]>([])
  const [users, setUsers] = useState<User[]>([])
  const [loading, setLoading] = useState(enabled)
  const [version, setVersion] = useState(0)

  const reload = useCallback(() => setVersion((current) => current + 1), [])

  useEffect(() => {
    if (!enabled) return
    let active = true
    setLoading(true)
    Promise.allSettled([
      api.get('/niches'),
      api.get('/teams'),
      api.get('/users/options'),
    ]).then(([nichesResult, teamsResult, usersResult]) => {
      if (!active) return
      if (nichesResult.status === 'fulfilled') setNiches(nichesResult.value.data.data || [])
      if (teamsResult.status === 'fulfilled') setTeams(teamsResult.value.data.data || [])
      if (usersResult.status === 'fulfilled') setUsers(usersResult.value.data.data || [])
    }).finally(() => {
      if (active) setLoading(false)
    })
    return () => {
      active = false
    }
  }, [enabled, version])

  return { niches, teams, users, loading, reload }
}

export function useInterval(callback: () => void, delay: number | null) {
  const saved = useRef(callback)
  useEffect(() => {
    saved.current = callback
  }, [callback])
  useEffect(() => {
    if (delay === null) return
    const interval = window.setInterval(() => saved.current(), delay)
    return () => window.clearInterval(interval)
  }, [delay])
}

export function usePaymentSources() {
  const [sources, setSources] = useState<PaymentSource[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let active = true
    setLoading(true)
    api.get('/payment-sources')
      .then((response) => {
        if (active) setSources(response.data.data || [])
      })
      .catch(() => undefined)
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => {
      active = false
    }
  }, [])

  const bySlug = (slug?: string | null) => sources.find((source) => source.slug === slug) || null

  return { sources, loading, bySlug }
}

export function useLeadOptions(enabled = true) {
  const [leads, setLeads] = useState<Array<{ id: string; name: string; company?: string | null }>>([])
  useEffect(() => {
    if (!enabled) return
    let active = true
    api.get('/leads', { params: { per_page: 100, sort: 'name' } })
      .then((response) => {
        if (active) setLeads(response.data.data || [])
      })
      .catch(() => undefined)
    return () => {
      active = false
    }
  }, [enabled])
  return leads
}
