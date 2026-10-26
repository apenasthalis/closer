import { useState } from 'react'
import { api, apiMessage } from '@/lib/api'
import { useApi } from '@/lib/hooks'
import { dateTime } from '@/lib/format'
import type { Integration } from '@/lib/types'
import { Badge, Button, ErrorState, Icon, LoadingState, Panel, useToast } from '@/components/ui'

interface SettingsResponse {
  data: {
    system: Record<string, string>
    integrations: Integration[]
  }
}

const statusLabels: Record<string, { label: string; tone: string }> = {
  configured: { label: 'Pronta', tone: 'brand' },
  not_configured: { label: 'Não configurada', tone: 'neutral' },
  disabled: { label: 'Desabilitada', tone: 'amber' },
  error: { label: 'Erro', tone: 'rose' },
}

export function SettingsPage() {
  const { notify } = useToast()
  const { data, loading, error, reload } = useApi<SettingsResponse>('/settings')
  const [busy, setBusy] = useState('')
  const settings = data?.data
  const system = settings?.system
  const integrations = settings?.integrations || []

  const toggle = async (integration: Integration) => {
    setBusy(integration.provider)
    try {
      await api.put(`/settings/integrations/${integration.provider}`, {
        enabled: !integration.enabled,
        config: integration.config || {},
      })
      notify(integration.enabled ? 'Integração desabilitada.' : 'Integração atualizada.')
      reload()
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setBusy('')
    }
  }

  const test = async (integration: Integration) => {
    setBusy(integration.provider)
    try {
      const response = await api.post(`/settings/integrations/${integration.provider}/test`)
      notify(response.data?.message || 'Teste executado.')
    } catch (requestError) {
      notify(apiMessage(requestError), 'error')
    } finally {
      setBusy('')
    }
  }

  if (error) return <ErrorState message={error} onRetry={reload} />
  if (loading && !data) return <LoadingState />

  return (
    <div>
      <div className="page-toolbar">
        <div className="page-toolbar-left"><span className="muted small">Status técnico e preferências de integração. Nenhuma credencial secreta é exibida ou salva por aqui.</span></div>
        <Button icon="refresh" onClick={reload}>Atualizar</Button>
      </div>

      <div className="grid-main-side">
        <div className="stack">
          <Panel title="Integrações" subtitle="O sistema informa o estado real. Conectores externos não implementados permanecem identificados.">
            <div className="integration-list">
              {integrations.map((integration) => {
                const status = statusLabels[integration.status] || { label: integration.status, tone: 'neutral' }
                return (
                  <article className="integration-card" key={integration.provider}>
                    <div className="integration-icon"><Icon name={integration.provider === 'whatsapp_cloud' ? 'whatsapp' : integration.provider === 'google_calendar' ? 'calendar' : integration.provider === 'ai' ? 'sparkle' : 'mail'} size={18} /></div>
                    <div className="integration-main">
                      <div className="integration-title"><b>{integration.label}</b><Badge tone={status.tone}>{status.label}</Badge>{integration.enabled ? <Badge tone="blue">Habilitada</Badge> : null}</div>
                      <p>{integration.description}</p>
                      <div className="integration-details">
                        <span><Icon name={integration.environment_configured ? 'checkCircle' : 'alert'} size={11} />Ambiente {integration.environment_configured ? 'configurado' : 'pendente'}</span>
                        <span>Variáveis: {integration.required_environment.join(', ')}</span>
                        {integration.last_checked_at ? <span>Verificado em {dateTime(integration.last_checked_at)}</span> : null}
                      </div>
                      <div className="callout" style={{ marginTop: 8 }}>{integration.capability}</div>
                    </div>
                    <div className="integration-actions">
                      <Button size="sm" loading={busy === integration.provider} onClick={() => toggle(integration)}>{integration.enabled ? 'Desabilitar' : 'Habilitar'}</Button>
                      <Button size="sm" variant="ghost" loading={busy === integration.provider} onClick={() => test(integration)}>Testar</Button>
                    </div>
                  </article>
                )
              })}
            </div>
          </Panel>
        </div>

        <div className="stack">
          <Panel title="Ambiente do sistema" subtitle="Configuração efetiva do backend.">
            <div className="kv"><span>Aplicação</span><b>{system?.app_name}</b></div>
            <div className="kv"><span>Ambiente</span><b>{system?.environment}</b></div>
            <div className="kv"><span>Fuso horário</span><b>{system?.timezone}</b></div>
            <div className="kv"><span>Fila</span><b>{system?.queue}</b></div>
            <div className="kv"><span>Cache</span><b>{system?.cache}</b></div>
            <div className="kv"><span>Mailer</span><b>{system?.mailer}</b></div>
          </Panel>

          <Panel title="Segurança de credenciais" subtitle="Política aplicada pelo backend.">
            <div className="callout warn">
              Chaves, tokens, senhas e secrets não podem ser gravados pelo painel. Eles devem existir apenas em variáveis de ambiente do servidor. O endpoint rejeita campos com nomes sensíveis.
            </div>
            <div className="callout mt-8">
              Desabilitar uma integração mantém as variáveis de ambiente intactas. Nenhum envio ou sincronização simulada é realizado quando o conector não está implementado.
            </div>
          </Panel>
        </div>
      </div>
    </div>
  )
}
