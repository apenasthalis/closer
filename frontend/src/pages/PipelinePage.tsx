import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, apiMessage } from '@/lib/api'
import { useApi, useDebounced, useLookups } from '@/lib/hooks'
import { STAGES, STAGE_LABELS, TEMPERATURE_LABELS } from '@/lib/constants'
import { date, endOfMonth, money, relative, startOfMonth } from '@/lib/format'
import type { Lead } from '@/lib/types'
import { Button, ErrorState, Field, Icon, Input, LoadingState, Modal, SearchInput, Select, Textarea, useToast } from '@/components/ui'
import { LeadFormModal } from '@/components/LeadFormModal'

interface KanbanColumn {
  stage: string
  label: string
  leads: Lead[]
  count: number
  value: number
}

interface KanbanResponse {
  data: { columns: KanbanColumn[]; total: number }
}

interface PendingMove {
  lead: Lead
  from: string
  to: string
}

export function PipelinePage() {
  const navigate = useNavigate()
  const { notify } = useToast()
  const { niches, users } = useLookups()
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounced(search)
  const [from, setFrom] = useState(startOfMonth())
  const [to, setTo] = useState(endOfMonth())
  const [nicheId, setNicheId] = useState('')
  const [closerId, setCloserId] = useState('')
  const [dragLead, setDragLead] = useState<Lead | null>(null)
  const [dragOver, setDragOver] = useState('')
  const [newLeadStage, setNewLeadStage] = useState<string | null>(null)
  const [pendingMove, setPendingMove] = useState<PendingMove | null>(null)
  const [reason, setReason] = useState('')
  const [closedValue, setClosedValue] = useState('')
  const [moving, setMoving] = useState(false)

  const { data, loading, error, reload, setData } = useApi<KanbanResponse>('/leads/kanban', {
    q: debouncedSearch || undefined,
    niche_id: nicheId || undefined,
    closer_id: closerId || undefined,
    from: from || undefined,
    to: to || undefined,
  })

  const columns = useMemo(() => {
    const existing = data?.data?.columns || []
    return STAGES.map((stage) => {
      const column = existing.find((item) => item.stage === stage.value)
      return column || { stage: stage.value, label: stage.label, leads: [], count: 0, value: 0 }
    })
  }, [data])

  const total = columns.reduce((sum, column) => sum + column.count, 0)
  const totalValue = columns.reduce((sum, column) => sum + Number(column.value || 0), 0)

  useEffect(() => {
    setReason('')
    setClosedValue('')
  }, [pendingMove])

  const recalc = (nextColumns: KanbanColumn[]) => ({
    data: { columns: nextColumns, total: nextColumns.reduce((sum, column) => sum + column.leads.length, 0) },
  })

  const optimisticMove = (lead: Lead, from: string, to: string) => {
    if (!data) return null
    const previous = data
    const nextColumns = data.data.columns.map((column) => {
      if (column.stage === from) {
        const leads = column.leads.filter((item) => item.id !== lead.id)
        return { ...column, leads, count: leads.length }
      }
      if (column.stage === to) {
        const leads = [{ ...lead, stage: to }, ...column.leads]
        return { ...column, leads, count: leads.length }
      }
      return column
    })
    setData(recalc(nextColumns))
    return previous
  }

  const patchStage = async (payload: Record<string, unknown>, lead: Lead, from: string, to: string) => {
    const previous = optimisticMove(lead, from, to)
    try {
      await api.patch(`/leads/${lead.id}/stage`, payload)
      notify(`Lead movido para ${STAGE_LABELS[to]}.`)
      setPendingMove(null)
      reload()
    } catch (requestError) {
      if (previous) setData(previous)
      notify(apiMessage(requestError), 'error')
    } finally {
      setMoving(false)
    }
  }

  const drop = (to: string) => {
    setDragOver('')
    const lead = dragLead
    setDragLead(null)
    if (!lead || lead.stage === to) return

    if (to === 'loss' || to === 'mal_qualificado') {
      setReason('')
      setPendingMove({ lead, from: lead.stage, to })
      return
    }

    if (to === 'fechado') {
      setClosedValue(lead.ticket_fechado ? String(lead.ticket_fechado) : lead.valor_negociado ? String(lead.valor_negociado) : lead.ticket_estimado ? String(lead.ticket_estimado) : '')
      setPendingMove({ lead, from: lead.stage, to })
      return
    }

    setMoving(true)
    patchStage({ stage: to }, lead, lead.stage, to)
  }

  const confirmMove = async () => {
    if (!pendingMove) return
    const { lead, from, to } = pendingMove
    if ((to === 'loss' || to === 'mal_qualificado') && !reason.trim()) {
      notify('Informe o motivo obrigatório para esta etapa.', 'error')
      return
    }
    setMoving(true)
    const payload: Record<string, unknown> = { stage: to, reason: reason.trim() || undefined }
    if (to === 'fechado' && closedValue) payload.ticket_fechado = Number(closedValue)
    await patchStage(payload, lead, from, to)
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <SearchInput value={search} onChange={setSearch} placeholder="Buscar por nome ou empresa..." />
          <label className="pill" style={{ cursor: 'pointer', gap: 6, marginLeft: 10 }}>
            <Icon name="calendar" size={12} />
            <span>De</span>
            <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="bare-input" style={{ width: 95 }} />
          </label>
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <span>até</span>
            <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="bare-input" style={{ width: 95 }} />
          </label>
          <Select value={nicheId} onChange={(event) => setNicheId(event.target.value)} style={{ width: 165 }}>
            <option value="">Todos os nichos</option>
            {niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}
          </Select>
          <Select value={closerId} onChange={(event) => setCloserId(event.target.value)} style={{ width: 165 }}>
            <option value="">Todos os closers</option>
            {users.filter((user) => user.role !== 'sdr').map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
          </Select>
        </div>
        <div className="page-toolbar-right">
          <span className="pill sm">{total} lead(s) · {money(totalValue, true)}</span>
          <Button variant="primary" icon="plus" onClick={() => setNewLeadStage('lead_agendado')}>Novo lead</Button>
        </div>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState label="Montando o pipeline..." /> : (
        <div className="kanban-scroll">
          <div className="kanban">
            {columns.map((column) => {
              const stage = STAGES.find((item) => item.value === column.stage)!
              return (
                <div
                  key={column.stage}
                  className={`kcol ${dragOver === column.stage ? 'drag-over' : ''}`}
                  onDragOver={(event) => { event.preventDefault(); setDragOver(column.stage) }}
                  onDragLeave={() => setDragOver((current) => current === column.stage ? '' : current)}
                  onDrop={(event) => { event.preventDefault(); drop(column.stage) }}
                >
                  <div className="kcol-head">
                    <span className="kcol-title"><span className="kcol-dot" style={{ background: stage.color }} />{stage.label}</span>
                    <span className="kcol-count">{column.leads.length}</span>
                  </div>
                  <div className="kcol-value">{money(column.value, true)} em pipeline</div>
                  <div className="kcards">
                    {column.leads.map((lead) => (
                      <article
                        key={lead.id}
                        className={`kcard ${dragLead?.id === lead.id ? 'dragging' : ''}`}
                        draggable
                        onDragStart={() => setDragLead(lead)}
                        onDragEnd={() => { setDragLead(null); setDragOver('') }}
                        onClick={() => navigate(`/leads/${lead.id}`)}
                      >
                        <div className="name">
                          <span className="truncate">{lead.name}</span>
                          <span className={`temp ${lead.temperature}`} title={TEMPERATURE_LABELS[lead.temperature]} />
                        </div>
                        <div className="niche truncate">{lead.company || lead.niche?.name || 'Sem empresa'}{lead.niche?.name && lead.company ? ` · ${lead.niche.name}` : ''}</div>
                        <div className="foot">
                          <span className="value">{money(lead.valor_negociado || lead.ticket_estimado, true)}</span>
                          <span className="truncate">{lead.closer?.name?.split(' ')[0] || 'Sem closer'}</span>
                        </div>
                        {lead.next_action_at ? <div className="kcard-meta"><Icon name="clock" size={10} />{relative(lead.next_action_at)}</div> : null}
                        {lead.priority >= 7 ? <div className="kcard-meta"><Icon name="alert" size={10} />Prioridade {lead.priority}</div> : null}
                      </article>
                    ))}
                    {!column.leads.length ? <div className="kcol-empty">Solte um lead aqui</div> : null}
                  </div>
                  <button className="kcol-add" onClick={() => setNewLeadStage(column.stage)}>+ Adicionar lead</button>
                </div>
              )
            })}
          </div>
        </div>
      )}

      <LeadFormModal
        open={Boolean(newLeadStage)}
        defaultStage={newLeadStage || 'lead_agendado'}
        onClose={() => setNewLeadStage(null)}
        onSaved={() => { setNewLeadStage(null); reload() }}
      />

      <Modal
        open={Boolean(pendingMove)}
        onClose={() => { if (!moving) setPendingMove(null) }}
        title={pendingMove ? `Mover para ${STAGE_LABELS[pendingMove.to]}` : 'Mover lead'}
        subtitle={pendingMove ? `${pendingMove.lead.name} · ${STAGE_LABELS[pendingMove.from]} → ${STAGE_LABELS[pendingMove.to]}` : undefined}
        footer={<><Button onClick={() => setPendingMove(null)} disabled={moving}>Cancelar</Button><Button variant="primary" loading={moving} onClick={confirmMove}>Confirmar movimentação</Button></>}
      >
        {pendingMove ? (
          <div className="stack-sm">
            {pendingMove.to === 'fechado' ? (
              <>
                <Field label="Valor fechado" hint="Se vazio, o backend usa valor negociado ou ticket estimado."><Input type="number" min={0} step="0.01" value={closedValue} onChange={(event) => setClosedValue(event.target.value)} placeholder="0,00" /></Field>
                <div className="callout">Ao fechar, lembretes pendentes são cancelados e o financeiro da oportunidade é liberado. A comissão é calculada por regra com snapshot histórico.</div>
              </>
            ) : (
              <>
                <Field label="Motivo obrigatório" required><Textarea value={reason} onChange={(event) => setReason(event.target.value)} placeholder={pendingMove.to === 'loss' ? 'Por que a oportunidade foi perdida?' : 'Por que o lead foi desqualificado?'} /></Field>
                <div className="callout warn">O motivo é registrado no histórico, na auditoria e nos relatórios de perdas.</div>
              </>
            )}
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
