import { useEffect, useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useLookups } from '@/lib/hooks'
import { STAGES, TEMPERATURES, ORIGINS } from '@/lib/constants'
import type { Lead } from '@/lib/types'
import { Button, Field, Input, Modal, Select, Textarea, useToast } from './ui'

interface LeadFormModalProps {
  open: boolean
  onClose: () => void
  lead?: Lead | null
  onSaved: (lead: Lead) => void
  defaultStage?: string
}

function toLocalInput(value?: string | null) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const offset = date.getTimezoneOffset()
  return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16)
}

function emptyForm(defaultStage = 'lead_agendado') {
  return {
    name: '',
    company: '',
    phone: '',
    whatsapp: '',
    email: '',
    niche_id: '',
    origin: '',
    stage: defaultStage,
    temperature: 'warm',
    priority: '0',
    closer_id: '',
    pre_sales_id: '',
    ticket_estimado: '',
    valor_negociado: '',
    receita_prevista: '',
    commission_percentage: '7',
    commission_amount: '',
    company_id: '',
    meeting_at: '',
    next_action_at: '',
    notes: '',
  }
}

export function LeadFormModal({ open, onClose, lead, onSaved, defaultStage }: LeadFormModalProps) {
  const { niches, users, companies } = useLookups(open)
  const { notify } = useToast()
  const [form, setForm] = useState(emptyForm(defaultStage))
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!open) return
    setError('')
    if (lead) {
      setForm({
        name: lead.name || '',
        company: lead.company || '',
        phone: lead.phone || '',
        whatsapp: lead.whatsapp || '',
        email: lead.email || '',
        niche_id: lead.niche_id || '',
        origin: lead.origin || '',
        stage: lead.stage || 'lead_agendado',
        temperature: lead.temperature || 'warm',
        priority: String(lead.priority ?? 0),
        closer_id: lead.closer_id ? String(lead.closer_id) : '',
        pre_sales_id: lead.pre_sales_id ? String(lead.pre_sales_id) : '',
        ticket_estimado: lead.ticket_estimado ? String(lead.ticket_estimado) : '',
        valor_negociado: lead.valor_negociado ? String(lead.valor_negociado) : '',
        receita_prevista: lead.receita_prevista ? String(lead.receita_prevista) : '',
        commission_percentage: lead.commission_percentage !== null && lead.commission_percentage !== undefined ? String(lead.commission_percentage) : '',
        commission_amount: lead.commission_amount !== null && lead.commission_amount !== undefined ? String(lead.commission_amount) : '',
        company_id: lead.company_id || '',
        meeting_at: lead.meeting_at ? new Date(lead.meeting_at).toISOString().slice(0, 16) : '',
        next_action_at: toLocalInput(lead.next_action_at),
        notes: lead.notes || '',
      })
    } else {
      setForm(emptyForm(defaultStage))
    }
  }, [open, lead, defaultStage])

  const change = (field: string, value: string) => setForm((current) => ({ ...current, [field]: value }))

  const save = async () => {
    setSaving(true)
    setError('')
    const selectedCompany = companies.find((c) => c.id === form.company_id)
    const payload: Record<string, unknown> = {
      name: form.name,
      company: selectedCompany ? selectedCompany.name : null,
      phone: form.phone || null,
      whatsapp: form.whatsapp || null,
      email: form.email || null,
      niche_id: form.niche_id || null,
      origin: form.origin || null,
      stage: form.stage,
      temperature: form.temperature,
      priority: Number(form.priority || 0),
      closer_id: form.closer_id ? Number(form.closer_id) : null,
      pre_sales_id: form.pre_sales_id ? Number(form.pre_sales_id) : null,
      ticket_estimado: form.ticket_estimado || null,
      valor_negociado: form.valor_negociado || null,
      receita_prevista: form.receita_prevista || null,
      commission_percentage: form.commission_percentage || null,
      commission_amount: form.commission_amount || null,
      company_id: form.company_id || null,
      meeting_at: form.meeting_at || null,
      next_action_at: form.next_action_at || null,
      notes: form.notes || null,
    }

    try {
      const response = lead
        ? await api.put(`/leads/${lead.id}`, payload)
        : await api.post('/leads', payload)
      notify(lead ? 'Lead atualizado.' : 'Lead criado com sucesso.')
      onSaved(response.data.data)
      onClose()
    } catch (requestError) {
      setError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={lead ? `Editar ${lead.name}` : 'Novo lead'}
      subtitle="Dados comerciais e responsáveis. A etapa pode ser ajustada no pipeline."
      size="lg"
      footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>{lead ? 'Salvar alterações' : 'Criar lead'}</Button></>}
    >
      {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
      <div className="form-grid">
        <Field label="Nome completo" required><Input value={form.name} onChange={(event) => change('name', event.target.value)} placeholder="Nome do contato" /></Field>
        <Field label="Telefone"><Input value={form.phone} onChange={(event) => change('phone', event.target.value)} placeholder="(62) 99999-0000" /></Field>
        <Field label="WhatsApp"><Input value={form.whatsapp} onChange={(event) => change('whatsapp', event.target.value)} placeholder="(62) 99999-0000" /></Field>
        <Field label="E-mail"><Input type="email" value={form.email} onChange={(event) => change('email', event.target.value)} placeholder="contato@empresa.com" /></Field>
        <Field label="Nicho">
          <Select value={form.niche_id} onChange={(event) => change('niche_id', event.target.value)}>
            <option value="">Sem nicho</option>
            {niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}
          </Select>
        </Field>
        <Field label="Empresa">
          <Select value={form.company_id} onChange={(event) => change('company_id', event.target.value)}>
            <option value="">Sem empresa</option>
            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
          </Select>
        </Field>
        <Field label="Origem">
          <Select value={form.origin} onChange={(event) => change('origin', event.target.value)}>
            <option value="">Não informada</option>
            {ORIGINS.map((origin) => <option key={origin} value={origin}>{origin}</option>)}
          </Select>
        </Field>
        <Field label="Etapa">
          <Select value={form.stage} onChange={(event) => change('stage', event.target.value)}>
            {STAGES.map((stage) => <option key={stage.value} value={stage.value}>{stage.label}</option>)}
          </Select>
        </Field>
        <Field label="Temperatura">
          <Select value={form.temperature} onChange={(event) => change('temperature', event.target.value)}>
            {TEMPERATURES.map((temperature) => <option key={temperature.value} value={temperature.value}>{temperature.label}</option>)}
          </Select>
        </Field>
        <Field label="Prioridade" hint="0 a 10. Valores maiores aparecem primeiro."><Input type="number" min={0} max={10} value={form.priority} onChange={(event) => change('priority', event.target.value)} /></Field>
        <Field label="Closer responsável">
          <Select value={form.closer_id} onChange={(event) => change('closer_id', event.target.value)}>
            <option value="">Sem responsável</option>
            {users.filter((user) => user.role !== 'sdr').map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
          </Select>
        </Field>
        <Field label="Pré-venda / SDR">
          <Select value={form.pre_sales_id} onChange={(event) => change('pre_sales_id', event.target.value)}>
            <option value="">Sem responsável</option>
            {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
          </Select>
        </Field>
        <Field label="Ticket estimado"><Input type="number" min={0} step="0.01" value={form.ticket_estimado} onChange={(event) => change('ticket_estimado', event.target.value)} placeholder="0,00" /></Field>
        <Field label="Valor negociado"><Input type="number" min={0} step="0.01" value={form.valor_negociado} onChange={(event) => change('valor_negociado', event.target.value)} placeholder="0,00" /></Field>
        <Field label="Receita prevista"><Input type="number" min={0} step="0.01" value={form.receita_prevista} onChange={(event) => change('receita_prevista', event.target.value)} placeholder="0,00" /></Field>
        <Field label="Comissão (%)" hint="Comissão combinada na venda."><Input type="number" min={0} max={100} step="0.01" value={form.commission_percentage} onChange={(event) => change('commission_percentage', event.target.value)} placeholder="10" /></Field>
        <Field label="Comissão fixa (R$)"><Input type="number" min={0} step="0.01" value={form.commission_amount} onChange={(event) => change('commission_amount', event.target.value)} placeholder="0,00" /></Field>
        <Field label="Reunião (data e hora)"><Input type="datetime-local" value={form.meeting_at} onChange={(event) => change('meeting_at', event.target.value)} /></Field>
        <Field label="Próxima ação"><Input type="datetime-local" value={form.next_action_at} onChange={(event) => change('next_action_at', event.target.value)} /></Field>
        <Field label="Observações" className="span-2"><Textarea value={form.notes} onChange={(event) => change('notes', event.target.value)} placeholder="Contexto da oportunidade, critérios e combinados." /></Field>
      </div>
    </Modal>
  )
}
