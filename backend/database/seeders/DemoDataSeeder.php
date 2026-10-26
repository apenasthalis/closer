<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\Commission;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\LeadStageHistory;
use App\Models\Niche;
use App\Models\Objection;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Reminder;
use App\Models\Script;
use App\Models\User;
use App\Services\CallAnalysisService;
use App\Services\CommissionService;
use Illuminate\Database\Seeder;
use Throwable;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') || Lead::query()->exists()) {
            return;
        }

        $admin = User::query()->where('email', 'admin@closer.local')->first()
            ?? User::query()->where('role', User::ROLE_ADMIN)->first();

        if (! $admin) {
            return;
        }

        $closers = User::query()->whereIn('email', ['caio@closer.local', 'thalis@closer.local', '1thalisgabriel1@gmail.com'])->get();
        $caio = $closers->firstWhere('email', 'caio@closer.local') ?? $closers->first() ?? $admin;
        $thalis = $closers->firstWhere('email', 'thalis@closer.local') ?? $closers->first() ?? $admin;
        $otherCloser = $closers->firstWhere('email', '1thalisgabriel1@gmail.com') ?? $caio;
        $sdr = User::query()->where('email', 'leticia@closer.local')->first() ?? $admin;
        $niches = Niche::query()->get()->keyBy('slug');
        $now = now();

        $definitions = [
            [
                'name' => 'Marina Costa', 'company' => 'Costa Store', 'email' => 'marina@costastore.com.br',
                'phone' => '(11) 98888-1010', 'niche' => 'mercado_livre', 'stage' => 'lead_agendado',
                'temperature' => 'hot', 'ticket' => 4500, 'closer' => $caio, 'days' => 2, 'origin' => 'Indicação',
            ],
            [
                'name' => 'Rafael Alves', 'company' => 'Rafael Alves Mentoria', 'email' => 'rafael@alves.com.br',
                'phone' => '(21) 97777-2020', 'niche' => 'mente_renovada', 'stage' => 'fup',
                'temperature' => 'warm', 'ticket' => 6800, 'closer' => $thalis, 'days' => 6, 'origin' => 'Instagram',
            ],
            [
                'name' => 'Camila Nunes', 'company' => 'Studio Camila Nunes', 'email' => 'camila@studionunes.com',
                'phone' => '(31) 96666-3030', 'niche' => 'visagismo', 'stage' => 'remarcado',
                'temperature' => 'hot', 'ticket' => 3200, 'closer' => $thalis, 'days' => 4, 'origin' => 'Google',
            ],
            [
                'name' => 'Eduardo Lima', 'company' => 'Lima Engenharia', 'email' => 'eduardo@limaengenharia.com',
                'phone' => '(41) 95555-4040', 'niche' => 'construcao_civil', 'stage' => 'negociacao',
                'temperature' => 'hot', 'ticket' => 12000, 'closer' => $caio, 'days' => 8, 'origin' => 'Outbound',
            ],
            [
                'name' => 'Patrícia Souza', 'company' => 'Souza Imóveis', 'email' => 'patricia@souzaimoveis.com',
                'phone' => '(51) 94444-5050', 'niche' => 'mercado_livre', 'stage' => 'fechado',
                'temperature' => 'hot', 'ticket' => 15000, 'closer' => $caio, 'days' => 12, 'closed_days' => 2, 'origin' => 'Evento',
            ],
            [
                'name' => 'João Pedro', 'company' => 'JP Wellness', 'email' => 'joao@jpwellness.com',
                'phone' => '(61) 93333-6060', 'niche' => 'mente_renovada', 'stage' => 'fechado',
                'temperature' => 'warm', 'ticket' => 8900, 'closer' => $thalis, 'days' => 15, 'closed_days' => 5, 'origin' => 'Indicação',
            ],
            [
                'name' => 'Fernanda Rocha', 'company' => 'Rocha Arquitetura', 'email' => 'fernanda@rochaarq.com',
                'phone' => '(71) 92222-7070', 'niche' => 'construcao_civil', 'stage' => 'loss',
                'temperature' => 'cold', 'ticket' => 5200, 'closer' => $otherCloser, 'days' => 10,
                'closed_days' => 3, 'origin' => 'Site', 'reason' => 'Sem orçamento disponível neste trimestre.',
            ],
            [
                'name' => 'Ricardo Dias', 'company' => 'Dias Consultoria', 'email' => 'ricardo@diasconsult.com',
                'phone' => '(81) 91111-8080', 'niche' => 'visagismo', 'stage' => 'mal_qualificado',
                'temperature' => 'cold', 'ticket' => 1500, 'closer' => $caio, 'days' => 7,
                'closed_days' => 1, 'origin' => 'Google', 'reason' => 'Não possui operação comercial compatível.',
            ],
            [
                'name' => 'Aline Martins', 'company' => 'Aline Beauty', 'email' => 'aline@alinebeauty.com',
                'phone' => '(85) 98888-9090', 'niche' => 'visagismo', 'stage' => 'lead_agendado',
                'temperature' => 'hot', 'ticket' => 7500, 'closer' => $thalis, 'days' => 1, 'origin' => 'Instagram',
            ],
            [
                'name' => 'Bruno Carvalho', 'company' => 'Carvalho Digital', 'email' => 'bruno@carvalhodigital.com',
                'phone' => '(48) 97777-0101', 'niche' => 'mercado_livre', 'stage' => 'negociacao',
                'temperature' => 'warm', 'ticket' => 11000, 'closer' => $otherCloser, 'days' => 9, 'origin' => 'Indicação',
            ],
        ];

        $leads = [];

        foreach ($definitions as $index => $definition) {
            $createdAt = $now->copy()->subDays($definition['days']);
            $stageDate = isset($definition['closed_days'])
                ? $now->copy()->subDays($definition['closed_days'])
                : $now->copy()->subHours($index + 1);

            /** @var Lead $lead */
            $lead = Lead::query()->create([
                'name' => $definition['name'],
                'company' => $definition['company'],
                'email' => $definition['email'],
                'phone' => $definition['phone'],
                'whatsapp' => $definition['phone'],
                'niche_id' => $niches->get($definition['niche'])?->getKey(),
                'origin' => $definition['origin'],
                'stage' => $definition['stage'],
                'temperature' => $definition['temperature'],
                'priority' => $definition['temperature'] === 'hot' ? 8 : 4,
                'position' => $index,
                'closer_id' => $definition['closer']->getKey(),
                'pre_sales_id' => $sdr->getKey(),
                'ticket_estimado' => $definition['ticket'],
                'valor_negociado' => in_array($definition['stage'], ['negociacao', 'fechado'], true) ? $definition['ticket'] : null,
                'ticket_fechado' => $definition['stage'] === 'fechado' ? $definition['ticket'] : null,
                'receita_prevista' => $definition['stage'] === 'fechado' ? $definition['ticket'] : $definition['ticket'] * 0.8,
                'saldo_devedor' => $definition['stage'] === 'fechado' ? $definition['ticket'] : null,
                'first_contact_at' => $createdAt,
                'last_interaction_at' => $now->copy()->subHours(4 + $index),
                'next_action_at' => in_array($definition['stage'], ['lead_agendado', 'fup', 'remarcado', 'negociacao'], true)
                    ? $now->copy()->addDays($index % 4 + 1)->setTime(9 + ($index % 6), 0)
                    : null,
                'last_stage_change_at' => $stageDate,
                'loss_reason' => $definition['reason'] ?? null,
                'disqualify_reason' => isset($definition['reason']) && $definition['stage'] === 'mal_qualificado' ? $definition['reason'] : null,
                'next_steps' => ['Confirmar diagnóstico', 'Enviar resumo da conversa'],
                'notes' => 'Lead de demonstração criado para validar o fluxo comercial.',
            ]);

            $lead->forceFill(['created_at' => $createdAt, 'updated_at' => $now])->saveQuietly();

            LeadStageHistory::query()->create([
                'lead_id' => $lead->getKey(),
                'from_stage' => null,
                'to_stage' => $definition['stage'],
                'reason' => 'Carga de demonstração',
                'user_id' => $definition['closer']->getKey(),
            ]);

            LeadInteraction::query()->create([
                'lead_id' => $lead->getKey(),
                'kind' => 'nota',
                'occurred_at' => $now->copy()->subHours(4 + $index),
                'author' => $sdr->name,
                'user_id' => $sdr->getKey(),
                'summary' => 'Contato inicial realizado e contexto comercial registrado.',
            ]);

            $leads[$definition['name']] = $lead;
        }

        $this->seedCalls($leads, $caio, $thalis, $now);
        $this->seedReminders($leads, $caio, $thalis, $now);
        $this->seedProposalsAndContracts($leads, $admin, $caio, $now);
        $this->seedLibrary($niches, $admin, $caio);
        $this->seedCommissions($leads, $now);
    }

    private function seedCalls(array $leads, User $caio, User $thalis, $now): void
    {
        $definitions = [
            [
                'lead' => 'Patrícia Souza', 'closer' => $caio, 'status' => 'completed',
                'started_at' => $now->copy()->subDays(2)->setTime(14, 0), 'duration' => 1860,
                'transcript' => implode("\n", [
                    'Closer: Olá Patrícia, obrigado por reservar esse tempo. Nosso objetivo hoje é entender o cenário atual da operação e ver se faz sentido avançarmos juntos.',
                    'Lead: Perfeito. Hoje eu vendo pelo Mercado Livre, mas estou perdendo margem e tenho dificuldade de acompanhar os pedidos.',
                    'Closer: Me conta como funciona o processo hoje. Quantos pedidos vocês recebem por mês e quem cuida dessa operação?',
                    'Lead: São uns 900 pedidos por mês. Eu e duas pessoas cuidamos de tudo, mas não temos processo. O problema é que perdemos tempo e às vezes erramos estoque.',
                    'Closer: Qual é o impacto disso no resultado? Quanto você acha que deixa de faturar ou perde em retrabalho?',
                    'Lead: Acho que perco uns 10% das vendas por falta de controle. Isso me preocupa porque quero crescer para 1.500 pedidos.',
                    'Closer: Se resolvesse esse gargalo, o que mudaria para você nos próximos meses?',
                    'Lead: Eu conseguiria crescer sem contratar tanta gente e teria mais previsibilidade. Isso faria sentido para mim.',
                    'Closer: Entendi. Nossa solução organiza o fluxo comercial e a operação, justamente para reduzir retrabalho e dar visibilidade. O investimento fica em R$ 15 mil, com retorno estimado sobre a margem que você recupera.',
                    'Lead: O valor é mais alto do que eu tinha pensado, mas faz sentido pelo impacto. Como funciona o pagamento?',
                    'Closer: Podemos dividir em três parcelas e começamos com um diagnóstico da operação. Posso enviar a proposta hoje e agendar o próximo passo para quinta.',
                    'Lead: Pode enviar. Vou querer avançar. Agendar quinta está ótimo.',
                ]),
            ],
            [
                'lead' => 'Eduardo Lima', 'closer' => $caio, 'status' => 'completed',
                'started_at' => $now->copy()->subDays(3)->setTime(10, 0), 'duration' => 1440,
                'transcript' => implode("\n", [
                    'Closer: Bom dia Eduardo. Vamos alinhar o objetivo da call: entender a operação da Lima Engenharia e os critérios para uma proposta.',
                    'Lead: Bom dia. Hoje temos obras atrasando e dificuldade de previsibilidade de caixa. Isso é o maior desafio.',
                    'Closer: Entendi. Me conta como funciona hoje e qual é o impacto financeiro de um atraso.',
                    'Lead: Cada obra atrasa em média 15 dias. Isso gera custo extra e atrasa o recebimento. Acho que perco uns R$ 20 mil por obra.',
                    'Closer: Se conseguíssemos organizar essa previsibilidade, o que mudaria?',
                    'Lead: Eu teria margem para investir em novas obras e reduziria a pressão no caixa.',
                    'Closer: Faz sentido. Vou preparar uma proposta com diagnóstico e implantação. O investimento sugerido é R$ 12 mil.',
                    'Lead: Preciso avaliar com meu sócio. Vou pensar e retorno depois.',
                ]),
            ],
            [
                'lead' => 'Aline Martins', 'closer' => $thalis, 'status' => 'scheduled',
                'started_at' => $now->copy()->addDay()->setTime(11, 0), 'duration' => 0,
                'transcript' => null,
            ],
            [
                'lead' => 'Camila Nunes', 'closer' => $thalis, 'status' => 'no_show',
                'started_at' => $now->copy()->subDay()->setTime(16, 0), 'duration' => 0,
                'transcript' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            $lead = $leads[$definition['lead']] ?? null;

            if (! $lead) {
                continue;
            }

            $call = Call::query()->create([
                'lead_id' => $lead->getKey(),
                'closer_id' => $definition['closer']->getKey(),
                'title' => 'Call de diagnóstico — '.$lead->name,
                'status' => $definition['status'],
                'started_at' => $definition['started_at'],
                'duration_sec' => $definition['duration'],
                'transcript' => $definition['transcript'] ? ['text' => $definition['transcript']] : null,
                'analysis_status' => $definition['transcript'] ? 'pending' : 'not_requested',
            ]);

            if ($definition['transcript']) {
                try {
                    app(CallAnalysisService::class)->analyze($call, $definition['closer']);
                } catch (Throwable) {
                    // A demonstração não deve falhar se uma heurística não puder ser aplicada.
                }
            }
        }
    }

    private function seedReminders(array $leads, User $caio, User $thalis, $now): void
    {
        $rows = [
            ['lead' => 'Aline Martins', 'type' => 'confirmacao_reuniao', 'title' => 'Confirmar reunião — Aline Martins', 'due' => $now->copy()->addDay()->setTime(9, 0), 'responsible' => $thalis, 'priority' => 80],
            ['lead' => 'Eduardo Lima', 'type' => 'proposta', 'title' => 'Retornar proposta — Eduardo Lima', 'due' => $now->copy()->subDay()->setTime(9, 0), 'responsible' => $caio, 'priority' => 95],
            ['lead' => 'Patrícia Souza', 'type' => 'cobranca', 'title' => 'Confirmar entrada — Patrícia Souza', 'due' => $now->copy()->addDays(2)->setTime(9, 0), 'responsible' => $caio, 'priority' => 70],
            ['lead' => 'Rafael Alves', 'type' => 'follow_up', 'title' => 'Follow-up de diagnóstico — Rafael Alves', 'due' => $now->copy()->addHours(5), 'responsible' => $thalis, 'priority' => 55],
        ];

        foreach ($rows as $row) {
            $lead = $leads[$row['lead']] ?? null;

            if (! $lead) {
                continue;
            }

            Reminder::query()->firstOrCreate(
                ['dedupe_key' => 'demo:'.$lead->getKey().':'.$row['type']],
                [
                    'lead_id' => $lead->getKey(),
                    'type' => $row['type'],
                    'title' => $row['title'],
                    'detail' => 'Tarefa de demonstração vinculada ao lead '.$lead->name.'.',
                    'due_at' => $row['due'],
                    'status' => Reminder::STATUS_PENDING,
                    'origin' => 'demo',
                    'priority' => $row['priority'],
                    'responsible_id' => $row['responsible']->getKey(),
                ]
            );
        }
    }

    private function seedProposalsAndContracts(array $leads, User $admin, User $caio, $now): void
    {
        $eduardo = $leads['Eduardo Lima'] ?? null;
        $patricia = $leads['Patrícia Souza'] ?? null;

        if ($eduardo) {
            $items = [
                ['description' => 'Diagnóstico comercial e operacional', 'quantity' => 1, 'unit_price' => 3000],
                ['description' => 'Implantação do CRM e playbook', 'quantity' => 1, 'unit_price' => 9000],
            ];
            $subtotal = 12000;
            $proposal = Proposal::query()->create([
                'lead_id' => $eduardo->getKey(),
                'created_by' => $caio->getKey(),
                'number' => 'PROP-DEMO-001',
                'title' => 'Proposta de estruturação comercial',
                'status' => 'sent',
                'valid_until' => $now->copy()->addDays(5)->toDateString(),
                'currency' => 'BRL',
                'items' => $items,
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'payment_terms' => '50% na assinatura e 50% na entrega da primeira etapa.',
                'clauses' => ['Escopo conforme diagnóstico aprovado.', 'Prazo estimado de 30 dias.'],
                'notes' => 'Proposta de demonstração.',
                'version' => 1,
                'sent_at' => $now->copy()->subDays(3),
            ]);
            ProposalVersion::query()->create([
                'proposal_id' => $proposal->getKey(), 'version' => 1,
                'snapshot' => $proposal->toArray(), 'created_by' => $caio->getKey(),
            ]);
        }

        if ($patricia) {
            $items = [
                ['description' => 'Estruturação de operação marketplace', 'quantity' => 1, 'unit_price' => 12000],
                ['description' => 'Treinamento e acompanhamento', 'quantity' => 1, 'unit_price' => 3000],
            ];
            $proposal = Proposal::query()->create([
                'lead_id' => $patricia->getKey(),
                'created_by' => $caio->getKey(),
                'number' => 'PROP-DEMO-002',
                'title' => 'Proposta de operação e crescimento',
                'status' => 'accepted',
                'valid_until' => $now->copy()->addDays(10)->toDateString(),
                'currency' => 'BRL',
                'items' => $items,
                'subtotal' => 15000,
                'discount' => 0,
                'total' => 15000,
                'payment_terms' => 'Entrada de R$ 5.000 e duas parcelas de R$ 5.000.',
                'clauses' => ['Início imediato após assinatura.', 'Suporte por 90 dias.'],
                'notes' => 'Proposta aceita na demonstração.',
                'version' => 1,
                'sent_at' => $now->copy()->subDays(8),
                'accepted_at' => $now->copy()->subDays(2),
            ]);
            ProposalVersion::query()->create([
                'proposal_id' => $proposal->getKey(), 'version' => 1,
                'snapshot' => $proposal->toArray(), 'created_by' => $caio->getKey(),
            ]);

            $contract = Contract::query()->create([
                'proposal_id' => $proposal->getKey(),
                'lead_id' => $patricia->getKey(),
                'created_by' => $admin->getKey(),
                'number' => 'CTR-DEMO-001',
                'title' => 'Contrato de estruturação comercial',
                'status' => 'signed',
                'total' => 15000,
                'content' => 'Contrato de prestação de serviços de estruturação comercial, conforme proposta PROP-DEMO-002. Pagamento em três parcelas. Vigência de 90 dias.',
                'signed_at' => $now->copy()->subDays(2),
                'start_date' => $now->copy()->subDays(1)->toDateString(),
                'end_date' => $now->copy()->addDays(89)->toDateString(),
            ]);

            Payment::query()->firstOrCreate(
                ['lead_id' => $patricia->getKey(), 'installment_number' => 1],
                [
                    'contract_id' => $contract->getKey(), 'proposal_id' => $proposal->getKey(),
                    'kind' => 'entry', 'amount' => 5000, 'due_at' => $now->copy()->subDays(2)->toDateString(),
                    'paid_at' => $now->copy()->subDays(2), 'status' => 'paid', 'method' => 'pix',
                    'created_by' => $caio->getKey(),
                ]
            );
            Payment::query()->firstOrCreate(
                ['lead_id' => $patricia->getKey(), 'installment_number' => 2],
                [
                    'contract_id' => $contract->getKey(), 'proposal_id' => $proposal->getKey(),
                    'kind' => 'installment', 'amount' => 5000, 'due_at' => $now->copy()->addDays(2)->toDateString(),
                    'status' => 'pending', 'created_by' => $caio->getKey(),
                ]
            );
            Payment::query()->firstOrCreate(
                ['lead_id' => $patricia->getKey(), 'installment_number' => 3],
                [
                    'contract_id' => $contract->getKey(), 'proposal_id' => $proposal->getKey(),
                    'kind' => 'installment', 'amount' => 5000, 'due_at' => $now->copy()->addDays(20)->toDateString(),
                    'status' => 'pending', 'created_by' => $caio->getKey(),
                ]
            );
        }
    }

    private function seedLibrary($niches, User $admin, User $caio): void
    {
        $scripts = [
            ['slug' => 'mercado_livre', 'title' => 'Diagnóstico para operação de marketplace', 'stage' => 'lead_agendado', 'category' => 'diagnostico', 'sections' => [
                ['title' => 'Abertura', 'content' => 'Agradeça o tempo, confirme a agenda e explique que o objetivo é entender a operação antes de apresentar qualquer solução.'],
                ['title' => 'Perguntas de situação', 'content' => 'Qual o volume de pedidos? Quem opera? Como é feito o controle de estoque e expedição?'],
                ['title' => 'Dor e impacto', 'content' => 'Onde ocorrem erros? Qual o custo de um pedido perdido ou atrasado? O que acontece se o volume dobrar?'],
                ['title' => 'Próximo passo', 'content' => 'Resuma o diagnóstico, confirme os critérios de decisão e proponha uma entrega concreta.'],
            ]],
            ['slug' => 'mente_renovada', 'title' => 'Call consultiva para mentoria', 'stage' => 'fup', 'category' => 'diagnostico', 'sections' => [
                ['title' => 'Rapport', 'content' => 'Reconheça a jornada do lead e valide o contexto pessoal e profissional.'],
                ['title' => 'Problema', 'content' => 'O que motivou a buscar ajuda agora? O que já tentou? Qual foi o resultado?'],
                ['title' => 'Visão de futuro', 'content' => 'Onde a pessoa quer chegar em seis meses e como saberia que a transformação aconteceu?'],
            ]],
            ['slug' => 'visagismo', 'title' => 'Objeção de preço em consultoria de imagem', 'stage' => 'negociacao', 'category' => 'objecoes', 'sections' => [
                ['title' => 'Validar', 'content' => 'Reconheça a preocupação com o investimento sem defender o preço imediatamente.'],
                ['title' => 'Reancorar', 'content' => 'Retome o impacto da imagem na confiança e nas oportunidades profissionais do cliente.'],
                ['title' => 'Fechar', 'content' => 'Apresente a condição e confirme o próximo passo com data.'],
            ]],
            ['slug' => 'construcao_civil', 'title' => 'Diagnóstico de previsibilidade de obras', 'stage' => 'negociacao', 'category' => 'fechamento', 'sections' => [
                ['title' => 'Contexto', 'content' => 'Mapeie número de obras, atrasos, custos imprevistos e impacto no caixa.'],
                ['title' => 'Decisão', 'content' => 'Identifique quem participa da decisão e quais critérios serão usados.'],
                ['title' => 'Compromisso', 'content' => 'Proponha um diagnóstico inicial com entregáveis e data definida.'],
            ]],
        ];

        foreach ($scripts as $script) {
            $niche = $niches->get($script['slug']);
            unset($script['slug']);
            Script::query()->firstOrCreate(
                ['title' => $script['title']],
                $script + [
                    'niche_id' => $niche?->getKey(),
                    'description' => 'Roteiro de demonstração.',
                    'sections' => $script['sections'],
                    'version' => 1,
                    'status' => 'published',
                    'updated_by' => $admin->getKey(),
                    'published_at' => now(),
                ]
            );
        }

        $objections = [
            ['label' => 'Está caro', 'category' => 'preco', 'stage' => 'negociacao', 'best_rebuttal' => 'Reancorar no custo do problema e no retorno esperado; validar orçamento antes de desconto.', 'what_worked' => 'Comparar o investimento com o valor perdido por mês.', 'raised' => 18, 'resolved' => 11],
            ['label' => 'Preciso pensar', 'category' => 'reflexao', 'stage' => 'negociacao', 'best_rebuttal' => 'Perguntar o que falta para decidir e propor um próximo passo com data.', 'what_worked' => 'Agendar retorno em 24h com critério claro.', 'raised' => 14, 'resolved' => 8],
            ['label' => 'Não é o momento', 'category' => 'tempo', 'stage' => 'fup', 'best_rebuttal' => 'Entender o que precisa mudar para o momento virar prioridade.', 'what_worked' => 'Mostrar o custo de esperar mais um trimestre.', 'raised' => 11, 'resolved' => 5],
            ['label' => 'Preciso falar com meu sócio', 'category' => 'autoridade', 'stage' => 'negociacao', 'best_rebuttal' => 'Mapear o decisor e propor uma conversa conjunta.', 'what_worked' => 'Enviar resumo objetivo para o decisor.', 'raised' => 9, 'resolved' => 6],
            ['label' => 'Já tenho uma solução', 'category' => 'concorrencia', 'stage' => 'lead_agendado', 'best_rebuttal' => 'Comparar critérios de decisão sem desqualificar o concorrente.', 'what_worked' => 'Focar no gargalo que a solução atual não resolve.', 'raised' => 7, 'resolved' => 3],
            ['label' => 'Não conheço a empresa', 'category' => 'confianca', 'stage' => 'fup', 'best_rebuttal' => 'Trazer evidências, casos autorizados e clareza de escopo.', 'what_worked' => 'Compartilhar um caso semelhante e explicar o processo.', 'raised' => 6, 'resolved' => 4],
        ];

        foreach ($objections as $index => $objection) {
            Objection::query()->firstOrCreate(
                ['label' => $objection['label']],
                [
                    'niche_id' => $niches->values()->get($index % $niches->count())?->getKey(),
                    'label' => $objection['label'],
                    'category' => $objection['category'],
                    'stage' => $objection['stage'],
                    'context' => 'Objeção recorrente observada nas calls de demonstração.',
                    'times_raised' => $objection['raised'],
                    'times_resolved' => $objection['resolved'],
                    'what_worked' => $objection['what_worked'],
                    'best_rebuttal' => $objection['best_rebuttal'],
                    'result' => $objection['resolved'] >= $objection['raised'] / 2 ? 'parcial' : 'em_aberto',
                    'closer_id' => $caio->getKey(),
                    'last_seen_at' => now()->subDays($index),
                ]
            );
        }
    }

    private function seedCommissions(array $leads, $now): void
    {
        $service = app(CommissionService::class);

        foreach (['Patrícia Souza', 'João Pedro'] as $name) {
            $lead = $leads[$name] ?? null;

            if (! $lead) {
                continue;
            }

            try {
                $commission = $service->calculateForLead($lead, $now->format('Y-m'));

                if ($commission && $name === 'João Pedro') {
                    $commission->forceFill([
                        'status' => 'approved',
                        'approved_at' => $now->copy()->subDay(),
                        'approved_by' => User::query()->where('email', 'admin@closer.local')->value('id'),
                    ])->save();
                }
            } catch (Throwable) {
                // Comissão é opcional na carga de demonstração.
            }
        }
    }
}
