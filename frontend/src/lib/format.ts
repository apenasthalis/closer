export const BRL = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
  maximumFractionDigits: 2,
})

export const compactBRL = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
  notation: 'compact',
  maximumFractionDigits: 1,
})

export const number = new Intl.NumberFormat('pt-BR')

export function money(value: unknown, compact = false) {
  if (value === null || value === undefined || value === '') return '—'
  const parsed = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(parsed)) return '—'
  return compact ? compactBRL.format(parsed) : BRL.format(parsed)
}

export function num(value: unknown) {
  const parsed = typeof value === 'number' ? value : Number(value)
  return Number.isFinite(parsed) ? number.format(parsed) : '—'
}

export function pct(value: unknown, digits = 1) {
  if (value === null || value === undefined) return '—'
  const parsed = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(parsed)) return '—'
  return `${parsed.toLocaleString('pt-BR', { maximumFractionDigits: digits })}%`
}

export function date(value?: string | null, withTime = false) {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '—'
  return parsed.toLocaleDateString('pt-BR', withTime ? { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' } : { day: '2-digit', month: '2-digit', year: 'numeric' })
}

export function time(value?: string | null) {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '—'
  return parsed.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
}

export function dateTime(value?: string | null) {
  if (!value) return '—'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '—'
  return parsed.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

export function isoDate(value: Date) {
  const offset = value.getTimezoneOffset()
  return new Date(value.getTime() - offset * 60000).toISOString().slice(0, 10)
}

export function today() {
  return isoDate(new Date())
}

export function startOfMonth(value = new Date()) {
  const d = new Date(value)
  d.setDate(1)
  return isoDate(d)
}

export function endOfMonth(value = new Date()) {
  const d = new Date(value)
  d.setMonth(d.getMonth() + 1, 0)
  return isoDate(d)
}

export function monthValue(value = new Date()) {
  return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}`
}

export function monthRange(month: string) {
  const [year, monthNumber] = month.split('-').map(Number)
  const from = new Date(year, monthNumber - 1, 1)
  const to = new Date(year, monthNumber, 0)
  return { from: isoDate(from), to: isoDate(to) }
}

export function startOfWeek(value = new Date()) {
  const dateValue = new Date(value)
  const day = dateValue.getDay()
  const diff = day === 0 ? -6 : 1 - day
  dateValue.setDate(dateValue.getDate() + diff)
  return isoDate(dateValue)
}

export function relative(value?: string | null) {
  if (!value) return '—'
  const parsed = new Date(value).getTime()
  if (Number.isNaN(parsed)) return '—'
  const diff = parsed - Date.now()
  const minutes = Math.round(diff / 60000)
  const abs = Math.abs(minutes)
  if (abs < 60) return minutes >= 0 ? `em ${abs} min` : `há ${abs} min`
  const hours = Math.round(abs / 60)
  if (hours < 24) return minutes >= 0 ? `em ${hours}h` : `há ${hours}h`
  const days = Math.round(hours / 24)
  if (days < 30) return minutes >= 0 ? `em ${days}d` : `há ${days}d`
  return date(value)
}

export function initials(name?: string | null) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/).filter(Boolean)
  return ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1]?.[0] || '' : '')).toUpperCase() || '?'
}

export function truncate(value: string, size = 120) {
  return value.length > size ? `${value.slice(0, size).trimEnd()}…` : value
}

export function monthLabel(value: string) {
  const [year, month] = value.split('-').map(Number)
  return new Date(year, month - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
}

export function periodLabel(from?: string | null, to?: string | null) {
  if (!from || !to) return 'Período atual'
  return `${date(from)} — ${date(to)}`
}

export function whatsappLink(phone?: string | null, message?: string) {
  if (!phone) return null
  const digits = phone.replace(/\D/g, '')
  if (!digits) return null
  const withCountry = digits.startsWith('55') ? digits : `55${digits}`
  return `https://wa.me/${withCountry}${message ? `?text=${encodeURIComponent(message)}` : ''}`
}

export function percentOf(value: number, total: number) {
  if (!total) return 0
  return Math.max(0, Math.min(100, (value / total) * 100))
}
