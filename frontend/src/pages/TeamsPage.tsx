import { useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useApi } from '@/lib/hooks'
import { num } from '@/lib/format'
import type { Team } from '@/lib/types'
import { Badge, Button, EmptyState, ErrorState, Field, Icon, IconButton, Input, LoadingState, Modal, Textarea, useToast } from '@/components/ui'

const blank = { name: '', description: '', active: '1' }

export function TeamsPage() {
  const { notify } = useToast()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Team | null>(null)
  const [form, setForm] = useState({ ...blank })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const { data, loading, error: loadError, reload } = useApi<{ data: Team[] }>('/teams')
  const teams = data?.data || []

  const openForm = (team?: Team | null) => {
    setEditing(team || null)
    setForm(team ? { name: team.name, description: team.description || '', active: team.active ? '1' : '0' } : { ...blank })
    setError('')
    setOpen(true)
  }

  const save = async () => {
    setSaving(true)
    setError('')
    try {
      const payload = { name: form.name, description: form.description || null, active: form.active === '1' }
      if (editing) await api.put(`/teams/${editing.id}`, payload)
      else await api.post('/teams', payload)
      notify(editing ? 'Equipe atualizada.' : 'Equipe criada.')
      setOpen(false)
      reload()
    } catch (requestError) {
      setError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left"><span className="muted small">Equipes organizam visibilidade de leads, reuniões, comissões e lembretes para gestores.</span></div>
        <Button variant="primary" icon="plus" onClick={() => openForm(null)}>Nova equipe</Button>
      </div>
      {loadError ? <ErrorState message={loadError} onRetry={reload} /> : loading && !data ? <LoadingState /> : !teams.length ? (
        <div className="panel panel-padded"><EmptyState icon="users" title="Nenhuma equipe" description="Crie a primeira equipe comercial." action={<Button onClick={() => openForm(null)}>Nova equipe</Button>} /></div>
      ) : (
        <div className="library-grid">
          {teams.map((team) => (
            <article className="library-card" key={team.id}>
              <div className="library-card-top">
                <span className="library-icon"><Icon name="users" size={16} /></span>
                <Badge tone={team.active ? 'brand' : 'neutral'}>{team.active ? 'Ativa' : 'Inativa'}</Badge>
              </div>
              <h3>{team.name}</h3>
              <p>{team.description || 'Sem descrição.'}</p>
              <div className="library-meta"><span>{num(team.users_count || 0)} membro(s)</span><span className="mono">{team.slug}</span></div>
              <div className="library-foot"><span>Visibilidade e escopo de gestores</span><IconButton icon="edit" label="Editar equipe" onClick={() => openForm(team)} /></div>
            </article>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => { if (!saving) setOpen(false) }} title={editing ? 'Editar equipe' : 'Nova equipe'} footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>{editing ? 'Salvar' : 'Criar equipe'}</Button></>}>
        {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
        <div className="form-grid">
          <Field label="Nome" required className="span-2"><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></Field>
          <Field label="Descrição" className="span-2"><Textarea rows={3} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /></Field>
          <Field label="Status"><select className="input select" value={form.active} onChange={(event) => setForm({ ...form, active: event.target.value })}><option value="1">Ativa</option><option value="0">Inativa</option></select></Field>
        </div>
      </Modal>
    </div>
  )
}
