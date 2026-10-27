import { useState } from 'react'
import { api, apiMessage, download } from '@/lib/api'
import { useApi, useLookups } from '@/lib/hooks'
import { date, dateTime, endOfMonth, money, num, pct, periodLabel, startOfMonth, startOfWeek } from '@/lib/format'
import type { Dashboard, LossAnalysis, ReportRun } from '@/lib/types'
import { Badge, Button, EmptyState, ErrorState, Field, Icon, Input, LoadingState, Panel, Select, Stat, useToast } from '@/components/ui'

interface ReportResponse {
  data: {
    generated_at: string
    dashboard: Dashboard
    losses: LossAnalysis
    limitations: string[]
  }
}

export function ReportsPage() {
  const { notify } = useToast()
  const { niches, users } = useLookups()
  const [from, setFrom] = useState(startOfMonth())
  const [to, setTo] = useState(endOfMonth())
  const [nicheId, setNicheId] = useState('')
  const [closerId, setCloserId] = useState('')
  const [weekStart, setWeekStart] = useState(startOfWeek())
  const [generating, setGenerating] = useState(false)
  const params = { from: from || undefined, to: to || undefined, niche_id: nicheId || undefined, closer_id: closerId || undefined }
  const { data, loading, error, reload } = useApi<ReportResponse>('/reports', params)
  const runsApi = useApi<{ data: { data: ReportRun[] } }>('/reports/runs')
  const report = data?.data
  const dashboard = report?.dashboard
  const losses = report?.losses
  const runs = runsApi.data?.data?.data || []

  const exportCsv = async (type: string) => {
    const query = new URLSearchParams()
    if (from) query.set('from', from)
    if (to) query.set('to', to)
    if (nicheId) query.set('niche_id', nicheId)
    if (closerId) query.set('closer_id', closerId)
    const labels: Record<string, string> = { leads: 'leads', calls: 'calls', payments: 'pagamentos', commissions: 'comissoes' }
    try {
      await download(`/reports/export/${type}?${query.toString()}`, `${labels[type] || type}-${from}-${to}.csv`)
      notify('Exportação gerada.')
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const generateWeekly = async () => {
    setGenerating(true)
    try {
      const response = await api.post('/reports/weekly', { week_start: weekStart })
      notify(response.data?.message || 'Relatório semanal gerado.')
      runsApi.reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setGenerating(false)
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <label className="pill" style={{ gap: 6 }}><Icon name="calendar" size={13} /><span>De</span><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="bare-input" style={{ width: 100 }} /></label>
          <label className="pill" style={{ gap: 6 }}><span>até</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="bare-input" style={{ width: 100 }} /></label>
          <Select value={nicheId} onChange={(event) => setNicheId(event.target.value)} style={{ width: 155 }}><option value="">Todos os nichos</option>{niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}</Select>
          <Select value={closerId} onChange={(event) => setCloserId(event.target.value)} style={{ width: 155 }}><option value="">Todos os closers</option>{users.filter((item) => item.role !== 'sdr').map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select>
        </div>
        <Button icon="refresh" onClick={reload}>Atualizar</Button>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !report ? <LoadingState /> : report && dashboard && losses ? (
        <>
          <div className="section-title"><div><span className="eyebrow">Relatório gerencial</span><h2>Performance do período</h2><p>{periodLabel(dashboard.period.from, dashboard.period.to)} · gerado em {dateTime(report.generated_at)}</p></div></div>

          <div className="kpi-row">
            <Stat label="Receita contratada" value={money(dashboard.revenue.contracted, true)} caption={`${num(dashboard.closed)} fechamento(s)`} icon="money" tone="green" />
            <Stat label="Receita recebida" value={money(dashboard.revenue.received, true)} caption={`Pendente ${money(dashboard.revenue.pending, true)}`} icon="payments" tone="blue" />
            <Stat label="Conversão" value={pct(dashboard.conversion_rate)} caption={`${num(dashboard.leads_created)} leads criados`} icon="target" tone="purple" />
            <Stat label="Ticket médio" value={money(dashboard.ticket_medio, true)} caption={`Ciclo ${dashboard.cycle_days ?? '—'} dias`} icon="briefcase" tone="amber" />
            <Stat label="Perdas" value={num(losses.totals.loss + losses.totals.mal_qualificado)} caption={`${money(losses.totals.estimated_value, true)} estimados`} icon="trendingDown" tone="rose" />
          </div>

          <div className="grid-2">
            <Panel title="Performance por closer" subtitle="Mesmo recorte de período, nicho e closer." padded={false}>
              <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
                <table className="table">
                  <thead><tr><th>Closer</th><th>Leads</th><th>Fechados</th><th>Conversão</th><th className="text-right">Receita</th><th className="text-right">Comissão</th></tr></thead>
                  <tbody>
                    {dashboard.ranking.map((row) => (
                      <tr key={row.id}><td className="cell-main">{row.name}</td><td>{num(row.leads_created)}</td><td>{num(row.closed)}</td><td>{pct(row.conversion)}</td><td className="text-right mono">{money(row.revenue)}</td><td className="text-right mono">{money(row.commission)}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Panel>
            <Panel title="Exportações CSV" subtitle="Somente dados acessíveis ao seu perfil, com proteção contra fórmulas.">
              <div className="export-grid">
                <button className="export-card" onClick={() => exportCsv('leads')}><Icon name="leads" size={18} /><span><b>Leads</b><small>Cadastro e etapas</small></span><Icon name="download" size={14} /></button>
                <button className="export-card" onClick={() => exportCsv('calls')}><Icon name="calls" size={18} /><span><b>Calls</b><small>Status e score</small></span><Icon name="download" size={14} /></button>
                <button className="export-card" onClick={() => exportCsv('payments')}><Icon name="payments" size={18} /><span><b>Pagamentos</b><small>Vencimentos e status</small></span><Icon name="download" size={14} /></button>
                <button className="export-card" onClick={() => exportCsv('commissions')}><Icon name="commissions" size={18} /><span><b>Comissões</b><small>Valores e aprovações</small></span><Icon name="download" size={14} /></button>
              </div>
            </Panel>
          </div>

          <div className="grid-2 mt-16">
            <Panel title="Por nicho" subtitle="Leads criados, fechamentos e receita no período." padded={false}>
              <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
                <table className="table">
                  <thead><tr><th>Nicho</th><th>Leads</th><th>Fechados</th><th>Conversão</th><th className="text-right">Receita</th></tr></thead>
                  <tbody>{dashboard.by_niche.map((row) => <tr key={row.id}><td><span className="niche-dot" style={{ background: row.color || 'var(--brand)' }} />{row.name}</td><td>{num(row.leads)}</td><td>{num(row.closed)}</td><td>{pct(row.conversion)}</td><td className="text-right mono">{money(row.revenue)}</td></tr>)}</tbody>
                </table>
              </div>
            </Panel>
            <Panel title="Motivos de perda" subtitle={losses.caveat}>
              <div className="section-label">Loss</div>
              {losses.loss_reasons.length ? losses.loss_reasons.slice(0, 5).map((reason) => (
                <div className="report-reason" key={reason.reason}><span className="truncate" title={reason.reason}>{reason.reason}</span><b>{reason.count}</b><span className="mono">{money(reason.value)}</span></div>
              )) : <p className="muted small">Nenhum loss no período.</p>}
              <div className="section-label mt-12">Desqualificados</div>
              {losses.disqualify_reasons.length ? losses.disqualify_reasons.slice(0, 5).map((reason) => (
                <div className="report-reason" key={reason.reason}><span className="truncate" title={reason.reason}>{reason.reason}</span><b>{reason.count}</b><span className="mono">{money(reason.value)}</span></div>
              )) : <p className="muted small">Nenhuma desqualificação no período.</p>}
            </Panel>
          </div>

          <div className="grid-main-side mt-16">
            <Panel title="Relatórios semanais" subtitle="O relatório é persistido e auditado. O envio por e-mail depende de SMTP configurado." padded={false}>
              {runs.length ? (
                <div className="table-wrap" style={{ border: 'none', borderRadius: 0 }}>
                  <table className="table">
                    <thead><tr><th>Período</th><th>Status</th><th>Gerado em</th><th>Receita</th><th>Fechados</th></tr></thead>
                    <tbody>
                      {runs.map((run) => (
                        <tr key={run.id}>
                          <td>{date(run.period_start)} — {date(run.period_end)}</td>
                          <td><Badge tone="brand">{run.status}</Badge></td>
                          <td>{dateTime(run.created_at)}</td>
                          <td className="mono">{money(run.metrics?.dashboard?.revenue?.contracted)}</td>
                          <td>{num(run.metrics?.dashboard?.closed)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : <EmptyState icon="reports" title="Nenhum relatório semanal" description="Gere o primeiro consolidado semanal." />}
            </Panel>
            <Panel title="Gerar relatório semanal" subtitle="O período considera segunda a domingo.">
              <Field label="Início da semana"><Input type="date" value={weekStart} onChange={(event) => setWeekStart(event.target.value)} /></Field>
              <Button variant="primary" icon="reports" className="btn-block mt-12" loading={generating} onClick={generateWeekly}>Gerar relatório</Button>
              <div className="callout mt-12">Não há envio automático de e-mail nesta versão. Nenhum disparo simulado é feito.</div>
              {report.limitations.length ? <div className="callout warn mt-8">{report.limitations.map((limitation) => <div key={limitation}>• {limitation}</div>)}</div> : null}
            </Panel>
          </div>
        </>
      ) : null}
    </div>
  )
}
