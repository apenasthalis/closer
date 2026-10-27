import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Link, NavLink, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/auth/AuthProvider'
import { api, apiMessage } from '@/lib/api'
import type { DockerStatus } from '@/lib/types'
import { Avatar, Badge, Button, Field, Icon, Input, Modal, Spinner, useToast, type IconNameType } from './ui'
import { dateTime } from '@/lib/format'

interface NavItem {
  to: string
  label: string
  icon: IconNameType
  permission?: 'view_financials' | 'manage_users' | 'manage_settings' | 'manage_playbooks'
  roles?: string[]
}

const navGroups: Array<{ label: string; items: NavItem[] }> = [
  {
    label: 'Operação',
    items: [
      { to: '/', label: 'Visão Geral', icon: 'overview' },
      { to: '/pipeline', label: 'Pipeline', icon: 'pipeline' },
      { to: '/leads', label: 'Leads', icon: 'leads' },
      { to: '/dashboard', label: 'Dashboard', icon: 'dashboard' },
      { to: '/reminders', label: 'Lembretes', icon: 'reminders' },
    ],
  },
  {
    label: 'Inteligência comercial',
    items: [
      { to: '/calls', label: 'Calls & Análise', icon: 'calls' },
      { to: '/library', label: 'Biblioteca', icon: 'library' },
    ],
  },
  {
    label: 'Financeiro',
    items: [
      { to: '/proposals', label: 'Propostas', icon: 'proposals' },
      { to: '/contracts', label: 'Contratos', icon: 'contracts' },
      { to: '/payments', label: 'Parcelas', icon: 'payments' },
      { to: '/commissions', label: 'Comissões', icon: 'commissions' },
    ],
  },
  {
    label: 'Gestão',
    items: [
      { to: '/reports', label: 'Relatórios', icon: 'reports', permission: 'view_financials' },
      { to: '/users', label: 'Usuários', icon: 'users', permission: 'manage_users' },
      { to: '/teams', label: 'Equipes', icon: 'briefcase', roles: ['admin'] },
      { to: '/niches', label: 'Nichos', icon: 'niches', roles: ['admin'] },
      { to: '/settings', label: 'Configurações', icon: 'settings', permission: 'manage_settings' },
      { to: '/audit', label: 'Auditoria', icon: 'audit', roles: ['admin'] },
    ],
  },
]

const pageMeta: Array<{ match: (path: string) => boolean; title: string; subtitle: string }> = [
  { match: (path) => path === '/', title: 'Visão Geral', subtitle: 'Métricas consolidadas de leads, receita e performance comercial.' },
  { match: (path) => path === '/pipeline', title: 'Pipeline', subtitle: 'Arraste os leads entre etapas e acompanhe quantidade e valor por coluna.' },
  { match: (path) => path === '/leads', title: 'Leads', subtitle: 'Base completa de oportunidades, filtros e cadastro comercial.' },
  { match: (path) => path.startsWith('/leads/'), title: 'Ficha do lead', subtitle: 'Histórico, próximos passos, documentos e financeiro da oportunidade.' },
  { match: (path) => path === '/dashboard', title: 'Dashboard', subtitle: 'Reuniões, conversão, ciclo de vendas e ranking de closers.' },
  { match: (path) => path === '/reminders', title: 'Lembretes', subtitle: 'Centro de tarefas comerciais com priorização e automações idempotentes.' },
  { match: (path) => path === '/calls', title: 'Calls & Análise', subtitle: 'Transcrições, análise local de SPIN, objeções, talk-time e playbook.' },
  { match: (path) => path === '/library', title: 'Biblioteca comercial', subtitle: 'Scripts por nicho, playbooks e banco histórico de objeções.' },
  { match: (path) => path === '/proposals', title: 'Propostas', subtitle: 'Versões, status, condições comerciais e PDFs gerados no servidor.' },
  { match: (path) => path === '/contracts', title: 'Contratos', subtitle: 'Documentos vinculados a propostas, assinatura e status.' },
  { match: (path) => path === '/payments', title: 'Parcelas', subtitle: 'Recebimentos, vencimentos e inadimplência por oportunidade.' },
  { match: (path) => path === '/commissions', title: 'Comissões', subtitle: 'Regras configuráveis, apuração por período e aprovação.' },
  { match: (path) => path === '/reports', title: 'Relatórios', subtitle: 'Relatório semanal, análise de perdas e exportações CSV.' },
  { match: (path) => path === '/users', title: 'Usuários', subtitle: 'Perfis, permissões, equipes e acesso.' },
  { match: (path) => path === '/teams', title: 'Equipes', subtitle: 'Estrutura comercial e isolamento de dados por equipe.' },
  { match: (path) => path === '/niches', title: 'Nichos', subtitle: 'Segmentos configuráveis usados em leads, scripts e relatórios.' },
  { match: (path) => path === '/settings', title: 'Configurações', subtitle: 'Sistema e estado real das integrações externas.' },
  { match: (path) => path === '/audit', title: 'Auditoria', subtitle: 'Trilha de ações administrativas, financeiras e de CRM.' },
]

