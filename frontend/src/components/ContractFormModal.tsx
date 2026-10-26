import { useEffect, useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import type { Contract, Proposal } from '@/lib/types'
import { Button, Field, Input, Modal, Textarea, useToast } from './ui'

interface ContractFormModalProps {
  open: boolean
  onClose: () => void
  leadId?: string
  leadName?: string
  proposal?: Proposal | null
  onSaved: (contract: Contract) => void
}

export function ContractFormModal({ open, onClose, leadId, leadName, proposal, onSaved }: ContractFormModalProps) {
  const { notify } = useToast()
  const [title, setTitle] = useState('')
  const [total, setTotal] = useState('0')
  const [content, setContent] = useState('')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!open) return
    setError('')
    setTitle(proposal ? `Contrato — ${proposal.title}` : `Contrato${leadName ? ` — ${leadName}` : ''}`)
    setTotal(String(proposal ? Number(proposal.total) : 0))
    setContent(proposal
      ? `Contrato referente à proposta ${proposal.number}. ${proposal.payment_terms || ''}`
      : '')
    setStartDate(new Date().toISOString().slice(0, 10))
    setEndDate('')
  }, [open, proposal, leadName])

  const save = async () => {
    if (!leadId) {
      setError('Lead não identificado para o contrato.')
      return
    }
    if (content.trim().length < 20) {
      setError('O conteúdo do contrato deve ter pelo menos 20 caracteres.')
      return
    }
    setSaving(true)
    setError('')
    try {
      const response = await api.post(`/leads/${leadId}/contracts`, {
        proposal_id: proposal?.id || null,
        title,
        total: Number(total || 0),
        content,
        start_date: startDate || null,
        end_date: endDate || null,
      })
      notify('Contrato criado.')
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
      title="Novo contrato"
      subtitle="O PDF é gerado a partir do conteúdo registrado abaixo."
      size="lg"
      footer={<><Button onClick={onClose}>Cancelar</Button><Button variant="primary" loading={saving} onClick={save}>Criar contrato</Button></>}
    >
      {error ? <div className="login-error" style={{ marginBottom: 13 }}>{error}</div> : null}
      <div className="form-grid">
        <Field label="Título" required className="span-2"><Input value={title} onChange={(event) => setTitle(event.target.value)} /></Field>
        <Field label="Valor total (R$)" required><Input type="number" min={0} step="0.01" value={total} onChange={(event) => setTotal(event.target.value)} /></Field>
        <Field label="Início"><Input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} /></Field>
        <Field label="Término"><Input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} /></Field>
        <Field label="Conteúdo do contrato" required className="span-2"><Textarea rows={9} value={content} onChange={(event) => setContent(event.target.value)} placeholder="Partes, objeto, condições, cláusulas e assinaturas." /></Field>
      </div>
    </Modal>
  )
}
