export type Role = 'admin' | 'manager' | 'closer' | 'sdr'

export interface Permissions {
  manage_users: boolean
  manage_settings: boolean
  view_financials: boolean
  approve_commissions: boolean
  manage_playbooks: boolean
  view_all_leads: boolean
  control_docker: boolean
}

export interface User {
  id: number
  name: string
  email: string
  phone?: string | null
  role: Role
  role_label?: string
  active: boolean
  avatar_color?: string | null
  team_id?: number | null
  team?: { id: number; name: string } | null
  last_login_at?: string | null
  permissions: Permissions
}

export interface Team {
  id: number
  name: string
  slug: string
  description?: string | null
  active: boolean
  users_count?: number
}

export interface Niche {
  id: string
  name: string
  slug: string
  description?: string | null
  color?: string | null
  active: boolean
  leads_count?: number
}

export interface PaymentSource {
  id: string
  slug: string
  name: string
  enters_cash: boolean
  active: boolean
  position: number
}

export interface Lead {
  id: string
  name: string
  company?: string | null
  phone?: string | null
  whatsapp?: string | null
  email?: string | null
  niche_id?: string | null
  niche?: Niche | null
  origin?: string | null
  stage: string
  temperature: 'cold' | 'warm' | 'hot'
  priority: number
  position: number
  closer_id?: number | null
  closer?: User | null
  pre_sales_id?: number | null
  preSales?: User | null
  pre_sales?: User | null
  ticket_estimado?: string | number | null
  valor_negociado?: string | number | null
  ticket_fechado?: string | number | null
  receita_prevista?: string | number | null
  first_contact_at?: string | null
  last_interaction_at?: string | null
  next_action_at?: string | null
  meeting_at?: string | null
  last_stage_change_at?: string | null
  next_steps?: string[] | null
  loss_reason?: string | null
  disqualify_reason?: string | null
  saldo_devedor?: string | number | null
  notes?: string | null
  commission_percentage?: string | number | null
  commission_amount?: string | number | null
  created_at: string
  updated_at: string
  interactions?: LeadInteraction[]
  stage_histories?: LeadStageHistory[]
  reminders?: Reminder[]
  attachments?: Attachment[]
  proposals?: Proposal[]
  contracts?: Contract[]
  payments?: Payment[]
  calls?: Call[]
  commissions?: Commission[]
  meta?: { can_edit?: boolean }
}

export interface LeadInteraction {
  id: string
  kind: string
  occurred_at: string
  author?: string | null
  summary: string
  user?: { id: number; name: string } | null
  created_at: string
}

export interface LeadStageHistory {
  id: string
  from_stage?: string | null
  to_stage: string
  reason?: string | null
  created_at: string
  user?: { id: number; name: string } | null
}

export interface Attachment {
  id: string
  kind: string
  file_name: string
  size_kb: number
  mime_type?: string | null
  description?: string | null
  uploaded_at?: string | null
  uploaded_by?: { id: number; name: string } | null
  created_at?: string
}

export interface Reminder {
  id: string
  lead_id?: string | null
  lead?: { id: string; name: string; company?: string | null; stage?: string; temperature?: string } | null
  type: string
  title: string
  detail?: string | null
  due_at: string
  status: 'pendente' | 'concluido' | 'cancelado'
  priority: number
  origin?: string
  rule_key?: string | null
  rescheduled_count?: number
  responsible_id?: number | null
  responsible?: { id: number; name: string } | null
  completed_at?: string | null
  created_at: string
}

export interface Call {
  id: string
  lead_id?: string | null
  lead?: { id: string; name: string; company?: string | null; stage?: string } | null
  closer_id?: number | null
  closer?: { id: number; name: string } | null
  title: string
  status: 'scheduled' | 'completed' | 'no_show' | 'cancelled'
  started_at?: string | null
  duration_sec: number
  transcript?: { text?: string; lines?: Array<{ speaker?: string; text?: string }> } | null
  summary?: string | null
  spin?: Record<string, any> | null
  objections?: any[] | null
  talk_time_closer?: number | null
  talk_time_lead?: number | null
  adherence_score?: number | null
  playbook_score?: number | null
  analysis?: any | null
  analysis_status: string
  ai_provider?: string | null
  analyzed_at?: string | null
  sentiment?: any
  highlights?: any[] | null
  extracted_steps?: any[] | null
  created_at: string
}

export interface ProposalItem {
  description: string
  quantity: number
  unit_price: number
}

export interface Proposal {
  id: string
  lead_id: string
  lead?: { id: string; name: string; company?: string | null; email?: string | null; phone?: string | null } | null
  number: string
  title: string
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired'
  valid_until?: string | null
  items: ProposalItem[]
  subtotal: string | number
  discount: string | number
  total: string | number
  payment_terms?: string | null
  clauses?: string[] | null
  notes?: string | null
  version: number
  sent_at?: string | null
  accepted_at?: string | null
  rejected_at?: string | null
  created_at: string
  created_by?: { id: number; name: string } | null
  versions?: Array<{ id: string; version: number; created_at: string }>
  payments?: Payment[]
}

export interface Contract {
  id: string
  lead_id: string
  lead?: { id: string; name: string; company?: string | null } | null
  proposal_id?: string | null
  proposal?: { id: string; number: string; title?: string } | null
  number: string
  title: string
  status: 'draft' | 'pending_signature' | 'signed' | 'active' | 'completed' | 'cancelled'
  total: string | number
  content?: string
  signed_at?: string | null
  start_date?: string | null
  end_date?: string | null
  created_at: string
  payments?: Payment[]
}

