import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ButtonHTMLAttributes,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
} from 'react'
import { initials } from '@/lib/format'

type IconName =
  | 'overview' | 'pipeline' | 'leads' | 'dashboard' | 'reminders' | 'calls' | 'library'
  | 'proposals' | 'contracts' | 'payments' | 'commissions' | 'reports' | 'users'
  | 'niches' | 'settings' | 'audit' | 'docker' | 'search' | 'plus' | 'close'
  | 'chevronDown' | 'chevronLeft' | 'chevronRight' | 'edit' | 'trash' | 'download'
  | 'upload' | 'external' | 'check' | 'clock' | 'calendar' | 'whatsapp' | 'phone'
  | 'mail' | 'money' | 'chart' | 'filter' | 'refresh' | 'logout' | 'menu'
  | 'sparkle' | 'play' | 'alert' | 'info' | 'file' | 'target' | 'lock'
  | 'copy' | 'eye' | 'arrowRight' | 'arrowLeft' | 'dots' | 'building' | 'message'
  | 'send' | 'checkCircle' | 'xCircle' | 'user' | 'trendingUp' | 'trendingDown'
  | 'sliders' | 'briefcase' | 'inbox'

const paths: Record<IconName, ReactNode> = {
  overview: <><circle cx="12" cy="12" r="9" /><path d="M12 3v18M3 12h18" /></>,
  pipeline: <><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M9 4v16M15 4v16" /></>,
  leads: <><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6" /></>,
  dashboard: <><path d="M3 3v18h18" /><path d="M7 15l4-5 3 3 5-7" /></>,
  reminders: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  calls: <><path d="M4 14a8 8 0 0 1 16 0" /><rect x="2.5" y="14" width="4" height="7" rx="1.5" /><rect x="17.5" y="14" width="4" height="7" rx="1.5" /><path d="M12 3v3" /></>,
  library: <><path d="M5 4h4v16H5zM11 4h4v16h-4z" /><path d="m17.5 5 3.5 14" /></>,
  proposals: <><path d="M6 2h9l4 4v16H6z" /><path d="M15 2v5h4M9 12h7M9 16h5" /></>,
  contracts: <><path d="M7 3h10v18H7z" /><path d="M10 8h4M10 12h4M10 16h4" /></>,
  payments: <><rect x="2.5" y="5" width="19" height="14" rx="2" /><path d="M2.5 10h19M6 15h4" /></>,
  commissions: <><circle cx="9" cy="7" r="3" /><path d="M2 21v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1M18 8h4M20 6v4" /></>,
  reports: <><path d="M4 20V10M10 20V4M16 20v-7M22 20H2" /></>,
  users: <><circle cx="9" cy="8" r="3" /><path d="M2 21v-1a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v1M16 4a3 3 0 0 1 0 6M18 15a5 5 0 0 1 4 5v1" /></>,
  niches: <><path d="M12 2 3 7v10l9 5 9-5V7z" /><path d="m3 7 9 5 9-5M12 12v10" /></>,
  settings: <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z" /></>,
  audit: <><path d="M9 3h6l1 3h4v15H4V6h4z" /><path d="M9 12h6M9 16h4" /></>,
  docker: <><path d="M3 13h15a4 4 0 0 0 4-4h-4V6h-4v3h-4V6H6v3H3z" /><path d="M3 13c0 5 4 8 9 8 4 0 7-2 8-5M7 10V8h2v2M11 10V8h2v2M15 10V8h2v2" /></>,
  search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-4-4" /></>,
  plus: <path d="M12 5v14M5 12h14" />,
  close: <path d="m6 6 12 12M18 6 6 18" />,
  chevronDown: <path d="m6 9 6 6 6-6" />,
  chevronLeft: <path d="m15 18-6-6 6-6" />,
  chevronRight: <path d="m9 18 6-6-6-6" />,
  edit: <><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></>,
  trash: <><path d="M3 6h18M8 6V3h8v3M19 6l-1 15H6L5 6M10 11v6M14 11v6" /></>,
  download: <><path d="M12 3v12M7 10l5 5 5-5" /><path d="M4 21h16" /></>,
  upload: <><path d="M12 17V5M7 10l5-5 5 5" /><path d="M4 21h16" /></>,
  external: <><path d="M14 3h7v7M21 3l-9 9" /><path d="M19 13v7H4V5h7" /></>,
  check: <path d="m4 12 5 5L20 6" />,
  clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  calendar: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M8 3v4M16 3v4M3 10h18" /></>,
  whatsapp: <><path d="M20 11.5a8.5 8.5 0 0 1-12.6 7.5L3 20l1.1-4.2A8.5 8.5 0 1 1 20 11.5z" /><path d="M8.5 8.5c.5 2.5 2.5 4.5 5 5l1.3-1.4 2 1-.4 1.9c-3.8.6-8.6-4.2-9.2-8l1.9-.4z" /></>,
  phone: <path d="M6 2h4l2 5-2.5 1.5a11 11 0 0 0 6 6L17 12l5 2v4a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2z" />,
  mail: <><rect x="2" y="4" width="20" height="16" rx="2" /><path d="m3 5 9 7 9-7" /></>,
  money: <><circle cx="12" cy="12" r="9" /><path d="M12 6v12M15 9.5c0-1.4-1.3-2.5-3-2.5s-3 1-3 2.4c0 3.6 6 1.5 6 5.1 0 1.5-1.3 2.5-3 2.5s-3-1.1-3-2.5" /></>,
  chart: <><path d="M3 3v18h18" /><path d="m7 15 4-5 3 3 5-7" /></>,
  filter: <path d="M3 4h18l-7 8v7l-4 2v-9z" />,
  refresh: <><path d="M20 7v5h-5M4 17v-5h5" /><path d="M6.1 9a7 7 0 0 1 11.4-2L20 9M4 15l2.5 2a7 7 0 0 0 11.4-2" /></>,
  logout: <><path d="M9 3H4v18h5M14 8l4 4-4 4M18 12H8" /></>,
  menu: <path d="M3 6h18M3 12h18M3 18h18" />,
  sparkle: <><path d="m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z" /><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z" /></>,
  play: <path d="m8 5 11 7-11 7z" />,
  alert: <><path d="M12 3 2 21h20z" /><path d="M12 9v5M12 18v.1" /></>,
  info: <><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8v.1" /></>,
  file: <><path d="M6 2h9l4 4v16H6z" /><path d="M15 2v5h4" /></>,
  target: <><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1" /></>,
  lock: <><rect x="4" y="10" width="16" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" /></>,
  copy: <><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1" /></>,
  eye: <><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" /><circle cx="12" cy="12" r="3" /></>,
  arrowRight: <path d="M5 12h14M13 6l6 6-6 6" />,
  arrowLeft: <path d="M19 12H5M11 18l-6-6 6-6" />,
  dots: <><circle cx="5" cy="12" r="1" /><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /></>,
  building: <><rect x="4" y="3" width="16" height="18" rx="1" /><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3" /></>,
  message: <path d="M21 15a3 3 0 0 1-3 3H8l-5 3V6a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3z" />,
  send: <><path d="m22 2-7 20-4-9-9-4z" /><path d="M22 2 11 13" /></>,
  checkCircle: <><circle cx="12" cy="12" r="9" /><path d="m8 12 3 3 5-6" /></>,
  xCircle: <><circle cx="12" cy="12" r="9" /><path d="m9 9 6 6M15 9l-6 6" /></>,
  user: <><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.5-6 8-6s8 2 8 6" /></>,
  trendingUp: <><path d="m3 17 6-6 4 4 8-8" /><path d="M15 7h6v6" /></>,
  trendingDown: <><path d="m3 7 6 6 4-4 8 8" /><path d="M15 17h6v-6" /></>,
  sliders: <><path d="M4 6h16M4 12h16M4 18h16" /><circle cx="9" cy="6" r="2" /><circle cx="15" cy="12" r="2" /><circle cx="7" cy="18" r="2" /></>,
  briefcase: <><rect x="3" y="7" width="18" height="14" rx="2" /><path d="M8 7V4h8v3M3 12h18" /></>,
  inbox: <><path d="M3 12h5l2 3h4l2-3h5" /><path d="M5 3h14l2 9v9H3v-9z" /></>,
}

