import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, apiMessage } from '@/lib/api'
import { useApi, useLookups } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { COMMISSION_STATUS } from '@/lib/constants'
import { date, endOfMonth, money, startOfMonth, dateTime } from '@/lib/format'
import type { Commission, CommissionRule } from '@/lib/types'
import {
  Badge, Button, ConfirmDialog, EmptyState, ErrorState, Field, Icon, IconButton, Input,
  LoadingState, Modal, Select, Stat, Tabs, Textarea, useToast,
} from '@/components/ui'

interface CommissionResponse {
  data: Commission[]
  summary: Record<string, number>
}

const scopeLabels: Record<string, string> = { global: 'Global', user: 'Usuário', team: 'Equipe', niche: 'Nicho' }

export function CommissionsPage() {
  const { user } = useAuth()
  const { notify } = useToast()
  const { users, teams, niches, companies } = useLookups()
  const [tab, setTab] = useState('commissions')
  const [from, setFrom] = useState(startOfMonth())
  const [to, setTo] = useState(endOfMonth())
  const [companyId, setCompanyId] = useState('')
  const [status, setStatus] = useState('')
  const [calculating, setCalculating] = useState(false)
  const [reversing, setReversing] = useState<Commission | null>(null)
  const [reverseReason, setReverseReason] = useState('')
  const [ruleOpen, setRuleOpen] = useState(false)
  const [editingRule, setEditingRule] = useState<CommissionRule | null>(null)
  const [ruleName, setRuleName] = useState('')
  const [ruleScope, setRuleScope] = useState('global')
  const [ruleUserId, setRuleUserId] = useState('')
  const [ruleTeamId, setRuleTeamId] = useState('')
  const [ruleNicheId, setRuleNicheId] = useState('')
  const [rulePercentage, setRulePercentage] = useState('5')
  const [ruleFixed, setRuleFixed] = useState('')
  const [ruleBasis, setRuleBasis] = useState('contracted')
  const [rulePriority, setRulePriority] = useState('0')
  const [ruleActive, setRuleActive] = useState(true)
  const [savingRule, setSavingRule] = useState(false)
  const [ruleError, setRuleError] = useState('')
  const [deactivating, setDeactivating] = useState<CommissionRule | null>(null)

  const { data, loading, error, reload } = useApi<CommissionResponse>('/commissions', {
    from: from || undefined,
    to: to || undefined,
    company_id: companyId || undefined,
    status: status || undefined,
  })
  const rulesApi = useApi<{ data: CommissionRule[] }>('/commission-rules', undefined, tab === 'rules' && Boolean(user?.permissions?.view_financials))
  const rows = data?.data || []
  const summary = data?.summary || {}
  const rules = rulesApi.data?.data || []
  const canApprove = Boolean(user?.permissions?.approve_commissions)
  const canView = Boolean(user?.permissions?.view_financials)

  const calculate = async () => {
    setCalculating(true)
    try {
      const response = await api.post('/commissions/calculate', { from, to })
      const result = response.data?.data
      notify(result ? result.message || 'Comissões calculadas.' : 'Comissões calculadas.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setCalculating(false)
    }
  }

  const approve = async (commission: Commission) => {
    try {
      await api.post(`/commissions/${commission.id}/approve`)
      notify('Comissão aprovada.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const pay = async (commission: Commission) => {
    try {
      await api.post(`/commissions/${commission.id}/pay`)
      notify('Comissão marcada como paga.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const reverse = async () => {
    if (!reversing) return
    if (!reverseReason.trim()) {
      notify('Informe o motivo do estorno.', 'error')
      return
    }
    try {
      await api.post(`/commissions/${reversing.id}/reverse`, { reason: reverseReason.trim() })
      notify('Comissão estornada.')
      setReversing(null)
      setReverseReason('')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const openRule = (rule?: CommissionRule | null) => {
    setEditingRule(rule || null)
    setRuleName(rule?.name || '')
    setRuleScope(rule?.scope || 'global')
    setRuleUserId(rule?.user_id ? String(rule.user_id) : '')
    setRuleTeamId(rule?.team_id ? String(rule.team_id) : '')
    setRuleNicheId(rule?.niche_id || '')
    setRulePercentage(rule?.percentage !== null && rule?.percentage !== undefined ? String(Number(rule.percentage)) : '')
    setRuleFixed(rule?.fixed_amount !== null && rule?.fixed_amount !== undefined ? String(Number(rule.fixed_amount)) : '')
    setRuleBasis(rule?.basis || 'contracted')
    setRulePriority(String(rule?.priority ?? 0))
    setRuleActive(rule?.active ?? true)
    setRuleError('')
    setRuleOpen(true)
  }

  const saveRule = async () => {
    setSavingRule(true)
    setRuleError('')
    try {
      const payload = {
        name: ruleName,
        scope: ruleScope,
        user_id: ruleScope === 'user' ? Number(ruleUserId) : null,
        team_id: ruleScope === 'team' ? Number(ruleTeamId) : null,
        niche_id: ruleScope === 'niche' ? ruleNicheId : null,
        percentage: rulePercentage !== '' ? Number(rulePercentage) : null,
        fixed_amount: ruleFixed !== '' ? Number(ruleFixed) : null,
        basis: ruleBasis,
        priority: Number(rulePriority || 0),
        active: ruleActive,
      }
      if (editingRule) await api.put(`/commission-rules/${editingRule.id}`, payload)
      else await api.post('/commission-rules', payload)
      notify(editingRule ? 'Regra atualizada.' : 'Regra criada.')
      setRuleOpen(false)
      rulesApi.reload()
    } catch (requestError) {
      setRuleError(apiMessage(requestError))
    } finally {
      setSavingRule(false)
    }
  }

  const deactivateRule = async () => {
    if (!deactivating) return
    try {
      await api.delete(`/commission-rules/${deactivating.id}`)
      notify('Regra desativada.')
      setDeactivating(null)
      rulesApi.reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <Icon name="calendar" size={13} />
            <span>De</span>
            <input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="bare-input" style={{ width: 100 }} />
          </label>
          <label className="pill" style={{ cursor: 'pointer', gap: 6 }}>
            <span>até</span>
            <input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="bare-input" style={{ width: 100 }} />
          </label>
          <Select value={companyId} onChange={(event) => setCompanyId(event.target.value)} style={{ width: 155 }}>
            <option value="">Todas as empresas</option>
            {companies.map((company) => <option key={company.id} value={company.id}>{company.name}</option>)}
          </Select>
          {tab === 'commissions' ? (
            <>
              <Select value={status} onChange={(event) => setStatus(event.target.value)} style={{ width: 150 }}><option value="">Todos os status</option>{Object.entries(COMMISSION_STATUS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select>
            </>
          ) : <Button icon="plus" disabled={user?.role !== 'admin'} onClick={() => openRule()}>Nova regra</Button>}
        </div>
        <div className="page-toolbar-right">
          {tab === 'commissions' ? <Button icon="refresh" loading={calculating} onClick={calculate}>Calcular período</Button> : null}
        </div>
      </div>

      <Tabs value={tab} onChange={setTab} tabs={[{ value: 'commissions', label: 'Comissões' }, { value: 'rules', label: 'Regras' }]} />

      {tab === 'commissions' ? (
        error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : (
          <>
            <div className="kpi-row five">
              <Stat label="Estimada" value={money(summary.estimated, true)} caption="aguardando cálculo" icon="chart" tone="blue" />
              <Stat label="Calculada" value={money(summary.calculated, true)} caption="pronta para aprovação" icon="commissions" tone="purple" />
              <Stat label="Aprovada" value={money(summary.approved, true)} caption="liberada para pagamento" icon="checkCircle" tone="amber" />
              <Stat label="Paga" value={money(summary.paid, true)} caption="quitação registrada" icon="money" tone="green" />
              <Stat label="Estornada" value={money(summary.reversed, true)} caption="fora do total líquido" icon="refresh" tone="rose" />
            </div>
            {rows.length ? (
              <div className="table-wrap">
                <table className="table">
                  <thead><tr><th>Lead</th><th>Usuário</th><th>Regra</th><th>Venda</th><th>Recebido</th><th>%</th><th>Status</th><th className="text-right">Comissão</th><th /></tr></thead>
                  <tbody>
                    {rows.map((commission) => (
                      <tr key={commission.id}>
                        <td className="cell-main">{commission.lead ? <Link to={`/leads/${commission.lead.id}`}>{commission.lead.name}</Link> : '—'}</td>
                        <td>{commission.user?.name || '—'}</td>
                        <td>{commission.rule?.name || 'Manual'}</td>
                        <td className="mono">{money(commission.sale_amount)}</td>
                        <td className="mono">{money(commission.received_amount)}</td>
                        <td>{commission.percentage ? `${Number(commission.percentage)}%` : '—'}</td>
                        <td><Badge tone={commission.status === 'paid' ? 'brand' : commission.status === 'approved' ? 'green' : commission.status === 'reversed' ? 'rose' : 'neutral'}>{COMMISSION_STATUS[commission.status] || commission.status}</Badge></td>
                        <td className="text-right mono">{money(commission.amount)}</td>
                        <td>{canApprove && commission.status !== 'reversed' ? (
                          <div className="actions">
                            {['estimated', 'calculated'].includes(commission.status) && (commission.user_id !== user?.id || canApprove) ? <button className="btn btn-sm" onClick={() => approve(commission)}>Aprovar</button> : null}
                            {commission.status === 'approved' ? <button className="btn btn-sm" onClick={() => pay(commission)}>Pagar</button> : null}
                            <IconButton icon="refresh" label="Estornar comissão" onClick={() => { setReverseReason(''); setReversing(commission) }} />
                          </div>
                        ) : null}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : <div className="panel panel-padded"><EmptyState icon="commissions" title="Nenhuma comissão" description="Closes do período geram comissões após o cálculo. Regras com snapshot histórico preservam o cálculo original." action={canView ? <Button onClick={calculate}>Calcular período</Button> : undefined} /></div>}
          </>
        )
      ) : (
        rulesApi.error ? <ErrorState message={rulesApi.error} onRetry={rulesApi.reload} /> : rulesApi.loading ? <LoadingState /> : rules.length ? (
          <div className="table-wrap">
            <table className="table">
              <thead><tr><th>Regra</th><th>Escopo</th><th>Alvo</th><th>Base</th><th>Percentual</th><th>Fixo</th><th>Prioridade</th><th>Status</th><th /></tr></thead>
              <tbody>
                {rules.map((rule) => (
                  <tr key={rule.id}>
                    <td className="cell-main">{rule.name}</td>
                    <td>{scopeLabels[rule.scope]}</td>
                    <td>{rule.user?.name || rule.team?.name || rule.niche?.name || 'Todos'}</td>
                    <td>{rule.basis === 'contracted' ? 'Contratado' : 'Recebido'}</td>
                    <td>{rule.percentage !== null && rule.percentage !== undefined ? `${Number(rule.percentage)}%` : '—'}</td>
                    <td>{rule.fixed_amount ? money(rule.fixed_amount) : '—'}</td>
                    <td>{rule.priority}</td>
                    <td><Badge tone={rule.active ? 'brand' : 'neutral'}>{rule.active ? 'Ativa' : 'Inativa'}</Badge></td>
                    <td>{user?.role === 'admin' ? <div className="actions"><IconButton icon="edit" label="Editar regra" onClick={() => openRule(rule)} /><IconButton icon="trash" label="Desativar regra" onClick={() => setDeactivating(rule)} /></div> : null}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : <div className="panel panel-padded"><EmptyState icon="sliders" title="Nenhuma regra" description="Crie regras globais, por usuário, equipe ou nicho. A prioridade maior vence." action={user?.role === 'admin' ? <Button onClick={() => openRule()}>Nova regra</Button> : undefined} /></div>
      )}

      <Modal open={Boolean(reversing)} onClose={() => setReversing(null)} title="Estornar comissão" subtitle={reversing ? `${money(reversing.amount)} · ${reversing.user?.name}` : undefined} footer={<><Button onClick={() => setReversing(null)}>Cancelar</Button><Button variant="danger" onClick={reverse}>Estornar</Button></>}>
        <Field label="Motivo obrigatório" required><Textarea rows={3} value={reverseReason} onChange={(event) => setReverseReason(event.target.value)} placeholder="Descreva o motivo do estorno para auditoria." /></Field>
        <div className="callout warn mt-12">O estorno não apaga a comissão: mantém o snapshot e o histórico para auditoria.</div>
      </Modal>

      <Modal open={ruleOpen} onClose={() => { if (!savingRule) setRuleOpen(false) }} title={editingRule ? 'Editar regra' : 'Nova regra'} subtitle="Regras são aplicadas por prioridade decrescente e registram snapshot no cálculo." footer={<><Button onClick={() => setRuleOpen(false)}>Cancelar</Button><Button variant="primary" loading={savingRule} onClick={saveRule}>{editingRule ? 'Salvar' : 'Criar regra'}</Button></>}>
        {ruleError ? <div className="login-error" style={{ marginBottom: 13 }}>{ruleError}</div> : null}
        <div className="form-grid">
          <Field label="Nome" required className="span-2"><Input value={ruleName} onChange={(event) => setRuleName(event.target.value)} placeholder="Ex.: Comissão padrão closers" /></Field>
          <Field label="Escopo"><Select value={ruleScope} onChange={(event) => setRuleScope(event.target.value)}>{Object.entries(scopeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
          <Field label="Base de cálculo"><Select value={ruleBasis} onChange={(event) => setRuleBasis(event.target.value)}><option value="contracted">Valor contratado</option><option value="received">Valor recebido</option></Select></Field>
          {ruleScope === 'user' ? <Field label="Usuário" required><Select value={ruleUserId} onChange={(event) => setRuleUserId(event.target.value)}><option value="">Selecione</option>{users.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field> : null}
          {ruleScope === 'team' ? <Field label="Equipe" required><Select value={ruleTeamId} onChange={(event) => setRuleTeamId(event.target.value)}><option value="">Selecione</option>{teams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</Select></Field> : null}
          {ruleScope === 'niche' ? <Field label="Nicho" required><Select value={ruleNicheId} onChange={(event) => setRuleNicheId(event.target.value)}><option value="">Selecione</option>{niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}</Select></Field> : null}
          <Field label="Percentual (%)" hint="Preencha percentual ou valor fixo."><Input type="number" min={0} max={100} step="0.01" value={rulePercentage} onChange={(event) => setRulePercentage(event.target.value)} /></Field>
          <Field label="Valor fixo (R$)"><Input type="number" min={0} step="0.01" value={ruleFixed} onChange={(event) => setRuleFixed(event.target.value)} /></Field>
          <Field label="Prioridade"><Input type="number" min={0} max={100} value={rulePriority} onChange={(event) => setRulePriority(event.target.value)} /></Field>
          <Field label="Status"><Select value={ruleActive ? '1' : '0'} onChange={(event) => setRuleActive(event.target.value === '1')}><option value="1">Ativa</option><option value="0">Inativa</option></Select></Field>
        </div>
      </Modal>

      <ConfirmDialog open={Boolean(deactivating)} title="Desativar regra" message={`Desativar "${deactivating?.name}"? Cálculos futuros deixarão de usá-la; snapshots antigos permanecem.`} confirmLabel="Desativar" onClose={() => setDeactivating(null)} onConfirm={deactivateRule} />
    </div>
  )
}
