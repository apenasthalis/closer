import { useMemo, useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useApi, useLookups } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { dateTime, initials } from '@/lib/format'
import { NICHE_COLORS } from '@/lib/constants'
import type { User } from '@/lib/types'
import {
  Badge, Button, ConfirmDialog, EmptyState, ErrorState, Field, IconButton, Input,
  LoadingState, Modal, SearchInput, Select, useToast,
} from '@/components/ui'

const roleLabels: Record<string, string> = { admin: 'Administrador', manager: 'Gestor comercial', closer: 'Closer', sdr: 'SDR / Pré-vendas' }
const roleTone: Record<string, string> = { admin: 'rose', manager: 'purple', closer: 'blue', sdr: 'green' }

const blankForm = {
  name: '', email: '', password: '', password_confirmation: '', role: 'closer',
  active: '1', phone: '', team_id: '', avatar_color: NICHE_COLORS[0],
}

export function UsersPage() {
  const { user: currentUser } = useAuth()
  const { notify } = useToast()
  const { teams } = useLookups()
  const [search, setSearch] = useState('')
  const [role, setRole] = useState('')
  const [active, setActive] = useState('')
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<User | null>(null)
  const [form, setForm] = useState({ ...blankForm })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [deactivating, setDeactivating] = useState<User | null>(null)

  const { data, loading, error: loadError, reload } = useApi<{ data: User[] }>('/users')
  const users = data?.data || []
  const isAdmin = currentUser?.role === 'admin'

  const filtered = useMemo(() => users.filter((item) => {
    const term = search.trim().toLowerCase()
    if (term && !`${item.name} ${item.email}`.toLowerCase().includes(term)) return false
    if (role && item.role !== role) return false
    if (active === '1' && !item.active) return false
    if (active === '0' && item.active) return false
    return true
  }), [users, search, role, active])

  const openForm = (user?: User | null) => {
    setEditing(user || null)
    setForm(user ? {
      name: user.name, email: user.email, password: '', password_confirmation: '',
      role: user.role, active: user.active ? '1' : '0', phone: user.phone || '',
      team_id: user.team_id ? String(user.team_id) : '', avatar_color: user.avatar_color || NICHE_COLORS[0],
    } : { ...blankForm })
    setError('')
    setOpen(true)
  }

  const save = async () => {
    setSaving(true)
    setError('')
    try {
      const payload = {
        name: form.name,
        email: form.email,
        role: form.role,
        active: form.active === '1',
        phone: form.phone || null,
        team_id: form.team_id ? Number(form.team_id) : null,
        avatar_color: form.avatar_color,
        ...(form.password ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
      }
      if (editing) await api.put(`/users/${editing.id}`, payload)
      else await api.post('/users', payload)
      notify(editing ? 'Usuário atualizado.' : 'Usuário criado.')
      setOpen(false)
      reload()
    } catch (requestError) {
      setError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  const deactivate = async () => {
    if (!deactivating) return
    try {
      await api.delete(`/users/${deactivating.id}`)
      notify('Usuário desativado. Sessões ativas foram revogadas.')
      setDeactivating(null)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <SearchInput value={search} onChange={setSearch} placeholder="Nome ou e-mail..." />
          <Select value={role} onChange={(event) => setRole(event.target.value)} style={{ width: 170 }}>
            <option value="">Todos os perfis</option>
            {Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </Select>
          <Select value={active} onChange={(event) => setActive(event.target.value)} style={{ width: 130 }}>
            <option value="">Status</option>
            <option value="1">Ativos</option>
            <option value="0">Inativos</option>
          </Select>
        </div>
        {isAdmin ? <Button variant="primary" icon="plus" onClick={() => openForm(null)}>Novo usuário</Button> : null}
      </div>

      {loadError ? <ErrorState message={loadError} onRetry={reload} /> : loading && !data ? <LoadingState /> : !filtered.length ? (
        <div className="panel panel-padded"><EmptyState icon="users" title="Nenhum usuário" description="Ajuste os filtros ou convide um novo membro para a operação." action={isAdmin ? <Button onClick={() => openForm(null)}>Novo usuário</Button> : undefined} /></div>
      ) : (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>Usuário</th><th>Perfil</th><th>Equipe</th><th>Status</th><th>Último acesso</th><th /></tr></thead>
            <tbody>
              {filtered.map((item) => (
                <tr key={item.id}>
                  <td>
                    <div className="user-cell">
                      <span className="avatar-ui" style={{ width: 32, height: 32, background: item.avatar_color || 'var(--brand)' }}>{initials(item.name)}</span>
                      <div><b>{item.name}{item.id === currentUser?.id ? ' (você)' : ''}</b><span>{item.email}{item.phone ? ` · ${item.phone}` : ''}</span></div>
                    </div>
                  </td>
                  <td><Badge tone={roleTone[item.role]}>{roleLabels[item.role] || item.role_label || item.role}</Badge></td>
                  <td>{item.team?.name || <span className="muted">Sem equipe</span>}</td>
                  <td><Badge tone={item.active ? 'brand' : 'neutral'}>{item.active ? 'Ativo' : 'Inativo'}</Badge></td>
                  <td>{item.last_login_at ? dateTime(item.last_login_at) : <span className="muted">Nunca acessou</span>}</td>
                  <td>{isAdmin ? <div className="actions"><IconButton icon="edit" label="Editar usuário" onClick={() => openForm(item)} />{item.id !== currentUser?.id && item.active ? <IconButton icon="trash" label="Desativar usuário" onClick={() => setDeactivating(item)} /> : null}</div> : null}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={open} onClose={() => { if (!saving) setOpen(false) }} title={editing ? 'Editar usuário' : 'Novo usuário'} subtitle={editing ? 'Alterações de perfil e senha são auditadas.' : 'O administrador cria novos usuários da operação.'} size="lg" footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>{editing ? 'Salvar alterações' : 'Criar usuário'}</Button></>}>
        {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
        <div className="form-grid">
          <Field label="Nome" required><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></Field>
          <Field label="E-mail" required><Input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></Field>
          <Field label="Perfil" required><Select value={form.role} onChange={(event) => setForm({ ...form, role: event.target.value })}>{Object.entries(roleLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
          <Field label="Equipe"><Select value={form.team_id} onChange={(event) => setForm({ ...form, team_id: event.target.value })}><option value="">Sem equipe</option>{teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</Select></Field>
          <Field label="Telefone"><Input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></Field>
          <Field label="Status"><Select value={form.active} onChange={(event) => setForm({ ...form, active: event.target.value })}><option value="1">Ativo</option><option value="0">Inativo</option></Select></Field>
          <Field label={editing ? 'Nova senha' : 'Senha'} required={!editing} hint={editing ? 'Deixe vazio para manter a senha atual.' : 'Mínimo de 6 caracteres.'}><Input type="password" autoComplete="new-password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></Field>
          <Field label="Confirmar senha" required={!editing}><Input type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} /></Field>
          <Field label="Cor do avatar" className="span-2">
            <div className="color-picker-row">
              {NICHE_COLORS.map((color) => <button key={color} type="button" className={`color-swatch ${form.avatar_color === color ? 'selected' : ''}`} style={{ background: color }} onClick={() => setForm({ ...form, avatar_color: color })} aria-label={`Cor ${color}`} />)}
            </div>
          </Field>
        </div>
      </Modal>

      <ConfirmDialog open={Boolean(deactivating)} title="Desativar usuário" message={`Desativar ${deactivating?.name}? O acesso será bloqueado e todas as sessões ativas serão revogadas.`} confirmLabel="Desativar" onClose={() => setDeactivating(null)} onConfirm={deactivate} />
    </div>
  )
}