export function Icon({ name, size = 18, className, title }: { name: IconName; size?: number; className?: string; title?: string }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.8"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={className}
      aria-hidden={title ? undefined : true}
      role={title ? 'img' : undefined}
    >
      {title ? <title>{title}</title> : null}
      {paths[name]}
    </svg>
  )
}

export type IconNameType = IconName

export function Button({
  variant = 'secondary',
  size = 'md',
  loading,
  icon,
  children,
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'soft'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  icon?: IconName
}) {
  return (
    <button className={`btn btn-${variant} btn-${size} ${className}`} disabled={loading || props.disabled} {...props}>
      {loading ? <Spinner size={14} /> : icon ? <Icon name={icon} size={size === 'sm' ? 14 : 16} /> : null}
      {children}
    </button>
  )
}

export function IconButton({
  icon,
  label,
  className = '',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { icon: IconName; label: string }) {
  return (
    <button className={`icon-btn ${className}`} aria-label={label} title={label} {...props}>
      <Icon name={icon} size={16} />
    </button>
  )
}

export function Spinner({ size = 18 }: { size?: number }) {
  return <span className="spinner" style={{ width: size, height: size }} aria-label="Carregando" />
}

export function Badge({ tone = 'neutral', children, className = '' }: { tone?: string; children: ReactNode; className?: string }) {
  return <span className={`badge badge-${tone} ${className}`}>{children}</span>
}

export function Avatar({ name, color, size = 30 }: { name?: string | null; color?: string | null; size?: number }) {
  return (
    <span className="avatar-ui" style={{ width: size, height: size, background: color || undefined, fontSize: Math.max(10, size * 0.36) }}>
      {initials(name)}
    </span>
  )
}

export function Panel({ title, subtitle, action, children, className = '', padded = true }: {
  title?: ReactNode
  subtitle?: ReactNode
  action?: ReactNode
  children: ReactNode
  className?: string
  padded?: boolean
}) {
  return (
    <section className={`panel ${padded ? 'panel-padded' : ''} ${className}`}>
      {(title || action) && (
        <header className="panel-head">
          <div>
            {title ? <h3>{title}</h3> : null}
            {subtitle ? <p className="panel-sub">{subtitle}</p> : null}
          </div>
          {action ? <div className="panel-action">{action}</div> : null}
        </header>
      )}
      {children}
    </section>
  )
}

export function Stat({
  label,
  value,
  caption,
  icon,
  tone = 'green',
  trend,
}: {
  label: string
  value: ReactNode
  caption?: ReactNode
  icon?: IconName
  tone?: 'green' | 'blue' | 'purple' | 'amber' | 'rose'
  trend?: ReactNode
}) {
  return (
    <div className="kpi">
      <div className="kpi-top">
        <span className="kpi-label">{label}</span>
        {icon ? <span className={`icon-badge tone-${tone}`}><Icon name={icon} size={14} /></span> : null}
      </div>
      <div className="kpi-value">{value}</div>
      <div className="kpi-cap">{trend}{trend && caption ? <span className="dot-sep">·</span> : null}{caption}</div>
    </div>
  )
}

export function Field({ label, error, hint, required, children, className = '' }: {
  label?: ReactNode
  error?: ReactNode
  hint?: ReactNode
  required?: boolean
  children: ReactNode
  className?: string
}) {
  return (
    <div className={`field ${className}`}>
      {label ? <label>{label}{required ? <span className="req"> *</span> : null}</label> : null}
      {children}
      {error ? <small className="field-error">{error}</small> : hint ? <small className="field-hint">{hint}</small> : null}
    </div>
  )
}

export function Input(props: InputHTMLAttributes<HTMLInputElement>) {
  return <input {...props} className={`input ${props.className || ''}`} />
}

export function Select(props: SelectHTMLAttributes<HTMLSelectElement>) {
  return <select {...props} className={`input select ${props.className || ''}`} />
}

export function Textarea(props: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea {...props} className={`input textarea ${props.className || ''}`} />
}

export function SearchInput({ value, onChange, placeholder = 'Buscar...' }: { value: string; onChange: (value: string) => void; placeholder?: string }) {
  return (
    <div className="search-input">
      <Icon name="search" size={16} />
      <input value={value} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} />
      {value ? <button onClick={() => onChange('')} aria-label="Limpar busca"><Icon name="close" size={14} /></button> : null}
    </div>
  )
}

