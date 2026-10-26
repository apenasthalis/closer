import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useApi, useDebounced, useLookups } from '@/lib/hooks'
import { STAGES, STAGE_LABELS, TEMPERATURE_LABELS } from '@/lib/constants'
import { date, money } from '@/lib/format'
import type { Lead, Paginated } from '@/lib/types'
import { Badge, Button, EmptyState, ErrorState, Icon, LoadingState, Pagination, Panel, SearchInput, Select } from '@/components/ui'
import { LeadFormModal } from '@/components/LeadFormModal'

const stageTone: Record<string, string> = {
  lead_agendado: 'blue',
  fup: 'green',
  remarcado: 'purple',
  negociacao: 'amber',
  fechado: 'brand',
  loss: 'rose',
  mal_qualificado: 'neutral',
}

export function LeadsPage() {
  const navigate = useNavigate()
  const { niches, users } = useLookups()
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounced(search)
  const [stage, setStage] = useState('')
  const [nicheId, setNicheId] = useState('')
  const [temperature, setTemperature] = useState('')
  const [closerId, setCloserId] = useState('')
  const [sort, setSort] = useState('recent')
  const [page, setPage] = useState(1)
  const [newLead, setNewLead] = useState(false)

  const { data, loading, error, reload } = useApi<Paginated<Lead>>('/leads', {
    q: debouncedSearch || undefined,
    stage: stage || undefined,
    niche_id: nicheId || undefined,
    temperature: temperature || undefined,
    closer_id: closerId || undefined,
    sort,
    page,
    per_page: 20,
  })

  const leads = data?.data || []
  const clearFilters = () => {
    setSearch('')
    setStage('')
    setNicheId('')
    setTemperature('')
    setCloserId('')
    setSort('recent')
    setPage(1)
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <SearchInput value={search} onChange={(value) => { setSearch(value); setPage(1) }} placeholder="Nome, empresa, e-mail ou telefone..." />
          <Select value={stage} onChange={(event) => { setStage(event.target.value); setPage(1) }} style={{ width: 160 }}>
            <option value="">Todas as etapas</option>
            {STAGES.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
          </Select>
          <Select value={nicheId} onChange={(event) => { setNicheId(event.target.value); setPage(1) }} style={{ width: 155 }}>
            <option value="">Todos os nichos</option>
            {niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}
          </Select>
          <Select value={temperature} onChange={(event) => { setTemperature(event.target.value); setPage(1) }} style={{ width: 125 }}>
            <option value="">Temperatura</option>
            <option value="hot">Quente</option>
            <option value="warm">Morno</option>
            <option value="cold">Frio</option>
          </Select>
          <Select value={closerId} onChange={(event) => { setCloserId(event.target.value); setPage(1) }} style={{ width: 150 }}>
            <option value="">Closer</option>
            {users.filter((user) => user.role !== 'sdr').map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
          </Select>
          <Select value={sort} onChange={(event) => setSort(event.target.value)} style={{ width: 150 }}>
            <option value="recent">Mais recentes</option>
            <option value="priority">Maior prioridade</option>
            <option value="next_action">Próxima ação</option>
            <option value="name">Nome A-Z</option>
          </Select>
        </div>
        <div className="page-toolbar-right">
          {search || stage || nicheId || temperature || closerId ? <Button variant="ghost" size="sm" onClick={clearFilters}>Limpar filtros</Button> : null}
          <Button variant="primary" icon="plus" onClick={() => setNewLead(true)}>Novo lead</Button>
        </div>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !leads.length ? (
        <Panel>
          <EmptyState icon="leads" title="Nenhum lead encontrado" description="Ajuste os filtros ou cadastre uma nova oportunidade." action={<Button variant="primary" icon="plus" onClick={() => setNewLead(true)}>Cadastrar lead</Button>} />
        </Panel>
      ) : (
        <>
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Lead</th>
                  <th>Nicho</th>
                  <th>Etapa</th>
                  <th>Temperatura</th>
                  <th>Closer</th>
                  <th className="text-right">Valor</th>
                  <th>Próxima ação</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {leads.map((lead) => (
                  <tr key={lead.id} className="row-link" onClick={() => navigate(`/leads/${lead.id}`)}>
                    <td>
                      <div className="cell-main">{lead.name}</div>
                      <div className="cell-sub">{lead.company || lead.email || lead.phone || 'Sem empresa'}</div>
                    </td>
                    <td>{lead.niche ? <span><span className="niche-dot" style={{ background: lead.niche.color || 'var(--brand)' }} />{lead.niche.name}</span> : <span className="muted">—</span>}</td>
                    <td><Badge tone={stageTone[lead.stage] || 'neutral'}>{STAGE_LABELS[lead.stage] || lead.stage}</Badge></td>
                    <td><span className="stage-chip" style={{ '--chip-color': lead.temperature === 'hot' ? 'var(--c5)' : lead.temperature === 'warm' ? 'var(--c4)' : 'var(--muted)' } as React.CSSProperties}>{TEMPERATURE_LABELS[lead.temperature] || lead.temperature}</span></td>
                    <td>{lead.closer?.name || <span className="muted">—</span>}</td>
                    <td className="text-right mono">{money(lead.valor_negociado || lead.ticket_fechado || lead.ticket_estimado)}</td>
                    <td>{lead.next_action_at ? date(lead.next_action_at) : <span className="muted">—</span>}</td>
                    <td onClick={(event) => event.stopPropagation()}><div className="actions"><button className="icon-btn" aria-label="Abrir ficha" onClick={() => navigate(`/leads/${lead.id}`)}><Icon name="arrowRight" size={15} /></button></div></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data?.current_page || 1} lastPage={data?.last_page || 1} total={data?.total || leads.length} onChange={setPage} />
        </>
      )}

      <LeadFormModal open={newLead} onClose={() => setNewLead(false)} onSaved={() => { setNewLead(false); reload() }} />
    </div>
  )
}
