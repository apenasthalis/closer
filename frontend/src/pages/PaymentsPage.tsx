import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, apiMessage } from '@/lib/api'
import { useApi, useLeadOptions, usePaymentSources } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { PAYMENT_METHODS, PAYMENT_STATUS } from '@/lib/constants'
import { date, money, today } from '@/lib/format'
import type { Payment } from '@/lib/types'
import {
  Badge, Button, ConfirmDialog, EmptyState, ErrorState, Field, IconButton, Input,
  LoadingState, Modal, Select, Stat, Textarea, useToast,
} from '@/components/ui'

interface PaymentResponse {
  data: Payment[]
  summary: { pending: number; paid: number; overdue: number; cash_in?: number; cash_out?: number }
}

export function PaymentsPage() {
  const { user } = useAuth()
  const { notify } = useToast()
  const leads = useLeadOptions()
  const { sources } = usePaymentSources()
  const [status, setStatus] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [open, setOpen] = useState(false)
  const [leadId, setLeadId] = useState('')
  const [kind, setKind] = useState('installment')
  const [amount, setAmount] = useState('')
  const [dueAt, setDueAt] = useState(today())
  const [method, setMethod] = useState('pix')
  const [paymentStatus, setPaymentStatus] = useState('pending')
  const [notes, setNotes] = useState('')
  const [nextAction, setNextAction] = useState('')
  const [nextActionAt, setNextActionAt] = useState('')
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')
  const [deleting, setDeleting] = useState<Payment | null>(null)

  const { data, loading, error, reload } = useApi<PaymentResponse>('/payments', {
    status: status || undefined,
    from: from || undefined,
    to: to || undefined,
  })
  const payments = data?.data || []
  const canDelete = user?.role === 'admin'
  const selectedSource = sources.find((source) => source.slug === method) || null

  const summary = useMemo(() => ({
    paid: payments.filter((payment) => payment.status === 'paid').reduce((sum, payment) => sum + Number(payment.amount), 0),
    pending: payments.filter((payment) => payment.status === 'pending').reduce((sum, payment) => sum + Number(payment.amount), 0),
    overdue: payments.filter((payment) => payment.status === 'pending' && new Date(payment.due_at).getTime() < Date.now()).reduce((sum, payment) => sum + Number(payment.amount), 0),
    total: payments.reduce((sum, payment) => sum + Number(payment.amount), 0),
    cashIn: payments.filter((payment) => payment.status === 'paid' && payment.enters_cash !== false).reduce((sum, payment) => sum + Number(payment.amount), 0),
    cashOut: payments.filter((payment) => payment.status === 'paid' && payment.enters_cash === false).reduce((sum, payment) => sum + Number(payment.amount), 0),
  }), [payments])

  const create = async () => {
    setSaving(true)
    setFormError('')
    try {
      await api.post('/payments', {
        lead_id: leadId,
        kind,
        amount: Number(amount),
        due_at: dueAt,
        status: paymentStatus,
        method,
        notes: notes || null,
        next_action: nextAction || null,
        next_action_at: nextActionAt || null,
      })
      notify('Pagamento registrado.')
      setOpen(false)
      setAmount('')
      setNotes('')
      setNextAction('')
      setNextActionAt('')
      setLeadId('')
      reload()
    } catch (requestError) {
      setFormError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  const changeStatus = async (payment: Payment, nextStatus: string) => {
    try {
      await api.patch(`/payments/${payment.id}/status`, { status: nextStatus, method: payment.method || 'pix' })
      notify('Pagamento atualizado.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const remove = async () => {
    if (!deleting) return
    try {
      await api.delete(`/payments/${deleting.id}`)
      notify('Pagamento removido.')
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
          <Select value={status} onChange={(event) => setStatus(event.target.value)} style={{ width: 150 }}>
            <option value="">Todos os status</option>
            {Object.entries(PAYMENT_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </Select>
          <label className="pill" style={{ gap: 6 }}><span>De</span><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="bare-input" /></label>
          <label className="pill" style={{ gap: 6 }}><span>Até</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="bare-input" /></label>
          {from || to || status ? <Button size="sm" variant="ghost" onClick={() => { setFrom(''); setTo(''); setStatus('') }}>Limpar</Button> : null}
        </div>
        <Button variant="primary" icon="plus" onClick={() => { setFormError(''); setOpen(true) }}>Novo pagamento</Button>
      </div>

      <div className="kpi-row four">
        <Stat label="Total na visão" value={money(summary.total, true)} caption={`${payments.length} lançamento(s)`} icon="payments" tone="blue" />
        <Stat label="Recebido" value={money(summary.paid, true)} caption="status pago" icon="checkCircle" tone="green" />
        <Stat label="Entrou no caixa" value={money(summary.cashIn, true)} caption="pix, cartão e contratos" icon="money" tone="green" />
        <Stat label="Não entrou no caixa" value={money(summary.cashOut, true)} caption="boleto e alikeis" icon="alert" tone="amber" />
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !payments.length ? (
        <div className="panel panel-padded"><EmptyState icon="payments" title="Nenhum pagamento" description="Lance pagamentos avulsos ou gere parcelas na ficha do lead." action={<Button onClick={() => setOpen(true)}>Novo pagamento</Button>} /></div>
      ) : (
        <div className="table-wrap">
          <table className="table">
            <thead><tr><th>Lead</th><th>Parcela</th><th>Tipo</th><th>Vencimento</th><th>Status</th><th>Fonte</th><th>Caixa</th><th>Contrato</th><th className="text-right">Valor</th><th /></tr></thead>
            <tbody>
              {payments.map((payment) => (
                <tr key={payment.id}>
                  <td className="cell-main">{payment.lead ? <Link to={`/leads/${payment.lead.id}`}>{payment.lead.name}</Link> : '—'}</td>
                  <td className="mono">#{payment.installment_number}</td>
                  <td>{payment.kind}</td>
                  <td className={payment.status === 'pending' && new Date(payment.due_at).getTime() < Date.now() ? 'text-danger' : ''}>{date(payment.due_at)}</td>
                  <td>
                    <Select value={payment.status} onChange={(event) => changeStatus(payment, event.target.value)} style={{ width: 125, padding: '5px 8px', fontSize: 11 }}>
                      {Object.entries(PAYMENT_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                    </Select>
                  </td>
                  <td>{payment.method ? PAYMENT_METHODS[payment.method] || payment.method : '—'}</td>
                  <td>{payment.enters_cash === false ? <Badge tone="neutral">Não entra</Badge> : <Badge tone="green">Entra</Badge>}</td>
                  <td className="mono">{payment.contract?.number || '—'}</td>
                  <td className="text-right mono">{money(payment.amount)}</td>
                  <td>{canDelete ? <IconButton icon="trash" label="Remover pagamento" onClick={() => setDeleting(payment)} /> : <Badge tone={payment.status === 'paid' ? 'brand' : 'neutral'}>{PAYMENT_STATUS[payment.status]}</Badge>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={open} onClose={() => { if (!saving) setOpen(false) }} title="Novo pagamento" subtitle="Lançamento avulso vinculado a um lead." footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={create}>Registrar</Button></>}>
        {formError ? <div className="login-error" style={{ marginBottom: 13 }}>{formError}</div> : null}
        <div className="form-grid">
          <Field label="Lead" required className="span-2">
            <Select value={leadId} onChange={(event) => setLeadId(event.target.value)}>
              <option value="">Selecione</option>
              {leads.map((lead) => <option key={lead.id} value={lead.id}>{lead.name}{lead.company ? ` · ${lead.company}` : ''}</option>)}
            </Select>
          </Field>
          <Field label="Valor" required><Input type="number" min={0.01} step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} /></Field>
          <Field label="Vencimento" required><Input type="date" value={dueAt} onChange={(event) => setDueAt(event.target.value)} /></Field>
          <Field label="Tipo"><Select value={kind} onChange={(event) => setKind(event.target.value)}><option value="installment">Parcela</option><option value="entry">Entrada</option><option value="recurring">Recorrente</option><option value="other">Outro</option></Select></Field>
          <Field label="Status"><Select value={paymentStatus} onChange={(event) => setPaymentStatus(event.target.value)}>{Object.entries(PAYMENT_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
          <Field label="Fonte" hint={selectedSource ? (selectedSource.enters_cash ? 'Entra no caixa.' : 'Não entra no caixa.') : undefined}>
            <Select value={method} onChange={(event) => setMethod(event.target.value)}>
              {sources.length ? sources.map((source) => (
                <option key={source.slug} value={source.slug}>{source.name}{source.enters_cash ? ' (entra no caixa)' : ' (não entra no caixa)'}</option>
              )) : Object.entries(PAYMENT_METHODS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
            </Select>
          </Field>
          <Field label="Próxima ação" className="span-2" hint="O que falar com o cliente sobre este lançamento."><Textarea rows={2} value={nextAction} onChange={(event) => setNextAction(event.target.value)} placeholder="Ex.: cobrar o boleto e confirmar a data de pagamento." /></Field>
          <Field label="Data da próxima ação"><Input type="date" value={nextActionAt} onChange={(event) => setNextActionAt(event.target.value)} /></Field>
          <Field label="Observações"><Textarea rows={2} value={notes} onChange={(event) => setNotes(event.target.value)} /></Field>
        </div>
      </Modal>

      <ConfirmDialog open={Boolean(deleting)} title="Remover pagamento" message={`Remover o lançamento de ${money(deleting?.amount)}? Esta ação é exclusiva para administradores.`} confirmLabel="Remover" onClose={() => setDeleting(null)} onConfirm={remove} />
    </div>
  )
}