export function Modal({
  open,
  onClose,
  title,
  subtitle,
  children,
  footer,
  size = 'md',
}: {
  open: boolean
  onClose: () => void
  title: ReactNode
  subtitle?: ReactNode
  children: ReactNode
  footer?: ReactNode
  size?: 'sm' | 'md' | 'lg' | 'xl'
}) {
  useEffect(() => {
    if (!open) return
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    document.body.classList.add('modal-open')
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.classList.remove('modal-open')
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose() }}>
      <div className={`modal modal-${size}`} role="dialog" aria-modal="true">
        <header className="modal-head">
          <div>
            <h3>{title}</h3>
            {subtitle ? <p>{subtitle}</p> : null}
          </div>
          <IconButton icon="close" label="Fechar" onClick={onClose} />
        </header>
        <div className="modal-body">{children}</div>
        {footer ? <footer className="modal-footer">{footer}</footer> : null}
      </div>
    </div>
  )
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel = 'Confirmar',
  tone = 'danger',
  loading,
  onConfirm,
  onClose,
}: {
  open: boolean
  title: string
  message: ReactNode
  confirmLabel?: string
  tone?: 'danger' | 'primary'
  loading?: boolean
  onConfirm: () => void
  onClose: () => void
}) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      size="sm"
      footer={(
        <>
          <Button onClick={onClose}>Cancelar</Button>
          <Button variant={tone} loading={loading} onClick={onConfirm}>{confirmLabel}</Button>
        </>
      )}
    >
      <p className="confirm-text">{message}</p>
    </Modal>
  )
}

