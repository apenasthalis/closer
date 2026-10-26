import { useState } from 'react'
import { useApi, useLookups } from '@/lib/hooks'
import { dateTime } from '@/lib/format'
import type { AuditLog, Paginated } from '@/lib/types'
import { Badge, Button, EmptyState, ErrorState, Field, Input, LoadingState, Modal, Pagination, Select } from '@/components/ui'

function pretty(value: unknown) {
  if (value === null || value === undefined) return '—'
  return JSON.stringify(value, null, 2)
}

export function AuditPage() {
  const { users } = useLookups()
  const [userId, setUserId] = useState('')
  const [action, setAction] = useState('')
  const [entityType, setEntityType] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<AuditLog | null>(null)
  const { data, loading, error, reload } = useApi<Paginated<AuditLog>>('/audit-logs', {
    user_id: userId || undefined,
    action: action || undefined,
    entity_type: entityType || undefined,
    from: from || undefined,
    to: to || undefined,
    page,
  })
  const logs = data?.data || []

  const reset = () => {
    setUserId('')
    setAction('')
    setEntityType('')
    setFrom('')
    setTo('')
    setPage(1)
  }

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left">
          <Input value={action} onChange={(event) => { setAction(event.target.value); setPage(1) }} placeholder="Ação (ex.: lead.)" style={{ width: 160 }} />
          <Input value={entityType} onChange={(event) => { setEntityType(event.target.value); setPage(1) }} placeholder="Entidade" style={{ width: 140 }} />
          <Select value={userId} onChange={(event) => { setUserId(event.target.value); setPage(1) }} style={{ width: 150 }}>
            <option value="">Todos os usuários</option>
            {users.map((user) => <option key={user.id} value={user.id}>{user.name}</option>)}
          </Select>
          <label className="pill" style={{ gap: 6 }}><span>De</span><input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="bare-input" /></label>
          <label className="pill" style={{ gap: 6 }}><span>Até</span><input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="bare-input" /></label>
          <Button size="sm" variant="ghost" onClick={reset}>Limpar</Button>
        </div>
        <Button icon="refresh" onClick={reload}>Atualizar</Button>
      </div>

      {error ? <ErrorState message={error} onRetry={reload} /> : loading && !data ? <LoadingState /> : !logs.length ? (
        <div className="panel panel-padded"><EmptyState icon="audit" title="Nenhum registro" description="Ajuste os filtros ou execute ações no CRM para gerar trilha de auditoria." /></div>
      ) : (
        <>
          <div className="table-wrap">
            <table className="table">
              <thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>IP</th><th /></tr></thead>
              <tbody>
                {logs.map((log) => (
                  <tr className="row-link" key={log.id} onClick={() => setSelected(log)}>
                    <td>{dateTime(log.created_at)}</td>
                    <td>{log.user?.name || <span className="muted">Sistema</span>}</td>
                    <td><Badge tone={log.action.includes('created') ? 'green' : log.action.includes('deleted') || log.action.includes('deactivated') ? 'rose' : log.action.includes('updated') || log.action.includes('changed') ? 'amber' : 'neutral'}>{log.action}</Badge></td>
                    <td>{log.entity_type ? `${log.entity_type.split('\\').pop()}${log.entity_id ? ` · ${log.entity_id}` : ''}` : '—'}</td>
                    <td className="mono">{log.ip_address || '—'}</td>
                    <td className="text-right"><span className="muted small">Ver detalhes</span></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={data?.current_page || 1} lastPage={data?.last_page || 1} total={data?.total || logs.length} onChange={setPage} />
        </>
      )}

      <Modal open={Boolean(selected)} onClose={() => setSelected(null)} title="Detalhes da auditoria" subtitle={selected ? `${selected.action} · ${dateTime(selected.created_at)}` : undefined} size="lg" footer={<Button onClick={() => setSelected(null)}>Fechar</Button>}>
        {selected ? (
          <div className="audit-detail">
            <div className="kv"><span>Usuário</span><b>{selected.user?.name || 'Sistema'}</b></div>
            <div className="kv"><span>Entidade</span><b>{selected.entity_type || '—'}</b></div>
            <div className="kv"><span>ID da entidade</span><b className="mono">{selected.entity_id || '—'}</b></div>
            <div className="kv"><span>IP</span><b className="mono">{selected.ip_address || '—'}</b></div>
            <div className="audit-json-grid">
              <Field label="Valores anteriores"><pre>{pretty(selected.old_values)}</pre></Field>
              <Field label="Valores novos"><pre>{pretty(selected.new_values)}</pre></Field>
            </div>
            <Field label="Metadados"><pre>{pretty(selected.metadata)}</pre></Field>
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