const SIDEBAR_COLLAPSED_KEY = 'closer.sidebar.collapsed'

const isMobileViewport = () => typeof window !== 'undefined' && window.matchMedia('(max-width: 860px)').matches

function metaFor(path: string) {
  return pageMeta.find((item) => item.match(path)) || { title: 'Closer OS', subtitle: 'CRM comercial.' }
}

function DockerWidget() {
  const { can, user } = useAuth()
  const { notify } = useToast()
  const [open, setOpen] = useState(false)
  const [status, setStatus] = useState<DockerStatus | null>(null)
  const [loading, setLoading] = useState(false)
  const [starting, setStarting] = useState(false)

  const load = async () => {
    setLoading(true)
    try {
      const { data } = await api.get('/system/docker')
      setStatus(data.data)
    } catch (error) {
      setStatus({ available: false, reason: apiMessage(error), services: [] })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (!can('control_docker')) return
    load()
    const interval = window.setInterval(load, 30000)
    return () => window.clearInterval(interval)
  }, [can])

  if (!can('control_docker') || !user) return null

  const tone = loading ? 'warn' : status?.available ? 'ok' : 'err'
  const ready = status?.services?.length
    ? status.services.every((service) => ['running', 'healthy', 'up'].includes(String(service.state || service.status || '').toLowerCase()))
    : false

  const start = async () => {
    setStarting(true)
    try {
      await api.post('/system/docker/start')
      notify('Solicitação enviada. Verificando o ambiente...', 'info')
      window.setTimeout(load, 1800)
    } catch (error) {
      notify(apiMessage(error), 'error')
    } finally {
      setStarting(false)
    }
  }

  return (
    <>
      <button className="docker-btn" onClick={() => { setOpen(true); load() }} title="Estado do ambiente Docker">
        <span className={`docker-dot ${tone}`} />
        <Icon name="docker" size={15} />
        <span>{loading ? 'Verificando' : status?.available ? (ready ? 'Ambiente pronto' : 'Ambiente parcial') : 'Docker offline'}</span>
      </button>
      <Modal
        open={open}
        onClose={() => setOpen(false)}
        title="Ambiente Docker"
        subtitle="Controle administrativo local com operações permitidas, sem shell arbitrário."
        footer={(
          <>
            <Button icon="refresh" onClick={load} loading={loading}>Verificar</Button>
            <Button variant="primary" icon="play" onClick={start} loading={starting} disabled={loading || !status?.available}>Iniciar serviços</Button>
          </>
        )}
      >
        {!status && loading ? <div className="loading-inline"><Spinner size={14} /> Consultando agente local...</div> : null}
        {status ? (
          <div className="stack-sm">
            <div className="callout">
              <strong>{status.available ? 'Agente local conectado' : 'Agente indisponível'}</strong>
              {status.reason ? <div>{status.reason}</div> : <div>O botão inicia apenas os serviços allowlisted e reutiliza o que já está saudável.</div>}
            </div>
            {status.services?.length ? (
              <div>
                {status.services.map((service) => (
                  <div className="docker-service" key={service.name}>
                    <span className="name"><span className={`docker-dot ${String(service.state || '').includes('run') ? 'ok' : 'warn'}`} />{service.name}</span>
                    <span className="state">{service.health || service.status || service.state || 'desconhecido'}</span>
                  </div>
                ))}
              </div>
            ) : (
              <div className="muted small">Nenhum serviço reportado pelo agente.</div>
            )}
            <div className="callout warn">
              Para inicialização completa pela linha de comando, use <strong>./dev.sh up</strong>. O mecanismo é idempotente e não remove containers ou volumes.
            </div>
          </div>
        ) : null}
      </Modal>
    </>
  )
}

function ProfileModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { user, refreshUser } = useAuth()
  const { notify } = useToast()
  const [form, setForm] = useState({ name: '', phone: '', current_password: '', password: '', password_confirmation: '' })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (open && user) {
      setForm({ name: user.name, phone: user.phone || '', current_password: '', password: '', password_confirmation: '' })
      setError('')
    }
  }, [open, user])

  const save = async () => {
    setSaving(true)
    setError('')
    try {
      const payload: Record<string, unknown> = { name: form.name, phone: form.phone || null }
      if (form.password) {
        payload.current_password = form.current_password
        payload.password = form.password
        payload.password_confirmation = form.password_confirmation
      }
      const { data } = await api.patch('/users/me', payload)
      await refreshUser({ ...user!, ...data.data, permissions: user!.permissions })
      notify('Perfil atualizado.')
      onClose()
    } catch (requestError) {
      setError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Meu perfil"
      subtitle={user?.email}
      footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>Salvar</Button></>}
    >
      {error ? <div className="login-error" style={{ marginBottom: 12 }}>{error}</div> : null}
      <div className="form-grid">
        <Field label="Nome" className="span-2"><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></Field>
        <Field label="Telefone"><Input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></Field>
        <Field label="Perfil"><Input value={user?.role_label || user?.role || ''} disabled /></Field>
        <div className="form-section span-2">
          <div className="form-section-title">Alterar senha</div>
          <div className="form-grid">
            <Field label="Senha atual" className="span-2"><Input type="password" value={form.current_password} onChange={(event) => setForm({ ...form, current_password: event.target.value })} /></Field>
            <Field label="Nova senha"><Input type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></Field>
            <Field label="Confirmar nova senha"><Input type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} /></Field>
          </div>
        </div>
      </div>
    </Modal>
  )
}