export interface Payment {
  id: string
  lead_id: string
  lead?: { id: string; name: string; company?: string | null } | null
  contract_id?: string | null
  contract?: { id: string; number: string } | null
  proposal_id?: string | null
  proposal?: { id: string; number: string } | null
  kind: string
  installment_number: number
  amount: string | number
  due_at: string
  paid_at?: string | null
  status: 'pending' | 'paid' | 'overdue' | 'cancelled'
  method?: string | null
  enters_cash?: boolean
  notes?: string | null
  next_action?: string | null
  next_action_at?: string | null
  created_at: string
}

export interface Commission {
  id: string
  lead_id: string
  lead?: { id: string; name: string; company?: string | null } | null
  user_id: number
  user?: { id: number; name: string } | null
  rule_id?: string | null
  rule?: { id: string; name: string } | null
  rule_snapshot?: Record<string, any>
  sale_amount: string | number
  received_amount: string | number
  percentage: string | number
  fixed_amount?: string | number | null
  amount: string | number
  status: 'estimated' | 'calculated' | 'approved' | 'paid' | 'reversed'
  period: string
  notes?: string | null
  calculated_at?: string | null
  approved_at?: string | null
  paid_at?: string | null
  reversed_at?: string | null
  created_at: string
}

export interface CommissionRule {
  id: string
  name: string
  scope: 'global' | 'user' | 'team' | 'niche'
  user_id?: number | null
  team_id?: number | null
  niche_id?: string | null
  user?: { id: number; name: string } | null
  team?: { id: number; name: string } | null
  niche?: { id: string; name: string } | null
  percentage?: string | number | null
  fixed_amount?: string | number | null
  basis: 'contracted' | 'received'
  active: boolean
  priority: number
}

export interface ScriptSection {
  title: string
  content: string
}

export interface Script {
  id: string
  niche_id?: string | null
  niche?: { id: string; name: string; color?: string } | null
  title: string
  description?: string | null
  stage?: string | null
  category?: string | null
  sections?: ScriptSection[] | null
  version: number
  status: 'draft' | 'published' | 'archived'
  playbook_id?: number | null
  playbook?: { id: number; name: string } | null
  updated_by?: { id: number; name: string } | null
  updated_at: string
}

export interface PlaybookCriterion {
  key: string
  label: string
  weight: number
  keywords: string[]
}

export interface Playbook {
  id: number
  niche_id?: string | null
  niche?: { id: string; name: string } | null
  name: string
  description?: string | null
  criteria: PlaybookCriterion[]
  version: number
  active: boolean
}

export interface Objection {
  id: string
  niche_id?: string | null
  niche?: { id: string; name: string } | null
  label: string
  category?: string | null
  stage?: string | null
  context?: string | null
  times_raised: number
  times_resolved: number
  what_worked?: string | null
  best_rebuttal?: string | null
  result?: string | null
  closer?: { id: number; name: string } | null
  last_seen_at?: string | null
  created_at: string
  resolution_rate?: number | null
}

export interface Dashboard {
  period: { from: string; to: string }
  rules: Record<string, string>
  leads_total: number
  leads_created: number
  leads_by_stage: Record<string, number>
  stage_totals: Record<string, { count: number; value: number }>
  closed: number
  lost: number
  disqualified: number
  conversion_rate: number
  disqualification_rate: number
  ticket_medio: number
  cycle_days: number | null
  revenue: {
    forecast: number
    contracted: number
    received: number
    pending: number
    overdue: number
    debtor_balance: number
    commissions: number
  }
  meetings: {
    total: number
    completed: number
    scheduled: number
    no_show: number
    cancelled: number
    attendance_rate: number | null
    by_day: Array<{ date: string; completed: number; scheduled: number; no_show: number; cancelled: number }>
  }
  meetings_today: {
    total: number
    closed: number
    loss: number
  }
  by_niche: Array<{
    id: string
    name: string
    color?: string
    leads: number
    closed: number
    revenue: number
    conversion: number
  }>
  ranking: Array<{
    id: number
    name: string
    leads: number
    leads_created: number
    meetings_completed: number
    attendance_rate: number | null
    closed: number
    conversion: number
    revenue: number
    average_ticket: number
    cycle_days: number | null
    commission: number
  }>
  conversion_by_stage: Record<string, number>
  commissions_available: boolean
}

export interface LossAnalysis {
  period: { from: string; to: string }
  totals: {
    loss: number
    mal_qualificado: number
    estimated_value: number
    average_days_to_loss: number
  }
  loss_reasons: Array<{ reason: string; count: number; value: number }>
  disqualify_reasons: Array<{ reason: string; count: number; value: number }>
  by_niche: Array<{ name: string; count: number }>
  by_closer: Array<{ name: string; count: number }>
  by_origin: Array<{ name: string; count: number }>
  caveat: string
}

export interface ReportRun {
  id: number
  type: string
  period_start: string
  period_end: string
  status: string
  metrics?: any
  created_at: string
}

export interface AuditLog {
  id: number
  action: string
  entity_type?: string | null
  entity_id?: string | null
  old_values?: Record<string, any> | null
  new_values?: Record<string, any> | null
  metadata?: Record<string, any> | null
  ip_address?: string | null
  created_at: string
  user?: { id: number; name: string } | null
}

export interface Integration {
  provider: string
  label: string
  description: string
  enabled: boolean
  status: string
  environment_configured: boolean
  required_environment: string[]
  config: Record<string, any>
  last_checked_at?: string | null
  capability: string
}

export interface DockerStatus {
  available: boolean
  reason?: string | null
  services: Array<{ name: string; state?: string; health?: string; status?: string }>
  agent?: { name?: string; version?: string }
}

export interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from?: number
  to?: number
}
