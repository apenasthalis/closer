export const STAGES = [
  { value: 'lead_agendado', label: 'Lead Agendado', color: 'var(--c2)' },
  { value: 'fup', label: 'FUP', color: 'var(--c1)' },
  { value: 'remarcado', label: 'Remarcado', color: 'var(--c3)' },
  { value: 'negociacao', label: 'Negociação', color: 'var(--c4)' },
  { value: 'fechado', label: 'Fechado', color: 'var(--brand)' },
  { value: 'loss', label: 'Loss', color: 'var(--c5)' },
  { value: 'mal_qualificado', label: 'Mal Qualificado', color: 'var(--muted)' },
] as const

export const STAGE_LABELS: Record<string, string> = Object.fromEntries(STAGES.map((stage) => [stage.value, stage.label]))

export const TEMPERATURES = [
  { value: 'hot', label: 'Quente' },
  { value: 'warm', label: 'Morno' },
  { value: 'cold', label: 'Frio' },
] as const

export const TEMPERATURE_LABELS: Record<string, string> = { hot: 'Quente', warm: 'Morno', cold: 'Frio' }

export const REMINDER_TYPES = [
  { value: 'follow_up', label: 'Follow-up' },
  { value: 'proposta', label: 'Proposta' },
  { value: 'negociacao', label: 'Negociação' },
  { value: 'cobranca', label: 'Cobrança' },
  { value: 'parcela', label: 'Parcela' },
  { value: 'confirmacao_reuniao', label: 'Confirmação de reunião' },
  { value: 'remarcacao', label: 'Remarcação' },
  { value: 'no_show', label: 'No-show' },
  { value: 'contrato', label: 'Contrato' },
  { value: 'tarefa', label: 'Tarefa' },
  { value: 'ia', label: 'Sugestão de IA' },
  { value: 'outro', label: 'Outro' },
] as const

export const REMINDER_LABELS: Record<string, string> = Object.fromEntries(REMINDER_TYPES.map((type) => [type.value, type.label]))

export const INTERACTION_KINDS = [
  { value: 'nota', label: 'Nota' },
  { value: 'ligacao', label: 'Ligação' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'E-mail' },
  { value: 'reuniao', label: 'Reunião' },
  { value: 'proposta', label: 'Proposta' },
  { value: 'contrato', label: 'Contrato' },
  { value: 'pagamento', label: 'Pagamento' },
  { value: 'outro', label: 'Outro' },
] as const

export const CALL_STATUS = [
  { value: 'scheduled', label: 'Agendada' },
  { value: 'completed', label: 'Concluída' },
  { value: 'no_show', label: 'No-show' },
  { value: 'cancelled', label: 'Cancelada' },
] as const

export const CALL_STATUS_LABELS: Record<string, string> = Object.fromEntries(CALL_STATUS.map((status) => [status.value, status.label]))

export const ORIGINS = ['Indicação', 'Instagram', 'Google', 'WhatsApp', 'Mercado Livre', 'Evento', 'Prospecção ativa', 'Outro']

export const PROPOSAL_STATUS: Record<string, string> = {
  draft: 'Rascunho',
  sent: 'Enviada',
  accepted: 'Aceita',
  rejected: 'Rejeitada',
  expired: 'Expirada',
}

export const CONTRACT_STATUS: Record<string, string> = {
  draft: 'Rascunho',
  pending_signature: 'Aguardando assinatura',
  signed: 'Assinado',
  active: 'Ativo',
  completed: 'Concluído',
  cancelled: 'Cancelado',
}

export const PAYMENT_STATUS: Record<string, string> = {
  pending: 'Pendente',
  paid: 'Pago',
  overdue: 'Atrasado',
  cancelled: 'Cancelado',
}

export const COMMISSION_STATUS: Record<string, string> = {
  estimated: 'Estimada',
  calculated: 'Calculada',
  approved: 'Aprovada',
  paid: 'Paga',
  reversed: 'Estornada',
}

export const PAYMENT_METHODS: Record<string, string> = {
  pix: 'Pix',
  boleto: 'Boleto',
  credit_card: 'Cartão de crédito',
  debit_card: 'Cartão de débito',
  transfer: 'Transferência',
  cash: 'Dinheiro',
  other: 'Outro',
}

export const NICHE_COLORS = ['#3fa373', '#4f8fd8', '#9b7fd8', '#d8a34f', '#c76b7f', '#58a6a6', '#b2a35d']
