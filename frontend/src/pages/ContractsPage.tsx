import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, apiMessage, download } from '@/lib/api'
import { useApi, useLeadOptions } from '@/lib/hooks'
import { CONTRACT_STATUS } from '@/lib/constants'
import { date, dateTime, money } from '@/lib/format'
import type { Contract, Paginated } from '@/lib/types'
import {
  Badge, Button, EmptyState, ErrorState, IconButton, LoadingState, Modal, Pagination,
  Select, useToast,
} from '@/components/ui'
import { ContractFormModal } from '@/components/ContractFormModal'

export function ContractsPage() {
  const { notify } = useToast()
  const leads = useLeadOptions()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [pickLead, setPickLead] = useState(false)
  const [selectedLead, setSelectedLead] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [viewing, setViewing] = useState<Contract | null>(null)

  const { data, loading, error, reload } = useApi<Paginated<Contract>>('/contracts', { status: status || undefined, page })
  const contracts = data?.data || []
  const selectedLeadName = leads.find((lead) => lead.id === selectedLead)?.name

  const updateStatus = async (contract: Contract, nextStatus: string) => {
    try {
      await api.patch(`/contracts/${contract.id}/status`, { status: nextStatus })
      notify('Status do contrato atualizado.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <Select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }} style={{ width: 190 }}>
            <option value="">Todos os status</option>
            {Object.entries(CONTRACT_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </Select>
        </div>
        <Button variant="primary" icon="plus" onClick={() => { setSelectedLead(''); setPickLead(true) }}>Novo contrato</Button>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !contracts.length ? (
        <div className="panel panel-padded">
          <EmptyState icon="contracts" title="Nenhum contrato" description="Gere um contrato a partir de uma proposta ou crie manualmente vinculado ao lead." action={<Button onClick={() => setPickLead(true)}>Novo contrato</Button>} />
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table className="table">
              <thead><tr><th>Número</th><th>Título</th><th>Lead</th><th>Proposta</th><th>Status</th><th>Assinatura</th><th>Vigência</th><th className="text-right">Total</th><th /></tr></thead>
              <tbody>
                {contracts.map((contract) => (
                  <tr key={contract.id}>
                    <td className="mono">{contract.number}</td>
                    <td className="cell-main"><button className="link-button" onClick={() => setViewing(contract)}>{contract.title}</button></td>
                    <td>{contract.lead ? <Link to={`/leads/${contract.lead.id}`}>{contract.lead.name}</Link> : '—'}</td>
                    <td className="mono">{contract.proposal?.number || '—'}</td>
                    <td>
                      <Select value={contract.status} onChange={(event) => updateStatus(contract, event.target.value)} style={{ width: 175, padding: '5px 8px', fontSize: 11 }}>
                        {Object.entries(CONTRACT_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                      </Select>
                    </td>
                    <td>{dateTime(contract.signed_at)}</td>
                    <td>{contract.start_date ? `${date(contract.start_date)}${contract.end_date ? ` — ${date(contract.end_date)}` : ''}` : '—'}</td>
                    <td className="text-right mono">{money(contract.total)}</td>
                    <td><div className="actions"><IconButton icon="download" label="Baixar PDF" onClick={() => download(`/contracts/${contract.id}/pdf`, `contrato-${contract.number}.pdf`).catch((requestError) => notify(apiMessage(requestError), 'error'))} /><IconButton icon="eye" label="Ver contrato" onClick={() => setViewing(contract)} /></div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data?.current_page || 1} lastPage={data?.last_page || 1} total={data?.total || contracts.length} onChange={setPage} />
        </>
      )}

      <Modal open={pickLead} onClose={() => setPickLead(false)} title="Novo contrato" subtitle="Selecione o lead vinculado ao contrato." footer={<><Button onClick={() => setPickLead(false)}>Cancelar</Button><Button variant="primary" disabled={!selectedLead} onClick={() => { setPickLead(false); setFormOpen(true) }}>Continuar</Button></>}>
        <Select value={selectedLead} onChange={(event) => setSelectedLead(event.target.value)}>
          <option value="">Selecione um lead</option>
          {leads.map((lead) => <option key={lead.id} value={lead.id}>{lead.name}{lead.company ? ` · ${lead.company}` : ''}</option>)}
        </Select>
      </Modal>

      <ContractFormModal open={formOpen} leadId={selectedLead} leadName={selectedLeadName} onClose={() => setFormOpen(false)} onSaved={() => { setFormOpen(false); reload() }} />

      <Modal open={Boolean(viewing)} onClose={() => setViewing(null)} title={viewing?.title || 'Contrato'} subtitle={viewing ? `${viewing.number} · ${CONTRACT_STATUS[viewing.status]}` : undefined} size="lg" footer={<><Button onClick={() => setViewing(null)}>Fechar</Button>{viewing ? <Button icon="download" onClick={() => download(`/contracts/${viewing.id}/pdf`, `contrato-${viewing.number}.pdf`).catch((requestError) => notify(apiMessage(requestError), 'error'))}>Baixar PDF</Button> : null}</>}>
        {viewing ? (
          <div>
            <div className="grid-3">
              <div className="mini-stat"><span>Lead</span><b>{viewing.lead?.name || '—'}</b></div>
              <div className="mini-stat"><span>Total</span><b>{money(viewing.total)}</b></div>
              <div className="mini-stat"><span>Status</span><b><Badge tone={viewing.status === 'active' || viewing.status === 'signed' ? 'brand' : 'neutral'}>{CONTRACT_STATUS[viewing.status]}</Badge></b></div>
            </div>
            <div className="contract-content">{viewing.content || 'Sem conteúdo registrado.'}</div>
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