export function AppShell({ children }: { children: ReactNode }) {
  const { user, logout, can } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [sidebarCollapsed, setSidebarCollapsed] = useState(() => {
    try {
      return window.localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === '1'
    } catch {
      return false
    }
  })
  const [menuOpen, setMenuOpen] = useState(false)
  const [profileOpen, setProfileOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)
  const meta = metaFor(location.pathname)

  useEffect(() => {
    setSidebarOpen(false)
    setMenuOpen(false)
    window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior })
  }, [location.pathname])

  const closeSidebar = () => {
    setSidebarOpen(false)

    if (isMobileViewport()) return

    setSidebarCollapsed(true)
    try {
      window.localStorage.setItem(SIDEBAR_COLLAPSED_KEY, '1')
    } catch {
      return
    }
  }

  const openSidebar = () => {
    setSidebarCollapsed(false)
    setSidebarOpen(true)
    try {
      window.localStorage.removeItem(SIDEBAR_COLLAPSED_KEY)
    } catch {
      return
    }
  }

  useEffect(() => {
    const click = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) setMenuOpen(false)
    }
    document.addEventListener('mousedown', click)
    return () => document.removeEventListener('mousedown', click)
  }, [])

  const visibleGroups = useMemo(() => navGroups.map((group) => ({
    ...group,
    items: group.items.filter((item) => {
      if (item.roles && !item.roles.includes(user?.role || '')) return false
      if (item.permission && !can(item.permission)) return false
      return true
    }),
  })).filter((group) => group.items.length), [user, can])

  const doLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className={`app-shell ${sidebarCollapsed ? 'sidebar-collapsed' : ''}`}>
      {sidebarOpen ? <div className="sidebar-backdrop" onClick={closeSidebar} /> : null}
      <aside className={`sidebar ${sidebarOpen ? 'open' : ''}`} aria-label="Menu principal">
        <div className="sidebar-head">
          <Link to="/" className="logo" onClick={() => setSidebarOpen(false)}>
            <span className="logo-mark">CO</span>
            <span>
              <span className="logo-name">Closer OS</span>
              <span className="logo-sub">CRM</span>
            </span>
          </Link>
          <button className="icon-btn sidebar-close" onClick={closeSidebar} aria-label="Fechar menu" title="Fechar menu">
            <Icon name="close" size={15} />
          </button>
        </div>
        <nav className="nav">
          {visibleGroups.map((group) => (
            <div key={group.label}>
              <div className="nav-group">{group.label}</div>
              {group.items.map((item) => (
                <NavLink key={item.to} to={item.to} end={item.to === '/'} className={({ isActive }) => `nav-item ${isActive ? 'active' : ''}`}>
                  <Icon name={item.icon} />
                  <span>{item.label}</span>
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
        <div className="nav-spacer" />
        <div className="sidebar-user">
          <Avatar name={user?.name} color={user?.avatar_color} size={29} />
          <div style={{ minWidth: 0 }}>
            <div className="name truncate">{user?.name}</div>
            <div className="mail">{user?.role_label || user?.role}</div>
          </div>
        </div>
      </aside>

      <div className="main-shell">
        <header className="topbar">
          <div className="topbar-left">
            <button className={`icon-btn mobile-menu ${sidebarCollapsed ? 'always-visible' : ''}`} onClick={openSidebar} aria-label="Abrir menu"><Icon name="menu" /></button>
            <div>
              <h1>{meta.title}</h1>
              <div className="sub">{meta.subtitle}</div>
            </div>
          </div>
          <div className="topbar-actions">
            <span className="pill"><Icon name="calendar" size={14} />{new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })}</span>
            <DockerWidget />
            <div ref={menuRef} style={{ position: 'relative' }}>
              <button className="icon-btn" style={{ width: 33, height: 33, borderRadius: '50%', padding: 0, border: 'none' }} onClick={() => setMenuOpen((current) => !current)} aria-label="Menu do usuário">
                <Avatar name={user?.name} color={user?.avatar_color} size={32} />
              </button>
              {menuOpen ? (
                <div style={{ position: 'absolute', right: 0, top: 40, width: 220, background: 'var(--panel)', border: '1px solid var(--line)', borderRadius: 9, boxShadow: 'var(--shadow)', padding: 6, zIndex: 40 }}>
                  <div style={{ padding: '9px 10px', borderBottom: '1px solid var(--line)', marginBottom: 5 }}>
                    <div style={{ fontSize: 12.5, fontWeight: 500 }}>{user?.name}</div>
                    <div style={{ fontSize: 10.5, color: 'var(--muted)', marginTop: 1 }}>{user?.email}</div>
                    {user?.last_login_at ? <div style={{ fontSize: 9.5, color: 'var(--muted-2)', marginTop: 5 }}>Último acesso {dateTime(user.last_login_at)}</div> : null}
                  </div>
                  <button className="nav-item" onClick={() => { setMenuOpen(false); setProfileOpen(true) }}><Icon name="user" /><span>Meu perfil</span></button>
                  <button className="nav-item" onClick={doLogout}><Icon name="logout" /><span>Sair da conta</span></button>
                </div>
              ) : null}
            </div>
          </div>
        </header>
        <main className="content">{children}</main>
      </div>
      <ProfileModal open={profileOpen} onClose={() => setProfileOpen(false)} />
    </div>
  )
}

export function PageHeaderActions({ children }: { children: ReactNode }) {
  return <div className="page-toolbar-right">{children}</div>
}

export function StatusPill({ children, tone = 'neutral' }: { children: ReactNode; tone?: string }) {
  return <Badge tone={tone}>{children}</Badge>
}
