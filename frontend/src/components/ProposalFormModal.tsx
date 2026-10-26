import { useEffect, useMemo, useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { money } from '@/lib/format'
import type { Proposal, ProposalItem } from '@/lib/types'
import { Button, Field, IconButton, Input, Modal, Textarea, useToast } from './ui'

interface ProposalFormModalProps {
  open: boolean
  onClose: () => void
  leadId?: string
  leadName?: string
  proposal?: Proposal | null
  onSaved: (proposal: Proposal) => void
}

function initialItems(proposal?: Proposal | null): ProposalItem[] {
  return proposal?.items?.length
    ? proposal.items.map((item) => ({ description: item.description, quantity: Number(item.quantity), unit_price: Number(item.unit_price) }))
    : [{ description: '', quantity: 1, unit_price: 0 }]
}

export function ProposalFormModal({ open, onClose, leadId, leadName, proposal, onSaved }: ProposalFormModalProps) {
  const { notify } = useToast()
  const [title, setTitle] = useState('')
  const [validUntil, setValidUntil] = useState('')
  const [items, setItems] = useState<ProposalItem[]>(initialItems(proposal))
  const [discount, setDiscount] = useState('0')
  const [paymentTerms, setPaymentTerms] = useState('')
  const [clauses, setClauses] = useState('')
  const [notes, setNotes] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const targetLead = proposal?.lead_id || leadId || ''

  useEffect(() => {
    if (!open) return
    setError('')
    setTitle(proposal?.title || `Proposta comercial${leadName ? ` — ${leadName}` : ''}`)
    setValidUntil(proposal?.valid_until ? String(proposal.valid_until).slice(0, 10) : new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10))
    setItems(initialItems(proposal))
    setDiscount(String(proposal?.discount ? Number(proposal.discount) : 0))
    setPaymentTerms(proposal?.payment_terms || '')
    setClauses((proposal?.clauses || []).join('\n'))
    setNotes(proposal?.notes || '')
  }, [open, proposal, leadName])

  const subtotal = useMemo(() => items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0), [items])
  const discountValue = Math.min(Number(discount || 0), subtotal)
  const total = Math.max(0, subtotal - discountValue)

  const changeItem = (index: number, field: keyof ProposalItem, value: string) => {
    setItems((current) => current.map((item, itemIndex) => itemIndex === index ? { ...item, [field]: field === 'description' ? value : Number(value) } : item))
  }

  const save = async () => {
    if (!targetLead) {
      setError('Lead não identificado para a proposta.')
      return
    }
    if (items.some((item) => !item.description.trim())) {
      setError('Descreva todos os itens da proposta.')
      return
    }
    setSaving(true)
    setError('')
    const payload = {
      title,
      valid_until: validUntil || null,
      items: items.map((item) => ({ description: item.description.trim(), quantity: Number(item.quantity), unit_price: Number(item.unit_price) })),
      discount: Number(discount || 0),
      payment_terms: paymentTerms || null,
      clauses: clauses.split('\n').map((line) => line.trim()).filter(Boolean),
      notes: notes || null,
    }
    try {
      const response = proposal
        ? await api.put(`/proposals/${proposal.id}`, payload)
        : await api.post(`/leads/${targetLead}/proposals`, payload)
      notify(proposal ? 'Proposta atualizada.' : 'Proposta criada.')
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
      title={proposal ? `Editar proposta ${proposal.number}` : 'Nova proposta'}
      subtitle={proposal ? 'Uma nova versão é registrada a cada edição.' : 'Itens, condições e cláusulas que formarão o PDF.'}
      size="lg"
      footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>{proposal ? 'Salvar nova versão' : 'Criar proposta'}</Button></>}
    >
      {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
      <div className="form-grid">
        <Field label="Título" required className="span-2"><Input value={title} onChange={(event) => setTitle(event.target.value)} placeholder="Proposta comercial" /></Field>
        <Field label="Válida até"><Input type="date" value={validUntil} onChange={(event) => setValidUntil(event.target.value)} /></Field>
        <Field label="Desconto (R$)"><Input type="number" min={0} step="0.01" value={discount} onChange={(event) => setDiscount(event.target.value)} /></Field>
      </div>

      <div className="section-title mt-16">Itens da proposta</div>
      <div className="proposal-items">
        <div className="proposal-item-head"><span>Descrição</span><span>Qtd.</span><span>Valor unit.</span><span>Total</span><span /></div>
        {items.map((item, index) => (
          <div className="proposal-item-row" key={index}>
            <Input value={item.description} onChange={(event) => changeItem(index, 'description', event.target.value)} placeholder="Serviço ou entregável" />
            <Input type="number" min={0.01} step="0.01" value={item.quantity} onChange={(event) => changeItem(index, 'quantity', event.target.value)} />
            <Input type="number" min={0} step="0.01" value={item.unit_price} onChange={(event) => changeItem(index, 'unit_price', event.target.value)} />
            <span className="proposal-item-total">{money(Number(item.quantity || 0) * Number(item.unit_price || 0))}</span>
            <IconButton icon="trash" label="Remover item" disabled={items.length === 1} onClick={() => setItems((current) => current.filter((_, itemIndex) => itemIndex !== index))} />
          </div>
        ))}
        <Button size="sm" icon="plus" onClick={() => setItems((current) => [...current, { description: '', quantity: 1, unit_price: 0 }])}>Adicionar item</Button>
      </div>

      <div className="proposal-totals">
        <span>Subtotal <b>{money(subtotal)}</b></span>
        <span>Desconto <b>- {money(discountValue)}</b></span>
        <span className="total">Total <b>{money(total)}</b></span>
      </div>

      <div className="form-grid mt-16">
        <Field label="Condições de pagamento" className="span-2"><Textarea value={paymentTerms} onChange={(event) => setPaymentTerms(event.target.value)} placeholder="Ex.: 50% na assinatura e 50% em 30 dias." rows={2} /></Field>
        <Field label="Cláusulas" hint="Uma cláusula por linha."><Textarea value={clauses} onChange={(event) => setClauses(event.target.value)} placeholder="Escopo e prazo de entrega.&#10;Política de cancelamento." rows={4} /></Field>
        <Field label="Observações"><Textarea value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Observações internas ou comerciais." rows={4} /></Field>
      </div>
    </Modal>
  )
}
