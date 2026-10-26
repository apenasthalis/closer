import { useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useAuth } from '@/auth/AuthProvider'
import { apiMessage } from '@/lib/api'
import { Button, Field, Icon, Input, Spinner } from '@/components/ui'

export function LoginPage() {
  const { login, authenticated, loading } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [email, setEmail] = useState('admin@closer.local')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState('')

  if (loading) {
    return (
      <div className="login-shell">
        <div className="login-main"><Spinner size={24} /></div>
      </div>
    )
  }

  if (authenticated) {
    const destination = (location.state as { from?: string } | null)?.from || '/'
    return <Navigate to={destination} replace />
  }

  const submit = async (event: React.FormEvent) => {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    try {
      await login(email.trim(), password)
      const destination = (location.state as { from?: string } | null)?.from || '/'
      navigate(destination, { replace: true })
    } catch (requestError) {
      setError(apiMessage(requestError, 'Não foi possível entrar. Verifique as credenciais.'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="login-shell">
      <aside className="login-aside">
        <div className="login-brand">
          <span className="logo-mark">CO</span>
          <span>
            <span className="logo-name">Closer OS</span>
            <span className="logo-sub">CRM comercial</span>
          </span>
        </div>
        <div className="login-aside-body">
          <h1>Vendas consultivas,<br /><em>com clareza.</em></h1>
          <p>Pipeline, calls, propostas, comissões e inteligência comercial em uma operação única. Métricas calculadas no backend, permissões aplicadas em cada etapa.</p>
          <div className="login-features">
            <span className="login-feature"><Icon name="pipeline" size={12} /> Kanban com histórico</span>
            <span className="login-feature"><Icon name="calls" size={12} /> Análise local de calls</span>
            <span className="login-feature"><Icon name="money" size={12} /> Comissões auditáveis</span>
            <span className="login-feature"><Icon name="lock" size={12} /> JWT + refresh HttpOnly</span>
          </div>
        </div>
        <div className="login-foot">Closer OS · ambiente local · dados protegidos por perfil e equipe.</div>
      </aside>
      <main className="login-main">
        <div className="login-card">
          <h2>Entrar na operação</h2>
          <p>Use suas credenciais para acessar o CRM.</p>
          <form className="login-form" onSubmit={submit}>
            {error ? <div className="login-error">{error}</div> : null}
            <Field label="E-mail">
              <Input type="email" autoComplete="username" value={email} onChange={(event) => setEmail(event.target.value)} placeholder="voce@empresa.com" required autoFocus />
            </Field>
            <Field label="Senha">
              <div className="password-wrap">
                <Input type={showPassword ? 'text' : 'password'} autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Sua senha" required />
                <button type="button" className="password-toggle" onClick={() => setShowPassword((current) => !current)} aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}>
                  <Icon name="eye" size={16} />
                </button>
              </div>
            </Field>
            <Button type="submit" variant="primary" size="lg" className="btn-block" loading={submitting}>Entrar</Button>
          </form>
          <div className="login-demo">
            <b>Acesso de demonstração local</b><br />
            <code>admin@closer.local</code><br />
            Senha definida em <code>DEMO_ADMIN_PASSWORD</code> no backend (padrão <code>Closer@2026!</code>).
          </div>
        </div>
      </main>
    </div>
  )
}
