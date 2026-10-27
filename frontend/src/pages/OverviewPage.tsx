import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useApi, useLookups } from '@/lib/hooks'
import type { Dashboard } from '@/lib/types'
import { STAGES } from '@/lib/constants'
import {
  date, endOfMonth, money, num, pct, periodLabel, percentOf, startOfMonth, today,
} from '@/lib/format'
import { ErrorState, Icon, LoadingState, Panel, Select, Stat } from '@/components/ui'

const palette = ['var(--c1)', 'var(--c2)', 'var(--c3)', 'var(--c4)', 'var(--c5)', '#58a6a6', '#b2a35d']

function conic(items: Array<{ value: number }>) {
  const total = items.reduce((sum, item) => sum + item.value, 0)
  if (!total) return 'conic-gradient(var(--panel2) 0 100%)'
  let current = 0
  const stops = items.map((item, index) => {
    const start = current
    current += (item.value / total) * 100
    return `${palette[index % palette.length]} ${start}% ${current}%`
  })
  return `conic-gradient(${stops.join(', ')})`
}

export function OverviewPage() {
  const [from, setFrom] = useState(startOfMonth())
  const [to, setTo] = useState(endOfMonth())
  const [companyId, setCompanyId] = useState('')
  const { companies } = useLookups()
  const { data, loading, error, reload } = useApi<{ data: Dashboard }>('/dashboard', {
    from: from || undefined,
    to: to || undefined,
    company_id: companyId || undefined,
  })
  const dashboard = data?.data

  const stageRows = useMemo(() => {
    if (!dashboard) return []
    return STAGES.map((stage) => ({ ...stage, count: dashboard.stage_totals?.[stage.value]?.count || 0, value: dashboard.stage_totals?.[stage.value]?.value || 0 }))
  }, [dashboard])

  const maxStage = Math.max(1, ...stageRows.map((stage) => stage.count))
  const nicheRevenue = (dashboard?.by_niche || []).filter((item) => item.revenue > 0)
  const nicheLeads = (dashboard?.by_niche || []).filter((item) => item.leads > 0)
  const totalNicheRevenue = nicheRevenue.reduce((sum, item) => sum + item.revenue, 0)
  const totalNicheLeads = nicheLeads.reduce((sum, item) => sum + item.leads, 0)

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <span className="pill strong"><Icon name="calendar" size={14} />{dashboard ? periodLabel(dashboard.period.from, dashboard.period.to) : periodLabel(from, to)}</span>
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <Icon name="calendar" size={13} />
            <span>De</span>
            <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="bare-input" style={{ width: 105 }} />
          </label>
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <span>até</span>
            <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="bare-input" style={{ width: 105 }} />
          </label>
          <Select value={companyId} onChange={(event) => setCompanyId(event.target.value)} style={{ width: 155 }}>
            <option value="">Todas as empresas</option>
            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
          </Select>
        </div>
        <div className="page-toolbar-right">
          <button className="btn btn-primary" onClick={reload}><Icon name="refresh" size={15} />Atualizar</button>
        </div>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !dashboard ? <LoadingState /> : dashboard ? (
        <>
          <div className="kpi-row">
            <Stat label="Faturamento contratado" value={money(dashboard.revenue.contracted, true)} caption={`${num(dashboard.closed)} venda(s) no período`} icon="money" tone="green" />
            <Stat label="Ticket médio" value={money(dashboard.ticket_medio, true)} caption="Receita contratada / vendas" icon="chart" tone="blue" />
            <Stat label="Comissão gerada" value={money(dashboard.revenue.commissions, true)} caption="Aprovações e regras no backend" icon="commissions" tone="purple" />
            <Stat label="Leads no período" value={num(dashboard.leads_created)} caption={`${num(dashboard.leads_total)} na base acessível`} icon="leads" tone="amber" />
            <Stat label="Taxa de conversão" value={pct(dashboard.conversion_rate)} caption="Fechamentos / leads criados" icon="target" tone="rose" />
          </div>

          <div className="grid-2">
            <Panel title="Faturamento por nicho" subtitle="Receita contratada no período, sem dupla contagem.">
              <div className="donut-wrap">
                <div className="donut" style={{ background: conic(nicheRevenue.map((item) => ({ value: item.revenue }))) }}>
                  <div className="donut-center"><b>{money(totalNicheRevenue, true)}</b><span>contratado</span></div>
                </div>
                <div className="legend">
                  {nicheRevenue.length ? nicheRevenue.slice(0, 6).map((item, index) => (
                    <div className="legend-row" key={item.id}>
                      <span className="k"><span className="dot" style={{ background: palette[index % palette.length] }} /><span>{item.name}</span></span>
                      <span className="v">{money(item.revenue, true)}</span>
                    </div>
                  )) : <div className="muted small">Sem faturamento fechado no período.</div>}
                  <div className="legend-total">TOTAL<b>{money(totalNicheRevenue)}</b></div>
                </div>
              </div>
            </Panel>

            <Panel title="Leads por nicho" subtitle="Volume de leads criados no período.">
              <div className="donut-wrap">
                <div className="donut" style={{ background: conic(nicheLeads.map((item) => ({ value: item.leads }))) }}>
                  <div className="donut-center"><b>{num(totalNicheLeads)}</b><span>leads</span></div>
                </div>
                <div className="legend">
                  {nicheLeads.length ? nicheLeads.slice(0, 6).map((item, index) => (
                    <div className="legend-row" key={item.id}>
                      <span className="k"><span className="dot" style={{ background: palette[index % palette.length] }} /><span>{item.name}</span></span>
                      <span className="v">{num(item.leads)}</span>
                    </div>
                  )) : <div className="muted small">Sem leads criados no período.</div>}
                  <div className="legend-total">TOTAL<b>{num(totalNicheLeads)}</b></div>
                </div>
              </div>
            </Panel>
          </div>

          <div className="grid-main-side mt-16">
            <Panel title="Pipeline por etapa" subtitle="Distribuição atual dos leads na base acessível." action={<Link to="/pipeline" className="btn btn-sm btn-ghost">Abrir pipeline <Icon name="arrowRight" size={13} /></Link>}>
              {stageRows.map((stage) => (
                <div className="bar-list-row" key={stage.value}>
                  <span className="name">{stage.label}</span>
                  <div className="bar-track"><div className="bar-fill" style={{ width: `${percentOf(stage.count, maxStage)}%`, background: stage.color }} /></div>
                  <span className="n">{num(stage.count)}</span>
                </div>
              ))}
            </Panel>

            <Panel title="Reuniões do dia" subtitle="Leads com reunião marcada para hoje e o resultado no pipeline.">
              <div className="kpi-row three" style={{ marginBottom: 10 }}>
                <Stat label="Reuniões hoje" value={num(dashboard.meetings_today.total)} caption="com reunião marcada para hoje" icon="clock" tone="blue" />
                <Stat label="Fechadas" value={num(dashboard.meetings_today.closed)} caption="estão como Fechado" icon="checkCircle" tone="green" />
                <Stat label="Loss" value={num(dashboard.meetings_today.loss)} caption="estão como Loss" icon="alert" tone="rose" />
              </div>
              {dashboard.meetings_today.total > 0 ? (
                <div className="callout mt-8">Marque a data e a hora da reunião na criação ou edição de um lead para aparecer aqui.</div>
              ) : null}
            </Panel>

            <Panel title="Financeiro do período" subtitle="Valores claramente separados por estágio do caixa.">
              <div className="kv"><span>Faturamento previsto</span><b>{money(dashboard.revenue.forecast)}</b></div>
              <div className="kv"><span>Receita contratada</span><b>{money(dashboard.revenue.contracted)}</b></div>
              <div className="kv"><span>Recebido</span><b className="text-brand">{money(dashboard.revenue.received)}</b></div>
              <div className="kv"><span>Pendente</span><b>{money(dashboard.revenue.pending)}</b></div>
              <div className="kv"><span>Vencido</span><b className="text-danger">{money(dashboard.revenue.overdue)}</b></div>
              <div className="kv"><span>Saldo devedor</span><b>{money(dashboard.revenue.debtor_balance)}</b></div>
              <div className="divider" />
              <div className="kv"><span>Reuniões concluídas</span><b>{num(dashboard.meetings.completed)} / {num(dashboard.meetings.total)}</b></div>
              <div className="kv"><span>Comparecimento</span><b>{pct(dashboard.meetings.attendance_rate)}</b></div>
              <Link to="/dashboard" className="btn btn-soft btn-block mt-12">Ver dashboard completo <Icon name="arrowRight" size={13} /></Link>
            </Panel>
          </div>

          <Panel className="mt-16" title="Ranking de closers" subtitle="Receita, conversão e comissão gerada no período." padded={false}>
            <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
              <table className="table">
                <thead>
                  <tr><th>Closer</th><th>Reuniões</th><th>Comparecimento</th><th>Fechados</th><th>Conversão</th><th className="text-right">Receita</th><th className="text-right">Comissão</th></tr>
                </thead>
                <tbody>
                  {dashboard.ranking.length ? dashboard.ranking.map((row) => (
                    <tr key={row.id}>
                      <td className="cell-main">{row.name}</td>
                      <td>{num(row.meetings_completed)}</td>
                      <td>{pct(row.attendance_rate)}</td>
                      <td>{num(row.closed)}</td>
                      <td>{pct(row.conversion)}</td>
                      <td className="text-right mono">{money(row.revenue)}</td>
                      <td className="text-right mono">{money(row.commission)}</td>
                    </tr>
                  )) : <tr><td colSpan={7} className="muted">Nenhum closer com dados no período.</td></tr>}
                </tbody>
              </table>
            </div>
          </Panel>
        </>
      ) : null}
    </div>
  )
}
