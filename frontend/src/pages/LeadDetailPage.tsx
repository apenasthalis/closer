import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { api, apiMessage, download } from '@/lib/api'
import { useApi } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { CALL_STATUS_LABELS, CONTRACT_STATUS, INTERACTION_KINDS, PAYMENT_METHODS, PAYMENT_STATUS, PROPOSAL_STATUS, STAGES, STAGE_LABELS, TEMPERATURE_LABELS } from '@/lib/constants'
import { date, dateTime, money, relative, whatsappLink } from '@/lib/format'
import type { Call, Contract, Lead, Payment, Proposal } from '@/lib/types'
import {
  Avatar, Badge, Button, ConfirmDialog, EmptyState, ErrorState, Field, Icon, IconButton,
  Input, LoadingState, Modal, Select, Tabs, Textarea, useToast,
} from '@/components/ui'
import { LeadFormModal } from '@/components/LeadFormModal'
import { ProposalFormModal } from '@/components/ProposalFormModal'
import { ContractFormModal } from '@/components/ContractFormModal'

interface LeadResponse {
  data: Lead
  meta: { stages: Array<{ value: string; label: string }>; can_edit: boolean }
}

const stageTone: Record<string, string> = {
  lead_agendado: 'blue', fup: 'green', remarcado: 'purple', negociacao: 'amber',
  fechado: 'brand', loss: 'rose', mal_qualificado: 'neutral',
}

const interactionTone: Record<string, string> = {
  nota: 'neutral', ligacao: 'blue', whatsapp: 'green', email: 'purple', reuniao: 'amber',
  proposta: 'brand', contrato: 'brand', pagamento: 'green', etapa_alterada: 'purple', lead_criado: 'blue', outro: 'neutral',
}

