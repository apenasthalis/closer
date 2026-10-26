import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, apiMessage, download } from '@/lib/api'
import { useApi, useDebounced, useLeadOptions } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { PROPOSAL_STATUS } from '@/lib/constants'
import { date, money } from '@/lib/format'
import type { Paginated, Proposal } from '@/lib/types'
import {
  Badge, Button, ConfirmDialog, EmptyState, ErrorState, IconButton, LoadingState, Modal,
  Pagination, SearchInput, Select, useToast,
} from '@/components/ui'
import { ProposalFormModal } from '@/components/ProposalFormModal'

function currencyText(value: unknown) {
  return money(value)
}

export function ProposalsPage() {
  const { user } = useAuth()
  const { notify } = useToast()
  const leads = useLeadOptions()
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounced(search)
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Proposal | null>(null)
  const [pickLead, setPickLead] = useState(false)
  const [selectedLead, setSelectedLead] = useState('')
  const [viewing, setViewing] = useState<Proposal | null>(null)
  const [deleting, setDeleting] = useState<Proposal | null>(null)
  const [busy, setBusy] = useState(false)

  const { data, loading, error, reload } = useApi<Paginated<Proposal>>('/proposals', {
    q: debouncedSearch || undefined,
    status: status || undefined,
    page,
  })
  const proposals = data?.data || []
  const leadName = leads.find((lead) => lead.id === selectedLead)?.name

  const startNew = () => {
    if (!leads.length) {
      notify('Cadastre um lead antes de criar uma proposta.', 'error')
      return
    }
    setSelectedLead('')
    setPickLead(true)
  }

  const changeStatus = async (proposal: Proposal, nextStatus: string) => {
    try {
      await api.patch(`/proposals/${proposal.id}/status`, { status: nextStatus })
      notify('Status da proposta atualizado.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const createContract = async (proposal: Proposal) => {
    try {
      await api.post(`/proposals/${proposal.id}/contract`)
      notify('Contrato gerado. Acesse Contratos para acompanhar.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const remove = async () => {
    if (!deleting) return
    setBusy(true)
    try {
      await api.delete(`/proposals/${deleting.id}`)
      notify('Proposta arquivada.')
      setDeleting(null)
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <SearchInput value={search} onChange={(value) => { setSearch(value); setPage(1) }} placeholder="Número ou título..." />
          <Select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }} style={{ width: 160 }}>
            <option value="">Todos os status</option>
            {Object.entries(PROPOSAL_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </Select>
        </div>
        <Button variant="primary" icon="plus" onClick={startNew}>Nova proposta</Button>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !proposals.length ? (
        <div className="panel panel-padded">
          <EmptyState icon="proposals" title="Nenhuma proposta" description="Crie propostas com itens, condições e PDF gerado no backend." action={<Button onClick={startNew}>Nova proposta</Button>} />
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table className="table">
              <thead><tr><th>Número</th><th>Título</th><th>Lead</th><th>Status</th><th>Validade</th><th>Versão</th><th className="text-right">Total</th><th /></tr></thead>
              <tbody>
                {proposals.map((proposal) => (
                  <tr key={proposal.id}>
                    <td className="mono">{proposal.number}</td>
                    <td className="cell-main"><button className="link-button" onClick={() => setViewing(proposal)}>{proposal.title}</button></td>
                    <td>{proposal.lead ? <Link to={`/leads/${proposal.lead.id}`}>{proposal.lead.name}</Link> : '—'}</td>
                    <td>
                      <Select value={proposal.status} onChange={(event) => changeStatus(proposal, event.target.value)} style={{ width: 130, padding: '5px 8px', fontSize: 11 }} disabled={proposal.status === 'rejected'}>
                        {Object.entries(PROPOSAL_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                      </Select>
                    </td>
                    <td>{date(proposal.valid_until)}</td>
                    <td>v{proposal.version}</td>
                    <td className="text-right mono">{currencyText(proposal.total)}</td>
                    <td>
                      <div className="actions">
                        <IconButton icon="download" label="Baixar PDF" onClick={() => download(`/proposals/${proposal.id}/pdf`, `proposta-${proposal.number}.pdf`).catch((requestError) => notify(apiMessage(requestError), 'error'))} />
                        <IconButton icon="edit" label="Editar proposta" onClick={() => { setEditing(proposal); setFormOpen(true) }} />
                        {['sent', 'accepted'].includes(proposal.status) ? <button className="btn btn-sm btn-soft" onClick={() => createContract(proposal)}>Contrato</button> : null}
                        {proposal.status === 'draft' || user?.role === 'admin' ? <IconButton icon="trash" label="Arquivar proposta" onClick={() => setDeleting(proposal)} /> : null}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data?.current_page || 1} lastPage={data?.last_page || 1} total={data?.total || proposals.length} onChange={setPage} />
        </>
      )}

      <Modal open={pickLead} onClose={() => setPickLead(false)} title="Nova proposta" subtitle="Selecione o lead que receberá a proposta." footer={<><Button onClick={() => setPickLead(false)}>Cancelar</Button><Button variant="primary" disabled={!selectedLead} onClick={() => { setPickLead(false); setEditing(null); setFormOpen(true) }}>Continuar</Button></>}>
        <Select value={selectedLead} onChange={(event) => setSelectedLead(event.target.value)}>
          <option value="">Selecione um lead</option>
          {leads.map((lead) => <option key={lead.id} value={lead.id}>{lead.name}{lead.company ? ` · ${lead.company}` : ''}</option>)}
        </Select>
      </Modal>

      <ProposalFormModal
        open={formOpen}
        onClose={() => { setFormOpen(false); setEditing(null) }}
        leadId={editing?.lead_id || selectedLead}
        leadName={editing?.lead?.name || leadName}
        proposal={editing}
        onSaved={() => { setFormOpen(false); setEditing(null); reload() }}
      />

      <Modal open={Boolean(viewing)} onClose={() => setViewing(null)} title={viewing?.title || 'Proposta'} subtitle={viewing ? `${viewing.number} · v${viewing.version} · ${PROPOSAL_STATUS[viewing.status]}` : undefined} size="lg" footer={<><Button onClick={() => setViewing(null)}>Fechar</Button>{viewing ? <Button icon="download" onClick={() => download(`/proposals/${viewing.id}/pdf`, `proposta-${viewing.number}.pdf`).catch((requestError) => notify(apiMessage(requestError), 'error'))}>PDF</Button> : null}</>}>
        {viewing ? (
          <div>
            <div className="proposal-view-head">
              <div><span>Lead</span><b>{viewing.lead?.name || '—'}</b></div>
              <div><span>Validade</span><b>{date(viewing.valid_until)}</b></div>
              <div><span>Status</span><b>{PROPOSAL_STATUS[viewing.status]}</b></div>
              <div><span>Total</span><b>{money(viewing.total)}</b></div>
            </div>
            <div className="table-wrap" style={{ marginTop: 14 }}>
              <table className="table">
                <thead><tr><th>Descrição</th><th>Qtd.</th><th className="text-right">Unitário</th><th className="text-right">Total</th></tr></thead>
                <tbody>{viewing.items?.map((item, index) => <tr key={index}><td>{item.description}</td><td>{item.quantity}</td><td className="text-right mono">{money(item.unit_price)}</td><td className="text-right mono">{money(Number(item.quantity) * Number(item.unit_price))}</td></tr>)}</tbody>
              </table>
            </div>
            <div className="proposal-totals" style={{ marginTop: 12 }}>
              <span>Subtotal <b>{money(viewing.subtotal)}</b></span>
              <span>Desconto <b>- {money(viewing.discount)}</b></span>
              <span className="total">Total <b>{money(viewing.total)}</b></span>
            </div>
            {viewing.payment_terms ? <div className="callout mt-12"><strong>Pagamento:</strong> {viewing.payment_terms}</div> : null}
            {viewing.clauses?.length ? <div className="mt-16"><div className="section-label">Cláusulas</div>{viewing.clauses.map((clause, index) => <p className="clause-line" key={index}>{index + 1}. {clause}</p>)}</div> : null}
          </div>
        ) : null}
      </Modal>

      <ConfirmDialog open={Boolean(deleting)} title="Arquivar proposta" message={`Arquivar a proposta ${deleting?.number}? O PDF deixará de estar disponível.`} confirmLabel="Arquivar" loading={busy} onClose={() => setDeleting(null)} onConfirm={remove} />
    </div>
  )
}
