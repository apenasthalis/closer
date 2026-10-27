import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { AuthProvider, useAuth } from '@/auth/AuthProvider'
import { AppShell } from '@/components/AppShell'
import { Button, EmptyState, Spinner, ToastProvider } from '@/components/ui'
import { LoginPage } from '@/pages/LoginPage'
import { OverviewPage } from '@/pages/OverviewPage'
import { PipelinePage } from '@/pages/PipelinePage'
import { LeadsPage } from '@/pages/LeadsPage'
import { LeadDetailPage } from '@/pages/LeadDetailPage'
import { DashboardPage } from '@/pages/DashboardPage'
import { RemindersPage } from '@/pages/RemindersPage'
import { CallsPage } from '@/pages/CallsPage'
import { LibraryPage } from '@/pages/LibraryPage'
import { ProposalsPage } from '@/pages/ProposalsPage'
import { ContractsPage } from '@/pages/ContractsPage'
import { PaymentsPage } from '@/pages/PaymentsPage'
import { CommissionsPage } from '@/pages/CommissionsPage'
import { CompaniesPage } from '@/pages/CompaniesPage'
import { ReportsPage } from '@/pages/ReportsPage'
import { UsersPage } from '@/pages/UsersPage'
import { TeamsPage } from '@/pages/TeamsPage'
import { NichesPage } from '@/pages/NichesPage'
import { SettingsPage } from '@/pages/SettingsPage'
import { AuditPage } from '@/pages/AuditPage'
import type { Permissions, Role } from '@/lib/types'

function Splash() {
  return (
    <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 10, color: 'var(--muted)' }}>
      <Spinner size={22} />
      <span>Carregando sessão...</span>
    </div>
  )
}

function Protected({ children }: { children: React.ReactNode }) {
  const { authenticated, loading } = useAuth()
  const location = useLocation()

  if (loading) return <Splash />
  if (!authenticated) return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  return <AppShell>{children}</AppShell>
}

function PermissionGate({ permission, roles, children }: { permission?: keyof Permissions; roles?: Role[]; children: React.ReactNode }) {
  const { user, can } = useAuth()
  const allowed = (!roles || roles.includes(user?.role as Role)) && (!permission || can(permission))
  if (!allowed) {
    return (
      <EmptyState
        icon="lock"
        title="Acesso restrito"
        description="Seu perfil não possui permissão para acessar esta área. Fale com um administrador."
        action={<Button onClick={() => window.history.back()}>Voltar</Button>}
      />
    )
  }
  return <>{children}</>
}

function NotFound() {
  const location = useLocation()
  return (
    <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20 }}>
      <div className="panel panel-padded" style={{ maxWidth: 480, width: '100%', textAlign: 'center' }}>
        <h1 style={{ fontSize: 40, letterSpacing: '-1px', color: 'var(--brand)' }}>404</h1>
        <h2 style={{ fontSize: 17, marginTop: 4 }}>Rota não encontrada</h2>
        <p className="muted" style={{ fontSize: 12.5, marginTop: 6 }}>O endereço <code>{location.pathname}</code> não existe neste CRM.</p>
        <Button variant="primary" style={{ marginTop: 16 }} onClick={() => { window.location.href = '/' }}>Voltar à visão geral</Button>
      </div>
    </div>
  )
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/" element={<Protected><OverviewPage /></Protected>} />
      <Route path="/pipeline" element={<Protected><PipelinePage /></Protected>} />
      <Route path="/leads" element={<Protected><LeadsPage /></Protected>} />
      <Route path="/leads/:id" element={<Protected><LeadDetailPage /></Protected>} />
      <Route path="/dashboard" element={<Protected><DashboardPage /></Protected>} />
      <Route path="/reminders" element={<Protected><RemindersPage /></Protected>} />
      <Route path="/calls" element={<Protected><CallsPage /></Protected>} />
      <Route path="/library" element={<Protected><LibraryPage /></Protected>} />
      <Route path="/proposals" element={<Protected><ProposalsPage /></Protected>} />
      <Route path="/contracts" element={<Protected><ContractsPage /></Protected>} />
      <Route path="/payments" element={<Protected><PaymentsPage /></Protected>} />
      <Route path="/commissions" element={<Protected><CommissionsPage /></Protected>} />
      <Route path="/reports" element={<Protected><PermissionGate roles={['admin', 'manager']}><ReportsPage /></PermissionGate></Protected>} />
      <Route path="/users" element={<Protected><PermissionGate permission="manage_users"><UsersPage /></PermissionGate></Protected>} />
      <Route path="/teams" element={<Protected><PermissionGate roles={['admin']}><TeamsPage /></PermissionGate></Protected>} />
      <Route path="/companies" element={<Protected><PermissionGate roles={['admin']}><CompaniesPage /></PermissionGate></Protected>} />
      <Route path="/niches" element={<Protected><PermissionGate roles={['admin']}><NichesPage /></PermissionGate></Protected>} />
      <Route path="/settings" element={<Protected><PermissionGate permission="manage_settings"><SettingsPage /></PermissionGate></Protected>} />
      <Route path="/audit" element={<Protected><PermissionGate roles={['admin']}><AuditPage /></PermissionGate></Protected>} />
      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}

export default function App() {
  return (
    <AuthProvider>
      <ToastProvider>
        <AppRoutes />
      </ToastProvider>
    </AuthProvider>
  )
}