export function Tabs({ tabs, value, onChange }: { tabs: Array<{ value: string; label: ReactNode; count?: number }>; value: string; onChange: (value: string) => void }) {
  return (
    <div className="tabs" role="tablist">
      {tabs.map((tab) => (
        <button
          key={tab.value}
          role="tab"
          aria-selected={value === tab.value}
          className={value === tab.value ? 'active' : ''}
          onClick={() => onChange(tab.value)}
        >
          {tab.label}
          {typeof tab.count === 'number' ? <span className="tab-count">{tab.count}</span> : null}
        </button>
      ))}
    </div>
  )
}

export function Pagination({ page, lastPage, total, onChange }: { page: number; lastPage: number; total: number; onChange: (page: number) => void }) {
  if (lastPage <= 1) return null
  return (
    <div className="pagination">
      <span>{total} registro(s) · página {page} de {lastPage}</span>
      <div>
        <IconButton icon="chevronLeft" label="Página anterior" disabled={page <= 1} onClick={() => onChange(page - 1)} />
        <IconButton icon="chevronRight" label="Próxima página" disabled={page >= lastPage} onClick={() => onChange(page + 1)} />
      </div>
    </div>
  )
}

export function EmptyState({ icon = 'inbox', title, description, action }: { icon?: IconName; title: string; description?: ReactNode; action?: ReactNode }) {
  return (
    <div className="empty">
      <span className="empty-icon"><Icon name={icon} size={24} /></span>
      <h3>{title}</h3>
      {description ? <p>{description}</p> : null}
      {action}
    </div>
  )
}

export function LoadingState({ label = 'Carregando dados...' }: { label?: string }) {
  return (
    <div className="loading-state">
      <Spinner size={22} />
      <span>{label}</span>
    </div>
  )
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div className="error-state">
      <Icon name="alert" size={22} />
      <div>
        <strong>Não foi possível carregar</strong>
        <p>{message}</p>
      </div>
      {onRetry ? <Button size="sm" onClick={onRetry}>Tentar novamente</Button> : null}
    </div>
  )
}

export function ProgressBar({ value, tone = 'green' }: { value: number; tone?: string }) {
  return (
    <div className="progress">
      <span className={`progress-fill tone-bg-${tone}`} style={{ width: `${Math.max(0, Math.min(100, value))}%` }} />
    </div>
  )
}

type ToastTone = 'success' | 'error' | 'info'
interface Toast {
  id: number
  message: string
  tone: ToastTone
}

const ToastContext = createContext<{ notify: (message: string, tone?: ToastTone) => void } | null>(null)

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])

  const notify = useCallback((message: string, tone: ToastTone = 'success') => {
    const id = Date.now() + Math.random()
    setToasts((current) => [...current, { id, message, tone }])
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 4200)
  }, [])

  const context = useMemo(() => ({ notify }), [notify])

  return (
    <ToastContext.Provider value={context}>
      {children}
      <div className="toasts">
        {toasts.map((toast) => (
          <div key={toast.id} className={`toast toast-${toast.tone}`}>
            <Icon name={toast.tone === 'success' ? 'checkCircle' : toast.tone === 'error' ? 'xCircle' : 'info'} size={17} />
            <span>{toast.message}</span>
            <button onClick={() => setToasts((current) => current.filter((item) => item.id !== toast.id))} aria-label="Fechar"><Icon name="close" size={14} /></button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const context = useContext(ToastContext)
  if (!context) throw new Error('useToast deve ser usado dentro de ToastProvider')
  return context
}

export function SectionTitle({ eyebrow, title, subtitle, action }: { eyebrow?: string; title: string; subtitle?: ReactNode; action?: ReactNode }) {
  return (
    <div className="section-title">
      <div>
        {eyebrow ? <span className="eyebrow">{eyebrow}</span> : null}
        <h2>{title}</h2>
        {subtitle ? <p>{subtitle}</p> : null}
      </div>
      {action}
    </div>
  )
}
