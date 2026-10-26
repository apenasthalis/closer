import { useMemo, useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useApi, useDebounced, useLookups } from '@/lib/hooks'
import { useAuth } from '@/auth/AuthProvider'
import { STAGES, STAGE_LABELS } from '@/lib/constants'
import { date, num, pct } from '@/lib/format'
import type { Objection, Playbook, Script } from '@/lib/types'
import {
  Badge, Button, ConfirmDialog, EmptyState, ErrorState, Field, Icon, IconButton, Input,
  LoadingState, Modal, SearchInput, Select, Tabs, Textarea, useToast,
} from '@/components/ui'

interface ScriptForm {
  title: string
  description: string
  niche_id: string
  stage: string
  category: string
  status: string
  sections: Array<{ title: string; content: string }>
}

interface PlaybookForm {
  name: string
  description: string
  niche_id: string
  active: boolean
  criteria: Array<{ key: string; label: string; weight: number; keywords: string }>
}

const blankScript = (): ScriptForm => ({
  title: '', description: '', niche_id: '', stage: '', category: '', status: 'draft',
  sections: [{ title: 'Abertura', content: '' }],
})

const blankPlaybook = (): PlaybookForm => ({
  name: '', description: '', niche_id: '', active: true,
  criteria: [{ key: 'abertura', label: 'Abertura', weight: 5, keywords: '' }],
})

const blankObjection = {
  label: '', niche_id: '', category: '', stage: '', context: '', result: '', what_worked: '', best_rebuttal: '', times_raised: '1', times_resolved: '0',
}

const SCRIPT_STATUS_LABELS: Record<string, string> = {
  draft: 'Rascunho',
  published: 'Publicado',
  archived: 'Arquivado',
}

