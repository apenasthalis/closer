import { useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api, apiMessage } from '@/lib/api'
import { useApi, useLeadOptions, useLookups } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { CALL_STATUS, CALL_STATUS_LABELS } from '@/lib/constants'
import { dateTime, num } from '@/lib/format'
import type { Call, Paginated } from '@/lib/types'
import {
  Badge, Button, EmptyState, ErrorState, Field, Icon, Input, LoadingState, Modal,
  Pagination, Select, Tabs, Textarea, useToast,
} from '@/components/ui'

const analysisLabels: Record<string, string> = {
  not_requested: 'Não solicitada',
  pending: 'Pendente',
  completed: 'Concluída',
  failed: 'Falhou',
}

function localNow() {
  return new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)
}

export function CallsPage() {
  const { user } = useAuth()
  const { notify } = useToast()
  const [searchParams] = useSearchParams()
  const initialLead = searchParams.get('lead_id') || ''
  const openedFromLead = useRef(false)
  const leads = useLeadOptions()
  const { users } = useLookups()
  const [status, setStatus] = useState('')
  const [closerId, setCloserId] = useState('')
  const [analysisStatus, setAnalysisStatus] = useState('')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState(false)
  const [selected, setSelected] = useState<string | null>(null)
  const [detailTab, setDetailTab] = useState('analysis')
  const [showTranscript, setShowTranscript] = useState(false)
  const [formLead, setFormLead] = useState(initialLead)
  const [formTitle, setFormTitle] = useState('')
  const [formCloser, setFormCloser] = useState('')
  const [formStatus, setFormStatus] = useState('completed')
  const [formStart, setFormStart] = useState(localNow())
  const [formDuration, setFormDuration] = useState('30')
  const [formTranscript, setFormTranscript] = useState('')
  const [saving, setSaving] = useState(false)
  const [formError, setFormError] = useState('')
  const [analyzing, setAnalyzing] = useState(false)
  const [creatingTasks, setCreatingTasks] = useState(false)

  const { data, loading, error, reload } = useApi<Paginated<Call>>('/calls', {
    status: status || undefined,
    closer_id: closerId || undefined,
    analysis_status: analysisStatus || undefined,
    page,
  })
  const { data: detail, loading: detailLoading, reload: reloadDetail } = useApi<{ data: Call }>(selected ? `/calls/${selected}` : null)
  const call = detail?.data

  useEffect(() => {
    if (initialLead && !openedFromLead.current) {
      openedFromLead.current = true
      setFormLead(initialLead)
      setOpen(true)
    }
  }, [initialLead])

  const leadName = useMemo(() => leads.find((lead) => lead.id === formLead)?.name || '', [leads, formLead])

  const create = async () => {
    setSaving(true)
    setFormError('')
    try {
      const response = await api.post('/calls', {
        lead_id: formLead || null,
        closer_id: formCloser ? Number(formCloser) : null,
        title: formTitle,
        status: formStatus,
        started_at: formStart,
        duration_sec: Math.round(Number(formDuration || 0) * 60),
        transcript: formTranscript.trim() ? { text: formTranscript.trim() } : null,
      })
      notify('Call registrada.')
      setOpen(false)
      setFormTitle('')
      setFormTranscript('')
      setSelected(response.data.data.id)
      setDetailTab('analysis')
      reload()
    } catch (requestError) {
      setFormError(apiMessage(requestError))
    } finally {
      setSaving(false)
    }
  }

  const analyze = async () => {
    if (!call) return
    setAnalyzing(true)
    try {
      const response = await api.post(`/calls/${call.id}/analyze`)
      notify('Call analisada localmente.')
      if (response.data?.data) {
        reloadDetail()
        reload()
      }
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setAnalyzing(false)
    }
  }

  const createTasks = async () => {
    if (!call) return
    setCreatingTasks(true)
    try {
      const response = await api.post(`/calls/${call.id}/next-steps`)
      notify(response.data?.message || 'Tarefas criadas.')
      reloadDetail()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setCreatingTasks(false)
    }
  }

  const calls = data?.data || []
  const spin = call?.spin as Record<string, any> | null | undefined
  const objections = (call?.objections || []) as any[]
  const analysis = call?.analysis as any
  const playbook = analysis?.playbook || null
  const highlights = (call?.highlights || []) as any[]
  const steps = (call?.extracted_steps || []) as any[]

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <Select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }} style={{ width: 150 }}>
            <option value="">Todos os status</option>
            {CALL_STATUS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
          </Select>
          <Select value={closerId} onChange={(event) => { setCloserId(event.target.value); setPage(1) }} style={{ width: 155 }}>
            <option value="">Todos os closers</option>
            {users.filter((item) => item.role !== 'sdr').map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
          </Select>
          <Select value={analysisStatus} onChange={(event) => { setAnalysisStatus(event.target.value); setPage(1) }} style={{ width: 165 }}>
            <option value="">Qualquer análise</option>
            <option value="not_requested">Não solicitada</option>
            <option value="pending">Pendente</option>
            <option value="completed">Concluída</option>
            <option value="failed">Falhou</option>
          </Select>
        </div>
        <Button variant="primary" icon="plus" onClick={() => { setFormError(''); setOpen(true) }}>Nova call</Button>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !calls.length ? (
        <div className="panel panel-padded">
          <EmptyState icon="calls" title="Nenhuma call encontrada" description="Registre uma reunião e cole a transcrição para gerar resumo, SPIN, objeções e próximos passos." action={<Button onClick={() => setOpen(true)}>Registrar call</Button>} />
        </div>
      ) : (
        <>
          <div className="table-wrap">
            <table className="table">
              <thead><tr><th>Call</th><th>Lead</th><th>Closer</th><th>Status</th><th>Data</th><th>Duração</th><th>Análise</th><th className="text-right">Score</th><th /></tr></thead>
              <tbody>
                {calls.map((item) => (
                  <tr className="row-link" key={item.id} onClick={() => { setSelected(item.id); setDetailTab('analysis'); setShowTranscript(false) }}>
                    <td className="cell-main">{item.title}</td>
                    <td>{item.lead?.name || <span className="muted">Sem lead</span>}</td>
                    <td>{item.closer?.name || '—'}</td>
                    <td><Badge tone={item.status === 'completed' ? 'brand' : item.status === 'no_show' ? 'rose' : item.status === 'cancelled' ? 'neutral' : 'blue'}>{CALL_STATUS_LABELS[item.status] || item.status}</Badge></td>
                    <td>{dateTime(item.started_at)}</td>
                    <td>{Math.round(item.duration_sec / 60)} min</td>
                    <td><Badge tone={item.analysis_status === 'completed' ? 'green' : item.analysis_status === 'failed' ? 'rose' : 'neutral'}>{analysisLabels[item.analysis_status] || item.analysis_status}</Badge></td>
                    <td className="text-right mono">{item.analysis_status === 'completed' ? `${item.playbook_score ?? 0}%` : '—'}</td>
                    <td><Icon name="chevronRight" size={14} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data?.current_page || 1} lastPage={data?.last_page || 1} total={data?.total || calls.length} onChange={setPage} />
        </>
      )}

      <Modal
        open={open}
        onClose={() => { if (!saving) setOpen(false) }}
        title="Registrar call"
        subtitle="A análise é opcional e executada localmente, sem enviar a transcrição a terceiros."
        size="lg"
        footer={<><Button onClick={() => setOpen(false)}>Cancelar</Button><Button variant="primary" loading={saving} onClick={create}>Registrar call</Button></>}
      >
        {formError ? <div className="login-error" style={{ marginBottom: 13 }}>{formError}</div> : null}
        <div className="form-grid">
          <Field label="Título" required className="span-2"><Input value={formTitle} onChange={(event) => setFormTitle(event.target.value)} placeholder={leadName ? `Call com ${leadName}` : 'Ex.: Diagnóstico comercial'} /></Field>
          <Field label="Lead">
            <Select value={formLead} onChange={(event) => setFormLead(event.target.value)}>
              <option value="">Sem lead</option>
              {leads.map((lead) => <option key={lead.id} value={lead.id}>{lead.name}{lead.company ? ` · ${lead.company}` : ''}</option>)}
            </Select>
          </Field>
          {user?.role === 'admin' || user?.role === 'manager' ? <Field label="Closer"><Select value={formCloser} onChange={(event) => setFormCloser(event.target.value)}><option value="">Meu usuário</option>{users.filter((item) => item.role !== 'sdr').map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field> : null}
          <Field label="Status"><Select value={formStatus} onChange={(event) => setFormStatus(event.target.value)}>{CALL_STATUS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
          <Field label="Início"><Input type="datetime-local" value={formStart} onChange={(event) => setFormStart(event.target.value)} /></Field>
          <Field label="Duração (min)"><Input type="number" min={0} value={formDuration} onChange={(event) => setFormDuration(event.target.value)} /></Field>
          <Field label="Transcrição" hint="Use Closer: e Lead: no início das falas para orientar a análise." className="span-2">
            <Textarea rows={10} value={formTranscript} onChange={(event) => setFormTranscript(event.target.value)} placeholder={'Closer: Olá, tudo bem? Obrigado por participar.\nLead: Oi, tudo bem.\nCloser: Me conta como funciona o processo de vocês hoje...'} />
          </Field>
        </div>
      </Modal>

      <Modal
        open={Boolean(selected)}
        onClose={() => setSelected(null)}
        title={call?.title || 'Call'}
        subtitle={call ? `${call.lead?.name || 'Sem lead'} · ${dateTime(call.started_at)} · ${Math.round(call.duration_sec / 60)} min` : undefined}
        size="xl"
        footer={(
          <>
            <Button onClick={() => setSelected(null)}>Fechar</Button>
            {call?.transcript ? <Button icon="sparkle" loading={analyzing} onClick={analyze}>{call.analysis_status === 'completed' ? 'Reanalisar' : 'Analisar localmente'}</Button> : null}
          </>
        )}
      >
        {detailLoading && !call ? <LoadingState /> : call ? (
          <>
            {call.analysis_status !== 'completed' ? (
              <div className="callout warn" style={{ marginBottom: 14 }}>
                <strong>Análise {analysisLabels[call.analysis_status]?.toLowerCase() || 'não concluída'}.</strong> A análise heurística usa apenas o texto local. Sem transcrição, ela não é executada.
              </div>
            ) : null}
            <Tabs value={detailTab} onChange={setDetailTab} tabs={[{ value: 'analysis', label: 'Análise' }, { value: 'transcript', label: 'Transcrição' }, { value: 'meta', label: 'Metadados' }]} />

            {detailTab === 'analysis' ? (
              call.analysis_status !== 'completed' ? (
                <EmptyState icon="sparkle" title="Sem análise" description={call.transcript ? 'Execute a análise local para gerar resumo, SPIN, objeções e próximos passos.' : 'Esta call não possui transcrição registrada.'} action={call.transcript ? <Button variant="primary" loading={analyzing} onClick={analyze}>Analisar agora</Button> : undefined} />
              ) : (
                <div className="analysis-wrap">
                  <div className="analysis-hero">
                    <div className="score-ring" style={{ '--score': `${call.playbook_score || 0}%` } as React.CSSProperties}>
                      <div><b>{call.playbook_score ?? 0}</b><span>score / 100</span></div>
                    </div>
                    <div className="analysis-hero-main">
                      <Badge tone="purple">{call.ai_provider === 'local-heuristic' ? 'Heurística local · sem IA externa' : call.ai_provider || 'Local'}</Badge>
                      <p>{call.summary || analysis?.summary}</p>
                    </div>
                    <div className="analysis-metrics">
                      <div><span>Talk-time closer</span><b>{call.talk_time_closer !== null && call.talk_time_closer !== undefined ? `${Math.round(call.talk_time_closer * 100)}%` : '—'}</b></div>
                      <div><span>Talk-time lead</span><b>{call.talk_time_lead !== null && call.talk_time_lead !== undefined ? `${Math.round(call.talk_time_lead * 100)}%` : '—'}</b></div>
                      <div><span>Objeções</span><b>{objections.length}</b></div>
                    </div>
                  </div>

                  <div className="grid-2">
                    <section className="analysis-section">
                      <h4>SPIN Selling</h4>
                      {spin ? Object.entries(spin).map(([key, value]: [string, any]) => (
                        <div className={`spin-block ${value.identified ? 'found' : ''}`} key={key}>
                          <div className="spin-head"><b>{value.label}</b><Badge tone={value.identified ? 'green' : 'neutral'}>{value.identified ? `${value.evidence?.length || 0} evidência(s)` : 'Sem evidência'}</Badge></div>
                          {value.evidence?.length ? value.evidence.slice(0, 3).map((evidence: any, index: number) => <blockquote key={index}>{evidence.speaker ? <span>{evidence.speaker}: </span> : null}{evidence.text}</blockquote>) : (
                            <div className="spin-suggestions"><span>Sugestões para investigar:</span>{(value.suggested_questions || []).map((question: string, index: number) => <p key={index}>“{question}”</p>)}</div>
                          )}
                        </div>
                      )) : <p className="muted small">SPIN não disponível.</p>}
                    </section>

                    <div className="stack">
                      <section className="analysis-section">
                        <h4>Objeções identificadas</h4>
                        {objections.length ? objections.map((objection, index) => (
                          <div className="objection-block" key={index}>
                            <div className="spin-head"><b>{objection.label}</b><Badge tone="amber">{objection.raised} menção(ões)</Badge></div>
                            {(objection.evidence || []).slice(0, 2).map((evidence: any, evidenceIndex: number) => <blockquote key={evidenceIndex}>{evidence.speaker ? <span>{evidence.speaker}: </span> : null}{evidence.text}</blockquote>)}
                            {objection.suggested_rebuttal ? <p className="rebuttal"><strong>Rebate sugerido:</strong> {objection.suggested_rebuttal}</p> : null}
                          </div>
                        )) : <p className="muted small">Nenhuma objeção textual identificada.</p>}
                      </section>

                      <section className="analysis-section">
                        <h4>Playbook</h4>
                        {playbook?.breakdown?.length ? playbook.breakdown.map((criterion: any) => (
                          <div className={`criterion-row ${criterion.found ? 'found' : ''}`} key={criterion.key}>
                            <span>{criterion.found ? <Icon name="checkCircle" size={13} /> : <Icon name="xCircle" size={13} />}</span>
                            <span className="criterion-label">{criterion.label}</span>
                            <span className="criterion-weight">peso {criterion.weight}</span>
                          </div>
                        )) : <p className="muted small">Nenhum playbook aplicado.</p>}
                      </section>
                    </div>
                  </div>

                  <div className="grid-2 mt-16">
                    <section className="analysis-section">
                      <h4>Sinais e destaques</h4>
                      {highlights.length ? highlights.map((highlight, index) => (
                        <div className="highlight-row" key={index}><Badge tone={highlight.type === 'buy_signal' ? 'brand' : highlight.type === 'risk' ? 'rose' : 'blue'}>{highlight.label || highlight.type}</Badge><span>{highlight.text}</span></div>
                      )) : <p className="muted small">Nenhum destaque extraído.</p>}
                      {analysis?.risks?.length ? <div className="callout danger mt-12">{analysis.risks.map((risk: string, index: number) => <div key={index}>• {risk}</div>)}</div> : null}
                    </section>
                    <section className="analysis-section">
                      <div className="panel-head" style={{ marginBottom: 8 }}><h4>Próximos passos</h4><Button size="sm" variant="soft" icon="reminders" loading={creatingTasks} disabled={!steps.length} onClick={createTasks}>Criar tarefas</Button></div>
                      {steps.length ? steps.map((step: any, index: number) => (
                        <div className="next-step-row" key={index}><span className="step-num">{index + 1}</span><div><b>{step.title}</b>{step.context ? <p>{step.context}</p> : null}</div></div>
                      )) : <p className="muted small">Nenhum próximo passo extraído da transcrição.</p>}
                    </section>
                  </div>
                  {analysis?.caveats?.length ? <div className="callout mt-16"><strong>Limitações:</strong> {analysis.caveats.join(' ')}</div> : null}
                </div>
              )
            ) : null}

            {detailTab === 'transcript' ? (
              <div>
                <div className="panel-toolbar"><div><h4>Transcrição</h4><p className="panel-sub">{call.transcript?.text ? `${num(call.transcript.text.length)} caracteres` : 'Transcrição não registrada'}</p></div></div>
                <div className="transcript">{call.transcript?.text || 'Nenhuma transcrição foi registrada para esta call.'}</div>
              </div>
            ) : null}

            {detailTab === 'meta' ? (
              <div className="kv-list">
                <div className="kv"><span>Lead</span><b>{call.lead?.name || '—'}</b></div>
                <div className="kv"><span>Closer</span><b>{call.closer?.name || '—'}</b></div>
                <div className="kv"><span>Status</span><b>{CALL_STATUS_LABELS[call.status]}</b></div>
                <div className="kv"><span>Início</span><b>{dateTime(call.started_at)}</b></div>
                <div className="kv"><span>Duração</span><b>{Math.round(call.duration_sec / 60)} min</b></div>
                <div className="kv"><span>Provedor</span><b>{call.ai_provider || '—'}</b></div>
                <div className="kv"><span>Analisada em</span><b>{dateTime(call.analyzed_at)}</b></div>
              </div>
            ) : null}
          </>
        ) : null}
      </Modal>
    </div>
  )
}