export function LeadDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { user } = useAuth()
  const { notify } = useToast()
  const { data, loading, error, reload } = useApi<LeadResponse>(id ? `/leads/${id}` : null)
  const [tab, setTab] = useState('overview')
  const [editOpen, setEditOpen] = useState(false)
  const [moveOpen, setMoveOpen] = useState(false)
  const [moveStage, setMoveStage] = useState('')
  const [moveReason, setMoveReason] = useState('')
  const [moveTicket, setMoveTicket] = useState('')
  const [moving, setMoving] = useState(false)

  const [interactionKind, setInteractionKind] = useState('nota')
  const [interactionText, setInteractionText] = useState('')
  const [interactionAt, setInteractionAt] = useState('')
  const [addingInteraction, setAddingInteraction] = useState(false)

  const [proposalOpen, setProposalOpen] = useState(false)
  const [editingProposal, setEditingProposal] = useState<Proposal | null>(null)
  const [contractOpen, setContractOpen] = useState(false)
  const [contractProposal, setContractProposal] = useState<Proposal | null>(null)
  const [installmentOpen, setInstallmentOpen] = useState(false)
  const [installmentTotal, setInstallmentTotal] = useState('')
  const [installmentCount, setInstallmentCount] = useState('1')
  const [installmentFirst, setInstallmentFirst] = useState('')
  const [installmentInterval, setInstallmentInterval] = useState('30')
  const [creatingInstallments, setCreatingInstallments] = useState(false)
  const [confirmDeleteAttachment, setConfirmDeleteAttachment] = useState<string | null>(null)
  const [deletingAttachment, setDeletingAttachment] = useState(false)
  const fileRef = useRef<HTMLInputElement>(null)

  const lead = data?.data
  const canEdit = data?.meta?.can_edit ?? false

  const proposalTotal = useMemo(() => (lead?.proposals || []).reduce((sum, proposal) => sum + Number(proposal.total || 0), 0), [lead])
  const paidTotal = useMemo(() => (lead?.payments || []).filter((payment) => payment.status === 'paid').reduce((sum, payment) => sum + Number(payment.amount || 0), 0), [lead])
  const pendingTotal = useMemo(() => (lead?.payments || []).filter((payment) => payment.status !== 'paid' && payment.status !== 'cancelled').reduce((sum, payment) => sum + Number(payment.amount || 0), 0), [lead])
  const pendingReminders = useMemo(() => (lead?.reminders || []).filter((reminder) => reminder.status === 'pendente'), [lead])
  const allowedStages = user?.role === 'sdr'
    ? STAGES.filter((stage) => ['lead_agendado', 'fup', 'remarcado', 'mal_qualificado'].includes(stage.value))
    : STAGES

  useEffect(() => {
    if (!lead) return
    setInteractionAt(new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16))
  }, [lead?.id])

  const openMove = () => {
    if (!lead) return
    setMoveStage(lead.stage)
    setMoveReason('')
    setMoveTicket(lead.ticket_fechado ? String(lead.ticket_fechado) : lead.valor_negociado ? String(lead.valor_negociado) : '')
    setMoveOpen(true)
  }

  const submitMove = async () => {
    if (!lead) return
    if (moveStage === lead.stage) {
      notify('Selecione uma etapa diferente da atual.', 'error')
      return
    }
    if ((moveStage === 'loss' || moveStage === 'mal_qualificado') && !moveReason.trim()) {
      notify('Informe o motivo obrigatório.', 'error')
      return
    }
    setMoving(true)
    try {
      await api.patch(`/leads/${lead.id}/stage`, {
        stage: moveStage,
        reason: moveReason.trim() || null,
        ticket_fechado: moveStage === 'fechado' && moveTicket ? Number(moveTicket) : undefined,
      })
      notify(`Lead movido para ${STAGE_LABELS[moveStage]}.`)
      setMoveOpen(false)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setMoving(false)
    }
  }

  const addInteraction = async () => {
    if (!lead || interactionText.trim().length < 2) return
    setAddingInteraction(true)
    try {
      await api.post(`/leads/${lead.id}/interactions`, {
        kind: interactionKind,
        summary: interactionText.trim(),
        occurred_at: interactionAt || undefined,
      })
      notify('Interação registrada.')
      setInteractionText('')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setAddingInteraction(false)
    }
  }

  const uploadAttachment = async (file: File) => {
    if (!lead) return
    const form = new FormData()
    form.append('file', file)
    form.append('kind', 'document')
    try {
      await api.post(`/leads/${lead.id}/attachments`, form)
      notify('Anexo enviado.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      if (fileRef.current) fileRef.current.value = ''
    }
  }

  const removeAttachment = async () => {
    if (!lead || !confirmDeleteAttachment) return
    setDeletingAttachment(true)
    try {
      await api.delete(`/leads/${lead.id}/attachments/${confirmDeleteAttachment}`)
      notify('Anexo removido.')
      setConfirmDeleteAttachment(null)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setDeletingAttachment(false)
    }
  }

  const openInstallments = () => {
    if (!lead) return
    setInstallmentTotal(String(lead.ticket_fechado || lead.valor_negociado || lead.ticket_estimado || ''))
    setInstallmentCount('1')
    setInstallmentInterval('30')
    const due = new Date()
    due.setDate(due.getDate() + 30)
    setInstallmentFirst(due.toISOString().slice(0, 10))
    setInstallmentOpen(true)
  }

  const createInstallments = async () => {
    if (!lead) return
    if (!installmentTotal || Number(installmentTotal) <= 0) {
      notify('Informe o valor total das parcelas.', 'error')
      return
    }
    setCreatingInstallments(true)
    try {
      await api.post(`/leads/${lead.id}/payments/installments`, {
        total: Number(installmentTotal),
        installments: Number(installmentCount),
        first_due_at: installmentFirst,
        interval_days: Number(installmentInterval),
      })
      notify('Parcelas criadas.')
      setInstallmentOpen(false)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setCreatingInstallments(false)
    }
  }

  const markPaid = async (payment: Payment) => {
    try {
      await api.patch(`/payments/${payment.id}/status`, { status: 'paid', method: payment.method || 'pix' })
      notify('Pagamento recebido.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const updateContractStatus = async (contract: Contract, status: string) => {
    try {
      await api.patch(`/contracts/${contract.id}/status`, { status })
      notify('Status do contrato atualizado.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const generateContract = async (proposal: Proposal) => {
    try {
      await api.post(`/proposals/${proposal.id}/contract`)
      notify('Contrato gerado a partir da proposta.')
      reload()
      setTab('finance')
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const deleteLead = async () => {
    if (!lead) return
    if (!window.confirm('Arquivar este lead? Ele deixará de aparecer nas listagens.')) return
    try {
      await api.delete(`/leads/${lead.id}`)
      notify('Lead arquivado.')
      navigate('/leads')
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  if (loading && !lead) return <LoadingState label="Carregando ficha do lead..." />
  if (error) return <ErrorState message={error} onRetry={reload} />
  if (!lead) return <EmptyState icon="leads" title="Lead não encontrado" description="O registro pode ter sido arquivado ou não está acessível ao seu perfil." />

  const whatsapp = whatsappLink(lead.whatsapp || lead.phone, `Olá, ${lead.name.split(' ')[0]}!`)
  const proposals = lead.proposals || []
  const contracts = lead.contracts || []
  const payments = lead.payments || []
  const calls = lead.calls || []
  const attachments = lead.attachments || []
  const interactions = lead.interactions || []
  const commissions = lead.commissions || []

  return (
    <div>
      <button className="back-link" onClick={() => navigate(-1)}><Icon name="arrowLeft" size={14} />Voltar</button>

      <div className="lead-header">
        <Avatar name={lead.name} color={lead.closer?.avatar_color || undefined} size={46} />
        <div className="lead-head-main">
          <div className="lead-title-row">
            <h1>{lead.name}</h1>
            <Badge tone={stageTone[lead.stage]}>{STAGE_LABELS[lead.stage]}</Badge>
            <span className="stage-chip" style={{ '--chip-color': lead.temperature === 'hot' ? 'var(--c5)' : lead.temperature === 'warm' ? 'var(--c4)' : 'var(--muted)' } as React.CSSProperties}>{TEMPERATURE_LABELS[lead.temperature]}</span>
            {lead.priority >= 7 ? <Badge tone="amber">Prioridade {lead.priority}</Badge> : null}
          </div>
          <div className="lead-subtitle">
            <span><Icon name="building" size={12} />{lead.company || 'Sem empresa'}</span>
            {lead.niche ? <span><span className="niche-dot" style={{ background: lead.niche.color || 'var(--brand)' }} />{lead.niche.name}</span> : null}
            <span><Icon name="user" size={12} />{lead.closer?.name || 'Sem closer'}</span>
            <span><Icon name="calendar" size={12} />Criado em {date(lead.created_at)}</span>
            {lead.origin ? <span><Icon name="target" size={12} />{lead.origin}</span> : null}
          </div>
        </div>
        <div className="lead-actions">
          {whatsapp ? <a className="btn btn-secondary btn-sm" href={whatsapp} target="_blank" rel="noreferrer"><Icon name="whatsapp" size={14} />WhatsApp</a> : null}
          <Button size="sm" icon="pipeline" onClick={openMove}>Mover etapa</Button>
          {canEdit ? <Button size="sm" variant="primary" icon="edit" onClick={() => setEditOpen(true)}>Editar</Button> : null}
          {user?.role === 'admin' ? <IconButton icon="trash" label="Arquivar lead" onClick={deleteLead} /> : null}
        </div>
      </div>

      <div className="lead-kpis">
        <div className="lead-kpi"><span>Ticket estimado</span><b>{money(lead.ticket_estimado)}</b></div>
        <div className="lead-kpi"><span>Valor negociado</span><b>{money(lead.valor_negociado)}</b></div>
        <div className="lead-kpi"><span>Receita prevista</span><b>{money(lead.receita_prevista)}</b></div>
        <div className="lead-kpi"><span>Recebido</span><b className="text-brand">{money(paidTotal)}</b></div>
        <div className="lead-kpi"><span>Saldo devedor</span><b className={Number(lead.saldo_devedor || 0) > 0 ? 'text-danger' : ''}>{money(lead.saldo_devedor)}</b></div>
      </div>

      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'overview', label: 'Visão geral' },
          { value: 'history', label: 'Histórico', count: interactions.length },
          { value: 'calls', label: 'Calls', count: calls.length },
          { value: 'proposals', label: 'Propostas', count: proposals.length },
          { value: 'finance', label: 'Financeiro', count: contracts.length + payments.length },
          { value: 'files', label: 'Arquivos', count: attachments.length },
        ]}
      />

      {tab === 'overview' ? (
        <div className="grid-main-side">
          <div className="stack">
            <section className="panel panel-padded">
              <div className="panel-head"><div><h3>Próximos passos</h3><p className="panel-sub">O que precisa acontecer para avançar esta oportunidade.</p></div><button className="btn btn-sm btn-ghost" onClick={() => setEditOpen(true)}><Icon name="edit" size={13} />Editar</button></div>
              {lead.next_steps?.length ? (
                <ul className="next-steps">{lead.next_steps.map((step, index) => <li key={index}><span className="step-num">{index + 1}</span><span>{step}</span></li>)}</ul>
              ) : <p className="muted small">Nenhum próximo passo registrado. Edite o lead para adicioná-los.</p>}
              {lead.next_action_at ? <div className="callout mt-12"><strong>Próxima ação:</strong> {dateTime(lead.next_action_at)} · {relative(lead.next_action_at)}</div> : null}
            </section>

            <section className="panel panel-padded">
              <div className="panel-head"><div><h3>Interações recentes</h3></div><button className="btn btn-sm btn-ghost" onClick={() => setTab('history')}>Ver histórico <Icon name="arrowRight" size={13} /></button></div>
              {interactions.length ? (
                <div className="timeline">
                  {interactions.slice(0, 5).map((interaction) => (
                    <div className="timeline-item" key={interaction.id}>
                      <span className="timeline-dot" />
                      <div className="timeline-body">
                        <div className="timeline-summary">{interaction.summary}</div>
                        <div className="timeline-meta"><Badge tone={interactionTone[interaction.kind] || 'neutral'}>{interaction.kind.replace('_', ' ')}</Badge><span>{dateTime(interaction.occurred_at)}</span><span>{interaction.user?.name || interaction.author}</span></div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : <p className="muted small">Nenhuma interação registrada.</p>}
            </section>

            {lead.notes ? <section className="panel panel-padded"><h3>Observações</h3><p className="notes-text">{lead.notes}</p></section> : null}
          </div>

          <div className="stack">
            <section className="panel panel-padded">
              <h3>Contato</h3>
              <div className="contact-list">
                {lead.phone ? <a href={`tel:${lead.phone}`}><Icon name="phone" size={14} /><span>{lead.phone}</span></a> : null}
                {lead.whatsapp ? <a href={whatsapp || '#'} target="_blank" rel="noreferrer"><Icon name="whatsapp" size={14} /><span>{lead.whatsapp}</span></a> : null}
                {lead.email ? <a href={`mailto:${lead.email}`}><Icon name="mail" size={14} /><span>{lead.email}</span></a> : null}
                {!lead.phone && !lead.whatsapp && !lead.email ? <p className="muted small">Nenhum contato cadastrado.</p> : null}
              </div>
              <div className="divider" />
              <div className="kv"><span>Closer</span><b>{lead.closer?.name || '—'}</b></div>
              <div className="kv"><span>Pré-venda</span><b>{lead.pre_sales?.name || '—'}</b></div>
              <div className="kv"><span>Última interação</span><b>{dateTime(lead.last_interaction_at)}</b></div>
              <div className="kv"><span>Mudança de etapa</span><b>{dateTime(lead.last_stage_change_at)}</b></div>
            </section>

            <section className="panel panel-padded">
              <div className="panel-head"><div><h3>Lembretes</h3><p className="panel-sub">{pendingReminders.length} pendente(s)</p></div><button className="btn btn-sm btn-ghost" onClick={() => navigate('/reminders')}>Abrir</button></div>
              {pendingReminders.length ? pendingReminders.slice(0, 4).map((reminder) => (
                <div className="reminder-mini" key={reminder.id}>
                  <span className={`priority-dot p${reminder.priority}`} />
                  <div><b>{reminder.title}</b><span>{relative(reminder.due_at)}</span></div>
                </div>
              )) : <p className="muted small">Nenhum lembrete pendente.</p>}
            </section>

            {lead.loss_reason ? <section className="panel panel-padded"><h3>Motivo de loss</h3><p className="notes-text">{lead.loss_reason}</p></section> : null}
            {lead.disqualify_reason ? <section className="panel panel-padded"><h3>Motivo da desqualificação</h3><p className="notes-text">{lead.disqualify_reason}</p></section> : null}
          </div>
        </div>
      ) : null}

      {tab === 'history' ? (
        <div className="grid-main-side">
          <section className="panel panel-padded">
            <h3>Registrar interação</h3>
            <div className="interaction-form">
              <Select value={interactionKind} onChange={(event) => setInteractionKind(event.target.value)} style={{ width: 150 }}>
                {INTERACTION_KINDS.map((kind) => <option key={kind.value} value={kind.value}>{kind.label}</option>)}
              </Select>
              <Input type="datetime-local" value={interactionAt} onChange={(event) => setInteractionAt(event.target.value)} style={{ width: 200 }} />
              <Textarea rows={3} value={interactionText} onChange={(event) => setInteractionText(event.target.value)} placeholder="Resumo do contato, acordos e próximos passos..." />
              <div className="form-actions"><Button variant="primary" size="sm" icon="send" loading={addingInteraction} onClick={addInteraction} disabled={interactionText.trim().length < 2}>Registrar</Button></div>
            </div>
            <div className="divider" />
            {interactions.length ? (
              <div className="timeline">
                {interactions.map((interaction) => (
                  <div className="timeline-item" key={interaction.id}>
                    <span className="timeline-dot" />
                    <div className="timeline-body">
                      <div className="timeline-summary">{interaction.summary}</div>
                      <div className="timeline-meta"><Badge tone={interactionTone[interaction.kind] || 'neutral'}>{interaction.kind.replace('_', ' ')}</Badge><span>{dateTime(interaction.occurred_at)}</span><span>{interaction.user?.name || interaction.author || 'Sistema'}</span></div>
                    </div>
                  </div>
                ))}
              </div>
            ) : <EmptyState icon="message" title="Sem interações" description="Registre a primeira conversa ou nota deste lead." />}
          </section>
          <section className="panel panel-padded">
            <h3>Alterações de etapa</h3>
            {(lead.stage_histories || []).length ? (
              <div className="stage-history">
                {lead.stage_histories!.map((history) => (
                  <div className="stage-history-item" key={history.id}>
                    <span className={`stage-history-dot ${stageTone[history.to_stage] || 'neutral'}`} />
                    <div>
                      <b>{history.from_stage ? `${STAGE_LABELS[history.from_stage]} → ` : ''}{STAGE_LABELS[history.to_stage]}</b>
                      <span>{dateTime(history.created_at)} · {history.user?.name || 'Sistema'}</span>
                      {history.reason ? <p>{history.reason}</p> : null}
                    </div>
                  </div>
                ))}
              </div>
            ) : <p className="muted small">Nenhuma alteração registrada.</p>}
          </section>
        </div>
      ) : null}

      {tab === 'calls' ? (
        <section className="panel" style={{ overflow: 'hidden' }}>
          <div className="panel-toolbar">
            <div><h3>Reuniões e calls</h3><p className="panel-sub">Transcrições analisadas localmente por playbook.</p></div>
            <Button size="sm" variant="primary" icon="plus" onClick={() => navigate(`/calls?lead_id=${lead.id}`)}>Registrar call</Button>
          </div>
          {calls.length ? (
            <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
              <table className="table">
                <thead><tr><th>Call</th><th>Status</th><th>Início</th><th>Duração</th><th>Análise</th><th className="text-right">Adesão</th></tr></thead>
                <tbody>
                  {[...calls].sort((a, b) => String(b.started_at || '').localeCompare(String(a.started_at || ''))).map((call) => (
                    <CallRow key={call.id} call={call} />
                  ))}
                </tbody>
              </table>
            </div>
          ) : <EmptyState icon="calls" title="Nenhuma call registrada" description="Registre a transcrição para gerar resumo, SPIN, objeções e próximos passos." action={<Button size="sm" onClick={() => navigate(`/calls?lead_id=${lead.id}`)}>Registrar call</Button>} />}
        </section>
      ) : null}

      {tab === 'proposals' ? (
        <section className="panel" style={{ overflow: 'hidden' }}>
          <div className="panel-toolbar">
            <div><h3>Propostas</h3><p className="panel-sub">Versões, status e PDF gerado no servidor.</p></div>
            <Button size="sm" variant="primary" icon="plus" onClick={() => { setEditingProposal(null); setProposalOpen(true) }}>Nova proposta</Button>
          </div>
          {proposals.length ? (
            <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
              <table className="table">
                <thead><tr><th>Número</th><th>Título</th><th>Status</th><th>Versão</th><th>Validade</th><th className="text-right">Total</th><th /></tr></thead>
                <tbody>
                  {proposals.map((proposal) => (
                    <tr key={proposal.id}>
                      <td className="mono">{proposal.number}</td>
                      <td className="cell-main">{proposal.title}</td>
                      <td><Badge tone={proposal.status === 'accepted' ? 'brand' : proposal.status === 'rejected' ? 'rose' : proposal.status === 'sent' ? 'blue' : 'neutral'}>{PROPOSAL_STATUS[proposal.status] || proposal.status}</Badge></td>
                      <td>v{proposal.version}</td>
                      <td>{date(proposal.valid_until)}</td>
                      <td className="text-right mono">{money(proposal.total)}</td>
                      <td>
                        <div className="actions">
                          <IconButton icon="download" label="Baixar PDF" onClick={() => download(`/proposals/${proposal.id}/pdf`, `proposta-${proposal.number}.pdf`).catch((requestError) => notify(apiMessage(requestError), 'error'))} />
                          <IconButton icon="edit" label="Editar proposta" onClick={() => { setEditingProposal(proposal); setProposalOpen(true) }} />
                          {['sent', 'accepted'].includes(proposal.status) ? <button className="btn btn-sm btn-soft" onClick={() => generateContract(proposal)}>Gerar contrato</button> : null}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : <EmptyState icon="proposals" title="Nenhuma proposta" description="Crie a primeira proposta comercial deste lead." action={<Button size="sm" onClick={() => setProposalOpen(true)}>Nova proposta</Button>} />}
          {proposals.length ? <div className="panel-foot">Valor total em propostas: <b>{money(proposalTotal)}</b></div> : null}
        </section>
      ) : null}

      {tab === 'finance' ? (
        <div className="stack">
          <div className="grid-main-side">
            <section className="panel panel-padded">
              <div className="panel-head"><div><h3>Contratos</h3><p className="panel-sub">{contracts.length} contrato(s)</p></div><Button size="sm" icon="plus" onClick={() => { setContractProposal(null); setContractOpen(true) }}>Novo contrato</Button></div>
              {contracts.length ? (
                <div className="contract-list">
                  {contracts.map((contract) => (
                    <div className="contract-row" key={contract.id}>
                      <div className="contract-main"><b>{contract.title}</b><span className="mono">{contract.number} · {money(contract.total)}</span></div>
                      <Select value={contract.status} onChange={(event) => updateContractStatus(contract, event.target.value)} style={{ width: 175 }}>
                        {Object.entries(CONTRACT_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                      </Select>
                      <IconButton icon="download" label="Baixar contrato" onClick={() => download(`/contracts/${contract.id}/pdf`, `contrato-${contract.number}.pdf`).catch((requestError) => notify(apiMessage(requestError), 'error'))} />
                    </div>
                  ))}
                </div>
              ) : <p className="muted small">Nenhum contrato criado.</p>}
            </section>
            <section className="panel panel-padded">
              <h3>Resumo financeiro</h3>
              <div className="kv"><span>Contratado em propostas</span><b>{money(proposalTotal)}</b></div>
              <div className="kv"><span>Total recebido</span><b className="text-brand">{money(paidTotal)}</b></div>
              <div className="kv"><span>Em aberto</span><b>{money(pendingTotal)}</b></div>
              <div className="kv"><span>Saldo devedor</span><b className="text-danger">{money(lead.saldo_devedor)}</b></div>
              <div className="divider" />
              {commissions.length ? commissions.map((commission) => (
                <div className="kv" key={commission.id}><span>Comissão {commission.period}</span><b>{money(commission.amount)}</b></div>
              )) : <p className="muted small">Comissões aparecem após o fechamento e cálculo.</p>}
            </section>
          </div>

          <section className="panel" style={{ overflow: 'hidden' }}>
            <div className="panel-toolbar">
              <div><h3>Pagamentos e parcelas</h3><p className="panel-sub">Status de cobrança é separado de receita contratada.</p></div>
              <Button size="sm" variant="primary" icon="plus" onClick={openInstallments}>Gerar parcelas</Button>
            </div>
            {payments.length ? (
              <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
                <table className="table">
                  <thead><tr><th>Parcela</th><th>Tipo</th><th>Vencimento</th><th>Status</th><th>Método</th><th className="text-right">Valor</th><th /></tr></thead>
                  <tbody>
                    {[...payments].sort((a, b) => a.due_at.localeCompare(b.due_at)).map((payment) => (
                      <tr key={payment.id}>
                        <td className="mono">#{payment.installment_number}</td>
                        <td>{payment.kind}</td>
                        <td>{date(payment.due_at)}</td>
                        <td><Badge tone={payment.status === 'paid' ? 'brand' : payment.status === 'overdue' ? 'rose' : payment.status === 'cancelled' ? 'neutral' : 'amber'}>{PAYMENT_STATUS[payment.status] || payment.status}</Badge></td>
                        <td>{payment.method ? PAYMENT_METHODS[payment.method] || payment.method : '—'}</td>
                        <td className="text-right mono">{money(payment.amount)}</td>
                        <td>{payment.status !== 'paid' && payment.status !== 'cancelled' ? <Button size="sm" onClick={() => markPaid(payment)}>Receber</Button> : null}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : <EmptyState icon="payments" title="Nenhum pagamento" description="Gere parcelas para acompanhar o contas a receber deste lead." action={<Button size="sm" onClick={openInstallments}>Gerar parcelas</Button>} />}
          </section>
        </div>
      ) : null}

      {tab === 'files' ? (
        <section className="panel panel-padded">
          <div className="panel-head">
            <div><h3>Anexos</h3><p className="panel-sub">PDF, documentos e imagens até 10 MB.</p></div>
            <Button size="sm" icon="upload" onClick={() => fileRef.current?.click()}>Enviar arquivo</Button>
          </div>
          <input ref={fileRef} type="file" hidden accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.txt,.csv" onChange={(event) => { const file = event.target.files?.[0]; if (file) uploadAttachment(file) }} />
          {attachments.length ? (
            <div className="attachment-list">
              {attachments.map((attachment) => (
                <div className="attachment-row" key={attachment.id}>
                  <span className="attachment-icon"><Icon name="file" size={17} /></span>
                  <div className="attachment-main"><b>{attachment.file_name}</b><span>{attachment.size_kb} KB · {date(attachment.uploaded_at || attachment.created_at)} · {attachment.uploaded_by?.name || 'Sistema'}</span></div>
                  <IconButton icon="download" label="Baixar anexo" onClick={() => download(`/leads/${lead.id}/attachments/${attachment.id}/download`, attachment.file_name).catch((requestError) => notify(apiMessage(requestError), 'error'))} />
                  <IconButton icon="trash" label="Remover anexo" onClick={() => setConfirmDeleteAttachment(attachment.id)} />
                </div>
              ))}
            </div>
          ) : <EmptyState icon="inbox" title="Nenhum arquivo" description="Envie documentos, propostas assinadas ou comprovantes." action={<Button size="sm" onClick={() => fileRef.current?.click()}>Enviar arquivo</Button>} />}
        </section>
      ) : null}

      <LeadFormModal open={editOpen} lead={lead} onClose={() => setEditOpen(false)} onSaved={() => { setEditOpen(false); reload() }} />
      <ProposalFormModal open={proposalOpen} leadId={lead.id} leadName={lead.name} proposal={editingProposal} onClose={() => { setProposalOpen(false); setEditingProposal(null) }} onSaved={() => { setProposalOpen(false); setEditingProposal(null); reload() }} />
      <ContractFormModal open={contractOpen} leadId={lead.id} leadName={lead.name} proposal={contractProposal} onClose={() => { setContractOpen(false); setContractProposal(null) }} onSaved={() => { setContractOpen(false); setContractProposal(null); reload() }} />

      <Modal
        open={moveOpen}
        onClose={() => { if (!moving) setMoveOpen(false) }}
        title="Mover etapa"
        subtitle={`${lead.name} · atualmente em ${STAGE_LABELS[lead.stage]}`}
        footer={<><Button onClick={() => setMoveOpen(false)} disabled={moving}>Cancelar</Button><Button variant="primary" loading={moving} onClick={submitMove}>Mover lead</Button></>}
      >
        <div className="stack-sm">
          <Field label="Nova etapa" required>
            <Select value={moveStage} onChange={(event) => { setMoveStage(event.target.value); setMoveTicket(lead.ticket_fechado ? String(lead.ticket_fechado) : '') }}>
              {allowedStages.map((stage) => <option key={stage.value} value={stage.value}>{stage.label}</option>)}
            </Select>
          </Field>
          {moveStage === 'fechado' ? <Field label="Valor fechado"><Input type="number" min={0} step="0.01" value={moveTicket} onChange={(event) => setMoveTicket(event.target.value)} placeholder="0,00" /></Field> : null}
          {moveStage === 'loss' || moveStage === 'mal_qualificado' ? <Field label="Motivo obrigatório" required><Textarea value={moveReason} onChange={(event) => setMoveReason(event.target.value)} placeholder="Descreva o motivo para relatórios e auditoria." /></Field> : null}
          {user?.role === 'sdr' ? <div className="callout warn">Como SDR, você só pode mover para Lead Agendado, FUP, Remarcado ou Mal Qualificado.</div> : null}
        </div>
      </Modal>

      <Modal
        open={installmentOpen}
        onClose={() => { if (!creatingInstallments) setInstallmentOpen(false) }}
        title="Gerar parcelas"
        subtitle="Divisão calculada em centavos, sem perder arredondamento."
        footer={<><Button onClick={() => setInstallmentOpen(false)}>Cancelar</Button><Button variant="primary" loading={creatingInstallments} onClick={createInstallments}>Gerar parcelas</Button></>}
      >
        <div className="form-grid">
          <Field label="Valor total" required><Input type="number" min={0.01} step="0.01" value={installmentTotal} onChange={(event) => setInstallmentTotal(event.target.value)} /></Field>
          <Field label="Parcelas" required><Input type="number" min={1} max={60} value={installmentCount} onChange={(event) => setInstallmentCount(event.target.value)} /></Field>
          <Field label="Primeiro vencimento" required><Input type="date" value={installmentFirst} onChange={(event) => setInstallmentFirst(event.target.value)} /></Field>
          <Field label="Intervalo (dias)"><Input type="number" min={1} max={365} value={installmentInterval} onChange={(event) => setInstallmentInterval(event.target.value)} /></Field>
        </div>
      </Modal>

      <ConfirmDialog open={Boolean(confirmDeleteAttachment)} title="Remover anexo" message="O arquivo será excluído permanentemente do armazenamento local. Esta ação não pode ser desfeita." confirmLabel="Remover" loading={deletingAttachment} onClose={() => setConfirmDeleteAttachment(null)} onConfirm={removeAttachment} />
    </div>
  )
}

function CallRow({ call }: { call: Call }) {
  const analysis = call.analysis_status === 'completed' ? 'Concluída' : call.analysis_status === 'pending' ? 'Pendente' : call.analysis_status === 'failed' ? 'Falhou' : 'Não solicitada'
  return (
    <tr>
      <td><div className="cell-main">{call.title}</div><div className="cell-sub">{call.closer?.name || 'Sem closer'}</div></td>
      <td><Badge tone={call.status === 'completed' ? 'brand' : call.status === 'no_show' ? 'rose' : call.status === 'cancelled' ? 'neutral' : 'blue'}>{CALL_STATUS_LABELS[call.status] || call.status}</Badge></td>
      <td>{dateTime(call.started_at)}</td>
      <td>{Math.round(call.duration_sec / 60)} min</td>
      <td><Badge tone={call.analysis_status === 'completed' ? 'green' : call.analysis_status === 'failed' ? 'rose' : 'neutral'}>{analysis}</Badge></td>
      <td className="text-right">{call.adherence_score !== null && call.adherence_score !== undefined ? `${call.adherence_score}%` : '—'}</td>
    </tr>
  )
}