export function LibraryPage() {
  const { user } = useAuth()
  const { notify } = useToast()
  const { niches } = useLookups()
  const [tab, setTab] = useState('scripts')
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounced(search)
  const [nicheFilter, setNicheFilter] = useState('')
  const [stageFilter, setStageFilter] = useState('')
  const scriptsApi = useApi<{ data: Script[] }>('/scripts', { q: debouncedSearch || undefined, niche_id: nicheFilter || undefined, stage: stageFilter || undefined })
  const playbooksApi = useApi<{ data: Playbook[] }>('/playbooks')
  const objectionsApi = useApi<{ data: Objection[]; summary: any }>('/objections', { q: debouncedSearch || undefined, niche_id: nicheFilter || undefined })

  const scripts = scriptsApi.data?.data || []
  const playbooks = playbooksApi.data?.data || []
  const objections = objectionsApi.data?.data || []
  const objectionSummary = objectionsApi.data?.summary
  const canManage = Boolean(user?.permissions?.manage_playbooks) || user?.role === 'admin' || user?.role === 'manager'

  const [scriptView, setScriptView] = useState<Script | null>(null)
  const [scriptFormOpen, setScriptFormOpen] = useState(false)
  const [scriptEditing, setScriptEditing] = useState<Script | null>(null)
  const [scriptForm, setScriptForm] = useState<ScriptForm>(blankScript())
  const [savingScript, setSavingScript] = useState(false)
  const [scriptError, setScriptError] = useState('')

  const [playbookOpen, setPlaybookOpen] = useState(false)
  const [playbookEditing, setPlaybookEditing] = useState<Playbook | null>(null)
  const [playbookForm, setPlaybookForm] = useState<PlaybookForm>(blankPlaybook())
  const [savingPlaybook, setSavingPlaybook] = useState(false)
  const [playbookError, setPlaybookError] = useState('')

  const [objectionOpen, setObjectionOpen] = useState(false)
  const [objectionEditing, setObjectionEditing] = useState<Objection | null>(null)
  const [objectionForm, setObjectionForm] = useState({ ...blankObjection })
  const [savingObjection, setSavingObjection] = useState(false)
  const [objectionError, setObjectionError] = useState('')
  const [deleteObjection, setDeleteObjection] = useState<Objection | null>(null)

  const activeLoading = tab === 'scripts' ? scriptsApi.loading : tab === 'playbooks' ? playbooksApi.loading : objectionsApi.loading
  const activeError = tab === 'scripts' ? scriptsApi.error : tab === 'playbooks' ? playbooksApi.error : objectionsApi.error
  const reloadActive = tab === 'scripts' ? scriptsApi.reload : tab === 'playbooks' ? playbooksApi.reload : objectionsApi.reload

  const openScriptForm = (script?: Script | null) => {
    setScriptEditing(script || null)
    setScriptForm(script ? {
      title: script.title,
      description: script.description || '',
      niche_id: script.niche_id || '',
      stage: script.stage || '',
      category: script.category || '',
      status: script.status,
      sections: script.sections?.length ? script.sections.map((section) => ({ title: section.title, content: section.content })) : [{ title: 'Abertura', content: '' }],
    } : blankScript())
    setScriptError('')
    setScriptFormOpen(true)
  }

  const saveScript = async () => {
    setSavingScript(true)
    setScriptError('')
    try {
      const payload = {
        ...scriptForm,
        niche_id: scriptForm.niche_id || null,
        stage: scriptForm.stage || null,
        category: scriptForm.category || null,
        description: scriptForm.description || null,
        sections: scriptForm.sections.filter((section) => section.title && section.content),
      }
      if (scriptEditing) await api.put(`/scripts/${scriptEditing.id}`, payload)
      else await api.post('/scripts', payload)
      notify(scriptEditing ? 'Script atualizado.' : 'Script criado.')
      setScriptFormOpen(false)
      scriptsApi.reload()
    } catch (requestError) {
      setScriptError(apiMessage(requestError))
    } finally {
      setSavingScript(false)
    }
  }

  const archiveScript = async (script: Script) => {
    try {
      await api.delete(`/scripts/${script.id}`)
      notify('Script arquivado.')
      setScriptView(null)
      scriptsApi.reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const openPlaybookForm = (playbook?: Playbook | null) => {
    setPlaybookEditing(playbook || null)
    setPlaybookForm(playbook ? {
      name: playbook.name,
      description: playbook.description || '',
      niche_id: playbook.niche_id || '',
      active: playbook.active,
      criteria: playbook.criteria.map((criterion) => ({ ...criterion, keywords: criterion.keywords.join(', ') })),
    } : blankPlaybook())
    setPlaybookError('')
    setPlaybookOpen(true)
  }

  const savePlaybook = async () => {
    setSavingPlaybook(true)
    setPlaybookError('')
    try {
      const payload = {
        name: playbookForm.name,
        description: playbookForm.description || null,
        niche_id: playbookForm.niche_id || null,
        active: playbookForm.active,
        criteria: playbookForm.criteria.map((criterion) => ({
          key: criterion.key, label: criterion.label, weight: Number(criterion.weight),
          keywords: criterion.keywords.split(',').map((keyword) => keyword.trim()).filter(Boolean),
        })),
      }
      if (playbookEditing) await api.put(`/playbooks/${playbookEditing.id}`, payload)
      else await api.post('/playbooks', payload)
      notify(playbookEditing ? 'Playbook atualizado.' : 'Playbook criado.')
      setPlaybookOpen(false)
      playbooksApi.reload()
    } catch (requestError) {
      setPlaybookError(apiMessage(requestError))
    } finally {
      setSavingPlaybook(false)
    }
  }

  const openObjectionForm = (objection?: Objection | null) => {
    setObjectionEditing(objection || null)
    setObjectionForm(objection ? {
      label: objection.label, niche_id: objection.niche_id || '', category: objection.category || '',
      stage: objection.stage || '', context: objection.context || '', result: objection.result || '',
      what_worked: objection.what_worked || '', best_rebuttal: objection.best_rebuttal || '',
      times_raised: String(objection.times_raised), times_resolved: String(objection.times_resolved),
    } : { ...blankObjection })
    setObjectionError('')
    setObjectionOpen(true)
  }

  const saveObjection = async () => {
    setSavingObjection(true)
    setObjectionError('')
    try {
      const payload = {
        ...objectionForm,
        niche_id: objectionForm.niche_id || null,
        category: objectionForm.category || null,
        stage: objectionForm.stage || null,
        context: objectionForm.context || null,
        result: objectionForm.result || null,
        what_worked: objectionForm.what_worked || null,
        best_rebuttal: objectionForm.best_rebuttal || null,
        times_raised: Number(objectionForm.times_raised || 0),
        times_resolved: Number(objectionForm.times_resolved || 0),
      }
      if (objectionEditing) await api.put(`/objections/${objectionEditing.id}`, payload)
      else await api.post('/objections', payload)
      notify(objectionEditing ? 'Objeção atualizada.' : 'Objeção registrada.')
      setObjectionOpen(false)
      objectionsApi.reload()
    } catch (requestError) {
      setObjectionError(apiMessage(requestError))
    } finally {
      setSavingObjection(false)
    }
  }

  const removeObjection = async () => {
    if (!deleteObjection) return
    try {
      await api.delete(`/objections/${deleteObjection.id}`)
      notify('Objeção removida.')
      setDeleteObjection(null)
      objectionsApi.reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    }
  }

  const categories = useMemo(() => [...new Set(objections.map((objection) => objection.category).filter(Boolean))], [objections])

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <SearchInput value={search} onChange={setSearch} placeholder={tab === 'scripts' ? 'Buscar script...' : tab === 'playbooks' ? 'Buscar playbook...' : 'Buscar objeção...'} />
          <Select value={nicheFilter} onChange={(event) => setNicheFilter(event.target.value)} style={{ width: 155 }}>
            <option value="">Todos os nichos</option>
            {niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}
          </Select>
          {tab === 'scripts' ? <Select value={stageFilter} onChange={(event) => setStageFilter(event.target.value)} style={{ width: 155 }}><option value="">Todas as etapas</option>{STAGES.map((stage) => <option key={stage.value} value={stage.value}>{stage.label}</option>)}</Select> : null}
        </div>
        <div className="page-toolbar-right">
          {tab === 'scripts' && canManage ? <Button variant="primary" icon="plus" onClick={() => openScriptForm(null)}>Novo script</Button> : null}
          {tab === 'playbooks' && canManage ? <Button variant="primary" icon="plus" onClick={() => openPlaybookForm(null)}>Novo playbook</Button> : null}
          {tab === 'objections' ? <Button variant="primary" icon="plus" onClick={() => openObjectionForm(null)}>Registrar objeção</Button> : null}
        </div>
      </div>

      <Tabs
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'scripts', label: 'Scripts', count: scripts.length },
          { value: 'playbooks', label: 'Playbooks', count: playbooks.length },
          { value: 'objections', label: 'Objeções', count: objections.length },
        ]}
      />

      {activeError ? <ErrorState message={activeError} onRetry={reloadActive} /> : activeLoading ? <LoadingState /> : (
        <>
          {tab === 'scripts' ? (
            scripts.length ? (
              <div className="library-grid">
                {scripts.map((script) => (
                  <article className="library-card" key={script.id} onClick={() => setScriptView(script)}>
                    <div className="library-card-top">
                      <span className="library-icon"><Icon name="file" size={16} /></span>
                      <Badge tone={script.status === 'published' ? 'brand' : script.status === 'draft' ? 'amber' : 'neutral'}>{script.status === 'published' ? 'Publicado' : script.status === 'draft' ? 'Rascunho' : 'Arquivado'}</Badge>
                    </div>
                    <h3>{script.title}</h3>
                    <p>{script.description || 'Sem descrição.'}</p>
                    <div className="library-meta">
                      {script.niche ? <span><span className="niche-dot" style={{ background: script.niche.color || 'var(--brand)' }} />{script.niche.name}</span> : <span>Global</span>}
                      {script.stage ? <span>{STAGE_LABELS[script.stage]}</span> : null}
                      <span>v{script.version}</span>
                    </div>
                    <div className="library-foot"><span>{script.sections?.length || 0} seção(ões)</span><span>{script.updated_by?.name || 'Sistema'} · {date(script.updated_at)}</span></div>
                  </article>
                ))}
              </div>
            ) : <div className="panel panel-padded"><EmptyState icon="library" title="Nenhum script" description="Crie scripts de abordagem, diagnóstico e fechamento por etapa e nicho." action={canManage ? <Button onClick={() => openScriptForm(null)}>Novo script</Button> : undefined} /></div>
          ) : null}

          {tab === 'playbooks' ? (
            playbooks.length ? (
              <div className="library-grid">
                {playbooks.map((playbook) => (
                  <article className="library-card" key={playbook.id}>
                    <div className="library-card-top">
                      <span className="library-icon purple"><Icon name="target" size={16} /></span>
                      <Badge tone={playbook.active ? 'brand' : 'neutral'}>{playbook.active ? 'Ativo' : 'Inativo'}</Badge>
                    </div>
                    <h3>{playbook.name}</h3>
                    <p>{playbook.description || 'Sem descrição.'}</p>
                    <div className="library-meta"><span>{playbook.niche?.name || 'Global'}</span><span>v{playbook.version}</span><span>{playbook.criteria.length} critérios</span></div>
                    <div className="criteria-preview">
                      {playbook.criteria.slice(0, 4).map((criterion) => <span key={criterion.key} className="criterion-pill">{criterion.label} <b>{criterion.weight}</b></span>)}
                    </div>
                    {canManage ? <div className="library-foot"><span>{playbook.criteria.reduce((sum, criterion) => sum + criterion.weight, 0)} peso total</span><Button size="sm" icon="edit" onClick={() => openPlaybookForm(playbook)}>Editar</Button></div> : null}
                  </article>
                ))}
              </div>
            ) : <div className="panel panel-padded"><EmptyState icon="target" title="Nenhum playbook" description="Defina critérios e pesos para a análise local das calls." action={canManage ? <Button onClick={() => openPlaybookForm(null)}>Novo playbook</Button> : undefined} /></div>
          ) : null}

          {tab === 'objections' ? (
            <>
              {objectionSummary ? (
                <div className="kpi-row four">
                  <div className="kpi"><div className="kpi-label">Objeções mapeadas</div><div className="kpi-value">{num(objectionSummary.total)}</div><div className="kpi-cap">{objectionSummary.by_category?.length || 0} categoria(s)</div></div>
                  <div className="kpi"><div className="kpi-label">Levantadas</div><div className="kpi-value">{num(objectionSummary.raised)}</div><div className="kpi-cap">menções registradas</div></div>
                  <div className="kpi"><div className="kpi-label">Resolvidas</div><div className="kpi-value">{num(objectionSummary.resolved)}</div><div className="kpi-cap">respostas efetivas</div></div>
                  <div className="kpi"><div className="kpi-label">Taxa de resolução</div><div className="kpi-value">{pct(objectionSummary.resolution_rate)}</div><div className="kpi-cap">resolvidas ÷ levantadas</div></div>
                </div>
              ) : null}
              {objections.length ? (
                <div className="table-wrap">
                  <table className="table">
                    <thead><tr><th>Objeção</th><th>Categoria</th><th>Nicho</th><th>Levantadas</th><th>Resolvidas</th><th>Resolução</th><th>Melhor resposta</th><th /></tr></thead>
                    <tbody>
                      {objections.map((objection) => (
                        <tr key={objection.id}>
                          <td className="cell-main">{objection.label}</td>
                          <td>{objection.category || <span className="muted">—</span>}</td>
                          <td>{objection.niche?.name || <span className="muted">Global</span>}</td>
                          <td>{objection.times_raised}</td>
                          <td>{objection.times_resolved}</td>
                          <td>{objection.times_raised ? pct((objection.times_resolved / objection.times_raised) * 100) : '—'}</td>
                          <td><span className="truncate-cell" title={objection.best_rebuttal || ''}>{objection.best_rebuttal || '—'}</span></td>
                          <td><div className="actions"><IconButton icon="edit" label="Editar objeção" onClick={() => openObjectionForm(objection)} />{user?.role === 'admin' || user?.role === 'manager' ? <IconButton icon="trash" label="Remover objeção" onClick={() => setDeleteObjection(objection)} /> : null}</div></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : <div className="panel panel-padded"><EmptyState icon="message" title="Nenhuma objeção" description="Registre objeções reais e o que funcionou para construir repertório comercial." action={<Button onClick={() => openObjectionForm(null)}>Registrar objeção</Button>} /></div>}
              {categories.length ? <div className="pill-row mt-12">{categories.map((category) => <span className="pill sm" key={category}>{category}</span>)}</div> : null}
            </>
          ) : null}
        </>
      )}

      <Modal open={Boolean(scriptView)} onClose={() => setScriptView(null)} title={scriptView?.title || 'Script'} subtitle={scriptView ? `${scriptView.niche?.name || 'Global'} · v${scriptView.version} · ${SCRIPT_STATUS_LABELS[scriptView.status] || scriptView.status}` : undefined} size="lg" footer={<><Button onClick={() => setScriptView(null)}>Fechar</Button>{canManage && scriptView ? <Button variant="primary" icon="edit" onClick={() => { setScriptView(null); openScriptForm(scriptView) }}>Editar script</Button> : null}</>}>
        {scriptView ? (
          <div>
            {scriptView.description ? <p className="notes-text" style={{ marginBottom: 14 }}>{scriptView.description}</p> : null}
            {scriptView.sections?.length ? scriptView.sections.map((section, index) => (
              <section className="script-section" key={index}><span className="eyebrow">Seção {index + 1}</span><h4>{section.title}</h4><p>{section.content}</p></section>
            )) : <p className="muted">Nenhuma seção registrada.</p>}
            {canManage ? <div className="form-actions mt-16"><Button variant="danger" size="sm" onClick={() => archiveScript(scriptView)}>Arquivar script</Button></div> : null}
          </div>
        ) : null}
      </Modal>

      <Modal open={scriptFormOpen} onClose={() => { if (!savingScript) setScriptFormOpen(false) }} title={scriptEditing ? 'Editar script' : 'Novo script'} size="lg" footer={<><Button onClick={() => setScriptFormOpen(false)}>Cancelar</Button><Button variant="primary" loading={savingScript} onClick={saveScript}>{scriptEditing ? 'Salvar nova versão' : 'Criar script'}</Button></>}>
        {scriptError ? <div className="login-error" style={{ marginBottom: 13 }}>{scriptError}</div> : null}
        <div className="form-grid">
          <Field label="Título" required className="span-2"><Input value={scriptForm.title} onChange={(event) => setScriptForm({ ...scriptForm, title: event.target.value })} /></Field>
          <Field label="Descrição" className="span-2"><Textarea rows={2} value={scriptForm.description} onChange={(event) => setScriptForm({ ...scriptForm, description: event.target.value })} /></Field>
          <Field label="Nicho"><Select value={scriptForm.niche_id} onChange={(event) => setScriptForm({ ...scriptForm, niche_id: event.target.value })}><option value="">Global</option>{niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}</Select></Field>
          <Field label="Etapa"><Select value={scriptForm.stage} onChange={(event) => setScriptForm({ ...scriptForm, stage: event.target.value })}><option value="">Qualquer etapa</option>{STAGES.map((stage) => <option key={stage.value} value={stage.value}>{stage.label}</option>)}</Select></Field>
          <Field label="Categoria"><Input value={scriptForm.category} onChange={(event) => setScriptForm({ ...scriptForm, category: event.target.value })} placeholder="Ex.: Discovery" /></Field>
          <Field label="Status"><Select value={scriptForm.status} onChange={(event) => setScriptForm({ ...scriptForm, status: event.target.value })}><option value="draft">Rascunho</option><option value="published">Publicado</option><option value="archived">Arquivado</option></Select></Field>
        </div>
        <div className="section-title mt-16">Seções</div>
        {scriptForm.sections.map((section, index) => (
          <div className="script-section-form" key={index}>
            <Input value={section.title} placeholder="Título da seção" onChange={(event) => setScriptForm({ ...scriptForm, sections: scriptForm.sections.map((item, itemIndex) => itemIndex === index ? { ...item, title: event.target.value } : item) })} />
            <Textarea rows={4} value={section.content} placeholder="Conteúdo do script..." onChange={(event) => setScriptForm({ ...scriptForm, sections: scriptForm.sections.map((item, itemIndex) => itemIndex === index ? { ...item, content: event.target.value } : item) })} />
            <IconButton icon="trash" label="Remover seção" disabled={scriptForm.sections.length === 1} onClick={() => setScriptForm({ ...scriptForm, sections: scriptForm.sections.filter((_, itemIndex) => itemIndex !== index) })} />
          </div>
        ))}
        <Button size="sm" icon="plus" onClick={() => setScriptForm({ ...scriptForm, sections: [...scriptForm.sections, { title: '', content: '' }] })}>Adicionar seção</Button>
      </Modal>

      <Modal open={playbookOpen} onClose={() => { if (!savingPlaybook) setPlaybookOpen(false) }} title={playbookEditing ? 'Editar playbook' : 'Novo playbook'} subtitle="Critérios e palavras-chave usados na análise heurística local." size="lg" footer={<><Button onClick={() => setPlaybookOpen(false)}>Cancelar</Button><Button variant="primary" loading={savingPlaybook} onClick={savePlaybook}>{playbookEditing ? 'Salvar nova versão' : 'Criar playbook'}</Button></>}>
        {playbookError ? <div className="login-error" style={{ marginBottom: 13 }}>{playbookError}</div> : null}
        <div className="form-grid">
          <Field label="Nome" required className="span-2"><Input value={playbookForm.name} onChange={(event) => setPlaybookForm({ ...playbookForm, name: event.target.value })} placeholder="Ex.: Playbook de diagnóstico" /></Field>
          <Field label="Descrição" className="span-2"><Textarea rows={2} value={playbookForm.description} onChange={(event) => setPlaybookForm({ ...playbookForm, description: event.target.value })} /></Field>
          <Field label="Nicho"><Select value={playbookForm.niche_id} onChange={(event) => setPlaybookForm({ ...playbookForm, niche_id: event.target.value })}><option value="">Global</option>{niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}</Select></Field>
          <Field label="Status"><Select value={playbookForm.active ? '1' : '0'} onChange={(event) => setPlaybookForm({ ...playbookForm, active: event.target.value === '1' })}><option value="1">Ativo</option><option value="0">Inativo</option></Select></Field>
        </div>
        <div className="section-title mt-16">Critérios</div>
        {playbookForm.criteria.map((criterion, index) => (
          <div className="criterion-form" key={index}>
            <Input value={criterion.key} placeholder="chave" onChange={(event) => setPlaybookForm({ ...playbookForm, criteria: playbookForm.criteria.map((item, itemIndex) => itemIndex === index ? { ...item, key: event.target.value } : item) })} style={{ width: 110 }} />
            <Input value={criterion.label} placeholder="Rótulo" onChange={(event) => setPlaybookForm({ ...playbookForm, criteria: playbookForm.criteria.map((item, itemIndex) => itemIndex === index ? { ...item, label: event.target.value } : item) })} />
            <Input type="number" min={1} max={100} value={criterion.weight} onChange={(event) => setPlaybookForm({ ...playbookForm, criteria: playbookForm.criteria.map((item, itemIndex) => itemIndex === index ? { ...item, weight: Number(event.target.value) } : item) })} style={{ width: 75 }} />
            <Input value={criterion.keywords} placeholder="palavras-chave separadas por vírgula" onChange={(event) => setPlaybookForm({ ...playbookForm, criteria: playbookForm.criteria.map((item, itemIndex) => itemIndex === index ? { ...item, keywords: event.target.value } : item) })} />
            <IconButton icon="trash" label="Remover critério" disabled={playbookForm.criteria.length === 1} onClick={() => setPlaybookForm({ ...playbookForm, criteria: playbookForm.criteria.filter((_, itemIndex) => itemIndex !== index) })} />
          </div>
        ))}
        <Button size="sm" icon="plus" onClick={() => setPlaybookForm({ ...playbookForm, criteria: [...playbookForm.criteria, { key: '', label: '', weight: 5, keywords: '' }] })}>Adicionar critério</Button>
      </Modal>

      <Modal open={objectionOpen} onClose={() => { if (!savingObjection) setObjectionOpen(false) }} title={objectionEditing ? 'Editar objeção' : 'Registrar objeção'} size="lg" footer={<><Button onClick={() => setObjectionOpen(false)}>Cancelar</Button><Button variant="primary" loading={savingObjection} onClick={saveObjection}>{objectionEditing ? 'Salvar' : 'Registrar'}</Button></>}>
        {objectionError ? <div className="login-error" style={{ marginBottom: 13 }}>{objectionError}</div> : null}
        <div className="form-grid">
          <Field label="Objeção" required className="span-2"><Input value={objectionForm.label} onChange={(event) => setObjectionForm({ ...objectionForm, label: event.target.value })} placeholder="Ex.: Está caro / preciso falar com meu sócio" /></Field>
          <Field label="Categoria"><Input value={objectionForm.category} onChange={(event) => setObjectionForm({ ...objectionForm, category: event.target.value })} placeholder="Preço, tempo, confiança..." /></Field>
          <Field label="Nicho"><Select value={objectionForm.niche_id} onChange={(event) => setObjectionForm({ ...objectionForm, niche_id: event.target.value })}><option value="">Global</option>{niches.map((niche) => <option key={niche.id} value={niche.id}>{niche.name}</option>)}</Select></Field>
          <Field label="Etapa"><Select value={objectionForm.stage} onChange={(event) => setObjectionForm({ ...objectionForm, stage: event.target.value })}><option value="">Qualquer etapa</option>{STAGES.map((stage) => <option key={stage.value} value={stage.value}>{stage.label}</option>)}</Select></Field>
          <Field label="Resultado"><Input value={objectionForm.result} onChange={(event) => setObjectionForm({ ...objectionForm, result: event.target.value })} placeholder="Contornada, perdida..." /></Field>
          <Field label="Vezes levantada"><Input type="number" min={0} value={objectionForm.times_raised} onChange={(event) => setObjectionForm({ ...objectionForm, times_raised: event.target.value })} /></Field>
          <Field label="Vezes resolvida"><Input type="number" min={0} value={objectionForm.times_resolved} onChange={(event) => setObjectionForm({ ...objectionForm, times_resolved: event.target.value })} /></Field>
          <Field label="Melhor resposta" className="span-2"><Textarea rows={3} value={objectionForm.best_rebuttal} onChange={(event) => setObjectionForm({ ...objectionForm, best_rebuttal: event.target.value })} /></Field>
          <Field label="O que funcionou" className="span-2"><Textarea rows={2} value={objectionForm.what_worked} onChange={(event) => setObjectionForm({ ...objectionForm, what_worked: event.target.value })} /></Field>
        </div>
      </Modal>

      <ConfirmDialog open={Boolean(deleteObjection)} title="Remover objeção" message={`Remover "${deleteObjection?.label}"? O histórico comercial dessa objeção será perdido.`} confirmLabel="Remover" onClose={() => setDeleteObjection(null)} onConfirm={removeObjection} />
    </div>
  )
}
