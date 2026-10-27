import { useState } from 'react'
import { useApi, useLookups } from '@/lib/hooks'
import { STAGES } from '@/lib/constants'
import { date, endOfMonth, money, num, pct, periodLabel, percentOf, startOfMonth, today } from '@/lib/format'
import type { Dashboard, LossAnalysis } from '@/lib/types'
import { ErrorState, Icon, LoadingState, Panel, Select, Stat } from '@/components/ui'

export function DashboardPage() {
  const [from, setFrom] = useState(startOfMonth())
  const [to, setTo] = useState(endOfMonth())
  const [nicheId, setNicheId] = useState('')
  const [companyId, setCompanyId] = useState('')
  const [closerId, setCloserId] = useState('')
  const { niches, users, companies } = useLookups()
  const params = { from: from || undefined, to: to || undefined, niche_id: nicheId || undefined, company_id: companyId || undefined, closer_id: closerId || undefined }
  const { data, loading, error, reload } = useApi<{ data: Dashboard }>('/dashboard', params)
  const { data: lossData } = useApi<{ data: LossAnalysis }>('/dashboard/losses', params)
  const dashboard = data?.data
  const losses = lossData?.data

  const byDay = dashboard?.meetings.by_day || []
  const maxMeeting = Math.max(1, ...byDay.map((day) => Math.max(day.completed, day.scheduled, day.no_show, day.cancelled)))
  const maxFunnel = Math.max(1, ...Object.values(dashboard?.conversion_by_stage || {}))

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <Icon name="calendar" size={13} />
            <span>De</span>
            <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} style={{ border: 'none', background: 'transparent', color: 'var(--text)', outline: 'none', width: 105 }} />
          </label>
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <span>até</span>
            <input type="date" value={to} onChange={(event) => setTo(event.target.value)} style={{ border: 'none', background: 'transparent', color: 'var(--text)', outline: 'none', width: 105 }} />
          </label>
          <Select value={nicheId} onChange={(event) => setNicheId(event.target.value)} style={{ width: 155 }}>
            <option value="">Todos os nichos</option>
            {niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}
          </Select>
          <Select value={companyId} onChange={(event) => setCompanyId(event.target.value)} style={{ width: 155 }}>
            <option value="">Todas as empresas</option>
            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
          </Select>
          <Select value={closerId} onChange={(event) => setCloserId(event.target.value)} style={{ width: 155 }}>
            <option value="">Todos os closers</option>
            {users.filter((user) => user.role !== 'sdr').map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
          </Select>
          {dashboard ? <span className="pill sm">{periodLabel(dashboard.period.from, dashboard.period.to)}</span> : null}
        </div>
        <button className="btn btn-secondary" onClick={reload}><Icon name="refresh" size={15} />Atualizar</button>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !dashboard ? <LoadingState /> : dashboard ? (
        <>
          <div className="kpi-row">
            <Stat label="Receita contratada" value={money(dashboard.revenue.contracted, true)} caption={`${num(dashboard.closed)} venda(s) no período`} icon="money" tone="green" />
            <Stat label="Receita recebida" value={money(dashboard.revenue.received, true)} caption={`Pendente: ${money(dashboard.revenue.pending, true)}`} icon="payments" tone="blue" />
            <Stat label="Ticket médio" value={money(dashboard.ticket_medio, true)} caption={`Previsto: ${money(dashboard.revenue.forecast, true)}`} icon="briefcase" tone="purple" />
            <Stat label="Conversão" value={pct(dashboard.conversion_rate)} caption={`${num(dashboard.leads_created)} lead(s) criado(s)`} icon="target" tone="amber" />
            <Stat label="Ciclo médio" value={dashboard.cycle_days !== null ? `${num(dashboard.cycle_days)} d` : '—'} caption={`${pct(dashboard.meetings.attendance_rate)} de comparecimento`} icon="clock" tone="rose" />
          </div>

          <div className="grid-main-side">
            <Panel title="Reuniões por dia" subtitle="Concluídas, agendadas, no-show e canceladas. Comparecimento considera apenas concluídas e no-show.">
              {byDay.length ? (
                <>
                  <div className="chart-bars" style={{ height: 190 }}>
                    {byDay.map((day) => (
                      <div className="chart-bar-group" key={day.date} title={`${date(day.date)}: ${day.completed} concluídas, ${day.scheduled} agendadas, ${day.no_show} no-show, ${day.cancelled} canceladas`}>
                        <div className="chart-bar-track" style={{ height: 145 }}>
                          <span className="chart-bar" style={{ height: `${Math.max(2, (day.completed / maxMeeting) * 100)}%` }} />
                          <span className="chart-bar alt" style={{ height: `${Math.max(2, (day.scheduled / maxMeeting) * 100)}%` }} />
                          <span className="chart-bar rose" style={{ height: `${Math.max(2, (day.no_show / maxMeeting) * 100)}%` }} />
                        </div>
                        <span className="chart-label">{date(day.date).slice(0, 5)}</span>
                      </div>
                    ))}
                  </div>
                  <div className="chart-legend"><span><span className="dot" style={{ background: 'var(--c1)' }} /> Concluídas</span><span><span className="dot" style={{ background: 'var(--c2)' }} /> Agendadas</span><span><span className="dot" style={{ background: 'var(--c5)' }} /> No-show</span></div>
                </>
              ) : <div className="empty" style={{ padding: '28px 10px' }}><Icon name="calls" size={22} /><p>Nenhuma call registrada no período.</p></div>}
            </Panel>

            <div className="stack">
              <Panel title="Caixa do período" subtitle="Contratado, recebido e pendente não são a mesma coisa.">
                <div className="kv"><span>Faturamento previsto</span><b>{money(dashboard.revenue.forecast)}</b></div>
                <div className="kv"><span>Contratado</span><b>{money(dashboard.revenue.contracted)}</b></div>
                <div className="kv"><span>Recebido</span><b className="text-brand">{money(dashboard.revenue.received)}</b></div>
                <div className="kv"><span>Pendente</span><b>{money(dashboard.revenue.pending)}</b></div>
                <div className="kv"><span>Vencido</span><b className="text-danger">{money(dashboard.revenue.overdue)}</b></div>
                <div className="kv"><span>Saldo devedor</span><b>{money(dashboard.revenue.debtor_balance)}</b></div>
              </Panel>
              <Panel title="Saúde do funil" subtitle="Quantidade de leads que alcançaram cada etapa no período.">
                {STAGES.map((stage) => (
                  <div className="bar-list-row" key={stage.value}>
                    <span className="name">{stage.label}</span>
                    <div className="bar-track"><div className="bar-fill" style={{ width: `${percentOf(dashboard.conversion_by_stage[stage.value] || 0, maxFunnel)}%`, background: stage.color }} /></div>
                    <span className="n">{num(dashboard.conversion_by_stage[stage.value] || 0)}</span>
                  </div>
                ))}
              </Panel>
            </div>
          </div>

          <Panel className="mt-16" title="Ranking de performance" subtitle="Receita, conversão, ciclo e comissão por closer." padded={false}>
            <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
              <table className="table">
                <thead><tr><th>Closer</th><th>Leads criados</th><th>Reuniões</th><th>Comparecimento</th><th>Fechados</th><th>Conversão</th><th className="text-right">Ticket médio</th><th className="text-right">Receita</th><th className="text-right">Comissão</th></tr></thead>
                <tbody>
                  {dashboard.ranking.length ? dashboard.ranking.map((row) => (
                    <tr key={row.id}>
                      <td className="cell-main">{row.name}</td>
                      <td>{num(row.leads_created)}</td>
                      <td>{num(row.meetings_completed)}</td>
                      <td>{pct(row.attendance_rate)}</td>
                      <td>{num(row.closed)}</td>
                      <td>{pct(row.conversion)}</td>
                      <td className="text-right mono">{money(row.average_ticket)}</td>
                      <td className="text-right mono">{money(row.revenue)}</td>
                      <td className="text-right mono">{money(row.commission)}</td>
                    </tr>
                  )) : <tr><td colSpan={9} className="muted">Nenhum closer com dados no período.</td></tr>}
                </tbody>
              </table>
            </div>
          </Panel>

          <div className="grid-2 mt-16">
            <Panel title="Por nicho" subtitle="Leads criados, fechamentos e receita contratada." padded={false}>
              <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
                <table className="table">
                  <thead><tr><th>Nicho</th><th>Leads</th><th>Fechados</th><th>Conversão</th><th className="text-right">Receita</th></tr></thead>
                  <tbody>
                    {dashboard.by_niche.length ? dashboard.by_niche.map((item) => (
                      <tr key={item.id}>
                        <td><span className="niche-dot" style={{ background: item.color || 'var(--brand)' }} />{item.name}</td>
                        <td>{num(item.leads)}</td>
                        <td>{num(item.closed)}</td>
                        <td>{pct(item.conversion)}</td>
                        <td className="text-right mono">{money(item.revenue)}</td>
                      </tr>
                    )) : <tr><td colSpan={5} className="muted">Sem dados por nicho.</td></tr>}
                  </tbody>
                </table>
              </div>
            </Panel>

            <Panel title="Análise de perdas" subtitle={losses?.caveat || 'Motivos descritivos de loss e desqualificação.'}>
              {losses ? (
                <>
                  <div className="kpi-row three" style={{ marginBottom: 14 }}>
                    <Stat label="Loss" value={num(losses.totals.loss)} caption="no período" />
                    <Stat label="Desqualificados" value={num(losses.totals.mal_qualificado)} caption="no período" />
                    <Stat label="Valor estimado" value={money(losses.totals.estimated_value, true)} caption="ticket estimado" />
                  </div>
                  <div className="section-label">Principais motivos de loss</div>
                  {losses.loss_reasons.length ? losses.loss_reasons.slice(0, 5).map((reason) => (
                    <div className="bar-list-row" key={reason.reason} style={{ gridTemplateColumns: '1fr 90px 36px' }}>
                      <span className="name" title={reason.reason}>{reason.reason}</span>
                      <div className="bar-track"><div className="bar-fill" style={{ width: `${percentOf(reason.count, Math.max(1, ...losses.loss_reasons.map((item) => item.count)))}%`, background: 'var(--c5)' }} /></div>
                      <span className="n">{reason.count}</span>
                    </div>
                  )) : <p className="muted small">Nenhum loss no período.</p>}
                </>
              ) : <LoadingState label="Calculando perdas..." />}
            </Panel>
          </div>

          <div className="callout mt-16">
            <strong>Como os números são calculados:</strong> receita contratada soma o ticket fechado de leads fechados no período; recebido considera pagamentos com status pago e data de pagamento no período; pendente é o saldo de pagamentos em aberto. Conversão é fechados ÷ leads criados. Comparecimento é concluídas ÷ (concluídas + no-show).
          </div>
        </>
      ) : null}
    </div>
  )
}
