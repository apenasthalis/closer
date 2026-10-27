import { useMemo, useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useApi } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import type { Company } from '@/lib/types'
import {
  Badge, Button, ConfirmDialog, EmptyState, ErrorState, Field, IconButton, Input,
  LoadingState, Modal, SearchInput, Select, useToast,
} from '@/components/ui'

const blankForm = {
  name: '', cnpj: '', email: '', phone: '', address: '', active: '1',
}

export function CompaniesPage() {
  const { user: currentUser } = useAuth()
  const { notify } = useToast()
  const [search, setSearch] = useState('')
  const [active, setActive] = useState('')
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Company | null>(null)
  const [form, setForm] = useState({ ...blankForm })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [deleting, setDeleting] = useState<Company | null>(null)

  const { data, loading, error: loadError, reload } = useApi<{ data: Company[] }>('/companies')
  const companies = data?.data || []
  const isAdmin = currentUser?.role === 'admin'

  const filtered = useMemo(() => companies.filter((item) => {
    const term = search.trim().toLowerCase()
    if (term && !`${item.name} ${item.cnpj ?? ''}`.toLowerCase().includes(term)) return false
    if (active === '1' && !item.active) return false
    if (active === '0' && item.active) return false
    return true
  }), [companies, search, active])

  const openForm = (company?: Company | null) => {
    setEditing(company || null)
    setForm(company ? {
      name: company.name, cnpj: company.cnpj || '', email: company.email || '',
      phone: company.phone || '', address: company.address || '',
      active: company.active ? '1' : '0',
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
        cnpj: form.cnpj || null,
        email: form.email || null,
        phone: form.phone || null,
        address: form.address || null,
        active: form.active === '1',
      }
      if (editing) await api.put(`/companies/${editing.id}`, payload)
      else await api.post('/companies', payload)
      notify(editing ? 'Empresa atualizada.' : 'Empresa criada.')
      setOpen(false)
      reload()
    } catch (requestError) {
      setError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  const remove = async () => {
    if (!deleting) return
    try {
      await api.delete(`/companies/${deleting.id}`)
      notify('Empresa excluída.')
      setDeleting(null)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <SearchInput value={search} onChange={setSearch} placeholder="Nome ou CNPJ..." />
          <Select value={active} onChange={(event) => setActive(event.target.value)} style={{ width: 130 }}>
            <option value="">Status</option>
            <option value="1">Ativas</option>
            <option value="0">Inativas</option>
          </Select>
        </div>
        {isAdmin ? <Button variant="primary" icon="plus" onClick={() => openForm(null)}>Nova empresa</Button> : null}
      </div>

      {loadError ? <ErrorState message={loadError} onRetry={reload} /> : loading && !data ? <LoadingState /> : !filtered.length ? (
        <div className="panel panel-padded"><EmptyState icon="users" title="Nenhuma empresa" description="Cadastre as empresas que sua operação atende." action={isAdmin ? <Button onClick={() => openForm(null)}>Nova empresa</Button> : undefined} /></div>
      ) : (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>Empresa</th><th>CNPJ</th><th>E-mail</th><th>Telefone</th><th>Status</th><th>Usuários</th><th /></tr></thead>
            <tbody>
              {filtered.map((item) => (
                <tr key={item.id}>
                  <td>
                    <div className="user-cell">
                      <div><b>{item.name}</b></div>
                      <div style={{ fontSize: 10.5, color: 'var(--muted)' }}>{item.address || 'Sem endereço'}</div>
                    </div>
                  </td>
                  <td className="mono">{item.cnpj || <span className="muted">—</span>}</td>
                  <td>{item.email || <span className="muted">—</span>}</td>
                  <td>{item.phone || <span className="muted">—</span>}</td>
                  <td><Badge tone={item.active ? 'brand' : 'neutral'}>{item.active ? 'Ativa' : 'Inativa'}</Badge></td>
                  <td>{item.users_count ?? 0}</td>
                  <td>{isAdmin ? <div className="actions"><IconButton icon="edit" label="Editar empresa" onClick={() => openForm(item)} />{item.active ? <IconButton icon="trash" label="Excluir empresa" onClick={() => setDeleting(item)} /> : null}</div> : null}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={open} onClose={() => { if (!saving) setOpen(false) }} title={editing ? 'Editar empresa' : 'Nova empresa'} subtitle="Cadastre as empresas que os usuários da operação podem representar." size="lg" footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>{editing ? 'Salvar alterações' : 'Criar empresa'}</Button></>}>
        {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
        <div className="form-grid">
          <Field label="Nome" required><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></Field>
          <Field label="CNPJ"><Input value={form.cnpj} onChange={(event) => setForm({ ...form, cnpj: event.target.value })} placeholder="00.000.000/0000-00" /></Field>
          <Field label="E-mail"><Input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></Field>
          <Field label="Telefone"><Input value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></Field>
          <Field label="Endereço" className="span-2"><Input value={form.address} onChange={(event) => setForm({ ...form, address: event.target.value })} /></Field>
          <Field label="Status"><Select value={form.active} onChange={(event) => setForm({ ...form, active: event.target.value })}><option value="1">Ativa</option><option value="0">Inativa</option></Select></Field>
        </div>
      </Modal>

      <ConfirmDialog open={Boolean(deleting)} title="Excluir empresa" message={`Excluir ${deleting?.name}? Usuários vinculados a ela terão o vínculo removido.`} confirmLabel="Excluir" onClose={() => setDeleting(null)} onConfirm={remove} />
    </div>
  )
}
