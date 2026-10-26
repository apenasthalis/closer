import { useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useApi } from '@/lib/hooks'
import { NICHE_COLORS } from '@/lib/constants'
import { num } from '@/lib/format'
import type { Niche } from '@/lib/types'
import { Badge, Button, EmptyState, ErrorState, Field, Icon, IconButton, Input, LoadingState, Modal, Textarea, useToast } from '@/components/ui'

const blank = { name: '', description: '', color: NICHE_COLORS[0], active: '1' }

export function NichesPage() {
  const { notify } = useToast()
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState<Niche | null>(null)
  const [form, setForm] = useState({ ...blank })
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const { data, loading, error: loadError, reload } = useApi<{ data: Niche[] }>('/niches')
  const niches = data?.data || []

  const openForm = (niche?: Niche | null) => {
    setEditing(niche || null)
    setForm(niche ? { name: niche.name, description: niche.description || '', color: niche.color || NICHE_COLORS[0], active: niche.active ? '1' : '0' } : { ...blank })
    setError('')
    setOpen(true)
  }

  const save = async () => {
    setSaving(true)
    setError('')
    try {
      const payload = { name: form.name, description: form.description || null, color: form.color, active: form.active === '1' }
      if (editing) await api.put(`/niches/${editing.id}`, payload)
      else await api.post('/niches', payload)
      notify(editing ? 'Nicho atualizado.' : 'Nicho criado.')
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
        <div className="page-toolbar-left"><span className="muted small">Nichos segmentam leads, scripts, playbooks, objeções e relatórios.</span></div>
        <Button variant="primary" icon="plus" onClick={() => openForm(null)}>Novo nicho</Button>
      </div>
      {loadError ? <ErrorState message={loadError} onRetry={reload} /> : loading && !data ? <LoadingState /> : !niches.length ? (
        <div className="panel panel-padded"><EmptyState icon="niches" title="Nenhum nicho" description="Cadastre o primeiro segmento comercial." action={<Button onClick={() => openForm(null)}>Novo nicho</Button>} /></div>
      ) : (
        <div className="library-grid">
          {niches.map((niche) => (
            <article className="library-card" key={niche.id}>
              <div className="library-card-top">
                <span className="library-icon" style={{ background: `${niche.color || '#3fa373'}1f`, color: niche.color || 'var(--brand)' }}><Icon name="niches" size={16} /></span>
                <Badge tone={niche.active ? 'brand' : 'neutral'}>{niche.active ? 'Ativo' : 'Inativo'}</Badge>
              </div>
              <h3><span className="niche-dot" style={{ background: niche.color || 'var(--brand)' }} />{niche.name}</h3>
              <p>{niche.description || 'Sem descrição.'}</p>
              <div className="library-meta"><span>{num(niche.leads_count || 0)} lead(s)</span><span className="mono">{niche.slug}</span></div>
              <div className="library-foot"><span>Cor {niche.color || 'padrão'}</span><IconButton icon="edit" label="Editar nicho" onClick={() => openForm(niche)} /></div>
            </article>
          ))}
        </div>
      )}

      <Modal open={open} onClose={() => { if (!saving) setOpen(false) }} title={editing ? 'Editar nicho' : 'Novo nicho'} footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>{editing ? 'Salvar' : 'Criar nicho'}</Button></>}>
        {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
        <div className="form-grid">
          <Field label="Nome" required className="span-2"><Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></Field>
          <Field label="Descrição" className="span-2"><Textarea rows={3} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /></Field>
          <Field label="Cor">
            <div className="color-picker-row">
              {NICHE_COLORS.map((color) => <button key={color} type="button" className={`color-swatch ${form.color === color ? 'selected' : ''}`} style={{ background: color }} onClick={() => setForm({ ...form, color })} aria-label={`Cor ${color}`} />)}
            </div>
          </Field>
          <Field label="Status"><select className="input select" value={form.active} onChange={(event) => setForm({ ...form, active: event.target.value })}><option value="1">Ativo</option><option value="0">Inativo</option></select></Field>
        </div>
      </Modal>
    </div>
  )
}
