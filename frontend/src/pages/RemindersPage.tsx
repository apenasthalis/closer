import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, apiMessage } from '@/lib/api'
import { useApi, useLeadOptions, useLookups } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { REMINDER_LABELS, REMINDER_TYPES } from '@/lib/constants'
import { dateTime, relative } from '@/lib/format'
import type { Paginated, Reminder } from '@/lib/types'
import {
  Badge, Button, EmptyState, ErrorState, Field, Icon, IconButton, Input, LoadingState,
  Modal, Pagination, Select, Tabs, Textarea, useToast,
} from '@/components/ui'

function defaultDue() {
  const value = new Date()
  value.setDate(value.getDate() + 1)
  value.setHours(9, 0, 0, 0)
  return new Date(value.getTime() - value.getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

export function RemindersPage() {
  const { user } = useAuth()
  const { notify } = useToast()
  const { users } = useLookups()
  const leads = useLeadOptions()
  const [status, setStatus] = useState('pending')
  const [due, setDue] = useState('')
  const [type, setType] = useState('')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [title, setTitle] = useState('')
  const [detail, setDetail] = useState('')
  const [reminderType, setReminderType] = useState('follow_up')
  const [dueAt, setDueAt] = useState(defaultDue())
  const [priority, setPriority] = useState('5')
  const [leadId, setLeadId] = useState('')
  const [responsibleId, setResponsibleId] = useState('')
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')
  const [snoozeOpen, setSnoozeOpen] = useState(false)
  const [snoozeTarget, setSnoozeTarget] = useState<Reminder | null>(null)
  const [snoozeAt, setSnoozeAt] = useState(defaultDue())
  const [snoozing, setSnoozing] = useState(false)
  const [reassignTarget, setReassignTarget] = useState<Reminder | null>(null)
  const [reassignTo, setReassignTo] = useState('')
  const [automating, setAutomating] = useState(false)

  const { data, loading, error, reload } = useApi<Paginated<Reminder>>('/reminders', {
    status,
    due: due || undefined,
    type: type || undefined,
    page,
    per_page: 100,
  })

  const reminders = data?.data || []
  const canManage = user?.role === 'admin' || user?.role === 'manager'
  const overdueCount = useMemo(() => reminders.filter((reminder) => reminder.status === 'pendente' && new Date(reminder.due_at).getTime() < Date.now()).length, [reminders])

  const create = async () => {
    if (title.trim().length < 2) return
    setSaving(true)
    setFormError('')
    try {
      await api.post('/reminders', {
        lead_id: leadId || null,
        type: reminderType,
        title: title.trim(),
        detail: detail || null,
        due_at: dueAt,
        priority: Number(priority || 0),
        responsible_id: responsibleId ? Number(responsibleId) : null,
      })
      notify('Lembrete criado.')
      setOpen(false)
      setTitle('')
      setDetail('')
      setLeadId('')
      reload()
    } catch (requestError) {
      setFormError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  const complete = async (reminder: Reminder) => {
    try {
      await api.post(`/reminders/${reminder.id}/complete`)
      notify('Lembrete concluído.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const cancel = async (reminder: Reminder) => {
    try {
      await api.post(`/reminders/${reminder.id}/cancel`)
      notify('Lembrete cancelado.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const openSnooze = (reminder: Reminder) => {
    const next = new Date()
    next.setDate(next.getDate() + 1)
    next.setHours(9, 0, 0, 0)
    setSnoozeAt(new Date(next.getTime() - next.getTimezoneOffset() * 60000).toISOString().slice(0, 16))
    setSnoozeTarget(reminder)
    setSnoozeOpen(true)
  }

  const snooze = async () => {
    if (!snoozeTarget) return
    setSnoozing(true)
    try {
      await api.post(`/reminders/${snoozeTarget.id}/snooze`, { due_at: snoozeAt, reason: 'Adiado pelo painel de lembretes.' })
      notify('Lembrete adiado.')
      setSnoozeOpen(false)
      setSnoozeTarget(null)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setSnoozing(false)
    }
  }

  const reassign = async () => {
    if (!reassignTarget || !reassignTo) return
    try {
      await api.post(`/reminders/${reassignTarget.id}/reassign`, { responsible_id: Number(reassignTo) })
      notify('Responsável alterado.')
      setReassignTarget(null)
      setReassignTo('')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const runAutomation = async () => {
    setAutomating(true)
    try {
      const response = await api.post('/reminders/automation/run')
      const result = response.data?.data
      const created = result?.created ?? result?.created_count
      notify(created !== undefined ? `Automação executada: ${created} lembrete(s) criado(s).` : 'Automação de lembretes executada.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setAutomating(false)
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <Select value={type} onChange={(event) => { setType(event.target.value); setPage(1) }} style={{ width: 175 }}>
            <option value="">Todos os tipos</option>
            {REMINDER_TYPES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
          </Select>
          <Select value={due} onChange={(event) => { setDue(event.target.value); setPage(1) }} style={{ width: 155 }}>
            <option value="">Qualquer prazo</option>
            <option value="overdue">Atrasados</option>
            <option value="today">Hoje</option>
            <option value="week">Próximos 7 dias</option>
          </Select>
          {overdueCount ? <Badge tone="rose">{overdueCount} atrasado(s) nesta visão</Badge> : null}
        </div>
        <div className="page-toolbar-right">
          {canManage ? <Button icon="refresh" loading={automating} onClick={runAutomation}>Executar automação</Button> : null}
          <Button variant="primary" icon="plus" onClick={() => { setFormError(''); setOpen(true) }}>Novo lembrete</Button>
        </div>
      </div>

      <Tabs
        value={status}
        onChange={(value) => { setStatus(value); setPage(1) }}
        tabs={[
          { value: 'pending', label: 'Pendentes' },
          { value: 'done', label: 'Concluídos' },
          { value: 'cancelled', label: 'Cancelados' },
          { value: 'all', label: 'Todos' },
        ]}
      />

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !reminders.length ? (
        <div className="panel panel-padded">
          <EmptyState icon="reminders" title="Nenhum lembrete" description="Crie follow-ups, confirmações e cobranças para não perder o timing comercial." action={<Button onClick={() => setOpen(true)}>Novo lembrete</Button>} />
        </div>
      ) : (
        <>
          <div className="reminder-list">
            {reminders.map((reminder) => {
              const late = reminder.status === 'pendente' && new Date(reminder.due_at).getTime() < Date.now()
              const isDone = reminder.status === 'concluido'
              return (
                <article className={`reminder-row ${late ? 'late' : ''} ${isDone ? 'done' : ''}`} key={reminder.id}>
                  <button className={`reminder-check ${isDone ? 'checked' : ''}`} aria-label="Concluir lembrete" disabled={reminder.status !== 'pendente'} onClick={() => complete(reminder)}>
                    {isDone ? <Icon name="check" size={13} /> : null}
                  </button>
                  <div className="reminder-main">
                    <div className="reminder-title">
                      <span>{reminder.title}</span>
                      <Badge tone={reminder.type === 'cobranca' || reminder.type === 'parcela' ? 'amber' : reminder.type === 'confirmacao_reuniao' ? 'blue' : 'neutral'}>{REMINDER_LABELS[reminder.type] || reminder.type}</Badge>
                      {reminder.origin === 'call_analysis' ? <Badge tone="purple">IA</Badge> : null}
                    </div>
                    <div className="reminder-meta">
                      <span className={late ? 'text-danger' : ''}><Icon name="clock" size={11} />{dateTime(reminder.due_at)} · {relative(reminder.due_at)}</span>
                      {reminder.lead ? <Link to={`/leads/${reminder.lead.id}`}><Icon name="leads" size={11} />{reminder.lead.name}</Link> : <span>Sem lead</span>}
                      <span><Icon name="user" size={11} />{reminder.responsible?.name || 'Sem responsável'}</span>
                      {reminder.detail ? <span className="reminder-detail">{reminder.detail}</span> : null}
                    </div>
                  </div>
                  <div className="reminder-actions">
                    {reminder.status === 'pendente' ? (
                      <>
                        <Button size="sm" icon="clock" onClick={() => openSnooze(reminder)}>Adiar</Button>
                        {canManage ? <IconButton icon="users" label="Reatribuir" onClick={() => { setReassignTarget(reminder); setReassignTo(String(reminder.responsible_id || '')) }} /> : null}
                        <IconButton icon="close" label="Cancelar lembrete" onClick={() => cancel(reminder)} />
                      </>
                    ) : <Badge tone={isDone ? 'brand' : 'neutral'}>{isDone ? 'Concluído' : 'Cancelado'}</Badge>}
                  </div>
                </article>
              )
            })}
          </div>
          <Pagination page={data?.current_page || 1} lastPage={data?.last_page || 1} total={data?.total || reminders.length} onChange={setPage} />
        </>
      )}

      <Modal
        open={open}
        onClose={() => { if (!saving) setOpen(false) }}
        title="Novo lembrete"
        subtitle="Lembrete interno com vínculo opcional ao lead."
        footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={create}>Criar lembrete</Button></>}
      >
        {formError ? <div className="login-error" style={{ marginBottom: 13 }}>{formError}</div> : null}
        <div className="form-grid">
          <Field label="Título" required className="span-2"><Input value={title} onChange={(event) => setTitle(event.target.value)} placeholder="Ex.: Confirmar reunião com o decisor" /></Field>
          <Field label="Tipo"><Select value={reminderType} onChange={(event) => setReminderType(event.target.value)}>{REMINDER_TYPES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
          <Field label="Prioridade"><Input type="number" min={0} max={10} value={priority} onChange={(event) => setPriority(event.target.value)} /></Field>
          <Field label="Vencimento" required><Input type="datetime-local" value={dueAt} onChange={(event) => setDueAt(event.target.value)} /></Field>
          <Field label="Lead">
            <Select value={leadId} onChange={(event) => setLeadId(event.target.value)}>
              <option value="">Sem lead</option>
              {leads.map((lead) => <option key={lead.id} value={lead.id}>{lead.name}{lead.company ? ` · ${lead.company}` : ''}</option>)}
            </Select>
          </Field>
          {canManage ? <Field label="Responsável"><Select value={responsibleId} onChange={(event) => setResponsibleId(event.target.value)}><option value="">Meu usuário</option>{users.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field> : null}
          <Field label="Detalhes" className="span-2"><Textarea value={detail} onChange={(event) => setDetail(event.target.value)} placeholder="Contexto e o que precisa ser feito." /></Field>
        </div>
      </Modal>

      <Modal
        open={snoozeOpen}
        onClose={() => { if (!snoozing) setSnoozeOpen(false) }}
        title="Adiar lembrete"
        subtitle={snoozeTarget?.title}
        footer={<><Button onClick={() => setSnoozeOpen(false)}>Cancelar</Button><Button variant="primary" loading={snoozing} onClick={snooze}>Adiar</Button></>}
      >
        <div className="stack-sm">
          <div className="quick-options">
            {[1, 3, 7].map((days) => (
              <Button key={days} size="sm" onClick={() => {
                const next = new Date()
                next.setDate(next.getDate() + days)
                next.setHours(9, 0, 0, 0)
                setSnoozeAt(new Date(next.getTime() - next.getTimezoneOffset() * 60000).toISOString().slice(0, 16))
              }}>+{days} dia{days > 1 ? 's' : ''}</Button>
            ))}
          </div>
          <Field label="Novo vencimento" required><Input type="datetime-local" value={snoozeAt} onChange={(event) => setSnoozeAt(event.target.value)} /></Field>
        </div>
      </Modal>

      <Modal
        open={Boolean(reassignTarget)}
        onClose={() => setReassignTarget(null)}
        title="Reatribuir lembrete"
        subtitle={reassignTarget?.title}
        footer={<><Button onClick={() => setReassignTarget(null)}>Cancelar</Button><Button variant="primary" onClick={reassign}>Reatribuir</Button></>}
      >
        <Field label="Novo responsável" required>
          <Select value={reassignTo} onChange={(event) => setReassignTo(event.target.value)}>
            <option value="">Selecione</option>
            {users.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </Select>
        </Field>
      </Modal>
    </div>
  )
}
