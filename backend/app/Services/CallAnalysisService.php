<?php

namespace App\Services;

use App\Models\Call;
use App\Models\LeadInteraction;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Support\Str;

class CallAnalysisService
{
    public const DEFAULT_CRITERIA = [
        ['key' => 'abertura', 'label' => 'Abertura', 'weight' => 4, 'keywords' => ['olá', 'bom dia', 'boa tarde', 'tudo bem', 'prazer', 'obrigado por']],
        ['key' => 'rapport', 'label' => 'Rapport', 'weight' => 4, 'keywords' => ['como você está', 'me conta', 'interessante', 'legal', 'concordo', 'imagino']],
        ['key' => 'agenda', 'label' => 'Definição da agenda', 'weight' => 5, 'keywords' => ['objetivo da call', 'hoje vamos', 'nossa conversa', 'em 30 minutos', 'agenda', 'alinhar']],
        ['key' => 'diagnostico', 'label' => 'Diagnóstico', 'weight' => 8, 'keywords' => ['me conta', 'como funciona hoje', 'atualmente', 'quantos', 'cenário', 'situação']],
        ['key' => 'dor', 'label' => 'Identificação da dor', 'weight' => 8, 'keywords' => ['problema', 'dificuldade', 'desafio', 'dor', 'gargalo', 'não consigo', 'perdendo']],
        ['key' => 'impacto', 'label' => 'Exploração do impacto', 'weight' => 8, 'keywords' => ['impacto', 'consequência', 'o que acontece', 'quanto custa', 'perde', 'deixa de']],
        ['key' => 'consequencia', 'label' => 'Consequência futura', 'weight' => 6, 'keywords' => ['daqui a', 'futuro', 'se continuar', 'próximos meses', 'piorar']],
        ['key' => 'visao_futuro', 'label' => 'Visão de futuro', 'weight' => 5, 'keywords' => ['onde quer chegar', 'objetivo', 'ideal', 'sonho', 'meta']],
        ['key' => 'necessidade', 'label' => 'Necessidade de solução', 'weight' => 8, 'keywords' => ['se resolvesse', 'gostaria', 'faria sentido', 'precisa', 'espera']],
        ['key' => 'confirmacao', 'label' => 'Confirmação de entendimento', 'weight' => 5, 'keywords' => ['faz sentido', 'entendi certo', 'pode confirmar', 'resumindo', 'é isso']],
        ['key' => 'solucao', 'label' => 'Apresentação da solução', 'weight' => 6, 'keywords' => ['nossa solução', 'funciona assim', 'como ajudamos', 'plataforma', 'metodologia']],
        ['key' => 'conexao_solucao', 'label' => 'Conexão da solução à dor', 'weight' => 6, 'keywords' => ['justamente', 'por isso', 'resolve', 'conecta', 'endereça']],
        ['key' => 'ancoragem', 'label' => 'Ancoragem de valor', 'weight' => 5, 'keywords' => ['valor gerado', 'retorno', 'economia', 'comparado', 'investimento']],
        ['key' => 'investimento', 'label' => 'Investimento', 'weight' => 5, 'keywords' => ['investimento', 'valor', 'preço', 'condição', 'parcela']],
        ['key' => 'objecoes', 'label' => 'Tratamento de objeções', 'weight' => 7, 'keywords' => ['entendo', 'faz sentido', 'vamos ver', 'alternativa', 'possibilidade', 'consigo']],
        ['key' => 'fechamento', 'label' => 'Fechamento', 'weight' => 8, 'keywords' => ['vamos fechar', 'próximo passo', 'posso enviar', 'agendo', 'assinatura', 'pagamento', 'combinamos']],
    ];

    private const OBJECTIONS = [
        'preco' => ['label' => 'Preço / investimento', 'keywords' => ['caro', 'preço', 'valor alto', 'sem dinheiro', 'apertado', 'orçamento']],
        'tempo' => ['label' => 'Tempo / momento', 'keywords' => ['mais para frente', 'agora não', 'momento', 'sem tempo', 'correria']],
        'confianca' => ['label' => 'Confiança / credibilidade', 'keywords' => ['não conheço', 'funciona mesmo', 'garantia', 'medo', 'risco']],
        'autoridade' => ['label' => 'Decisão com terceiros', 'keywords' => ['meu sócio', 'minha esposa', 'preciso falar', 'decidir com']],
        'necessidade' => ['label' => 'Necessidade', 'keywords' => ['não preciso', 'já tenho', 'resolve sozinho', 'não é prioridade']],
        'concorrencia' => ['label' => 'Concorrência', 'keywords' => ['concorrente', 'outra proposta', 'outra empresa', 'já fiz orçamento']],
        'reflexao' => ['label' => 'Precisa pensar', 'keywords' => ['vou pensar', 'deixa eu ver', 'preciso analisar', 'retorno depois']],
    ];

    private const BUY_SIGNALS = ['como funciona o pagamento', 'quando começamos', 'contrato', 'assinatura', 'parcela', 'desconto', 'próximo passo', 'agendar', 'fechar', 'vou querer'];
    private const URGENCY = ['urgente', 'preciso para', 'essa semana', 'hoje', 'amanhã', 'prazo', 'quanto antes'];
    private const POSITIVE = ['perfeito', 'ótimo', 'gostei', 'faz sentido', 'interessante', 'exatamente', 'concordo'];
    private const NEGATIVE = ['problema', 'difícil', 'não consigo', 'preocupado', 'receio', 'caro', 'não sei'];
    private const COMMITMENTS = ['vou', 'envio', 'mando', 'mandar', 'marcamos', 'agendo', 'retorno', 'ficamos', 'combinamos', 'próximo passo'];

    public function analyze(Call $call, ?User $actor = null, ?Playbook $playbook = null): Call
    {
        $text = $this->transcriptText($call);

        if (mb_strlen(trim($text)) < 20) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'transcript' => 'Transcrição muito curta para uma análise minimamente confiável.',
            ]);
        }

        $lines = $this->parseLines($text);
        [$closerLabel, $leadLabel] = $this->identifySpeakers($lines);
        $talk = $this->talkTime($lines, $closerLabel, $leadLabel);
        $sentences = $this->sentences($lines);
        $spin = $this->spinAnalysis($sentences);
        $objections = $this->objectionAnalysis($sentences, $closerLabel);
        $criteria = $playbook?->criteria ?: self::DEFAULT_CRITERIA;
        $score = $this->playbookScore($sentences, $criteria);
        $sentiment = $this->sentiment($sentences);
        $highlights = $this->highlights($sentences, $leadLabel);
        $nextSteps = $this->nextSteps($sentences, $leadLabel, $spin, $objections);
        $summary = $this->summary($text, $spin, $objections, $nextSteps, $talk, $closerLabel, $leadLabel);

        $analysis = [
            'provider' => 'local-heuristic',
            'provider_label' => 'Análise heurística local (sem IA externa)',
            'generated_at' => now()->toIso8601String(),
            'caveats' => [
                'Resultado heurístico baseado apenas no texto. Não representa avaliação psicológica nem medição exata de fala.',
                'Talk-time é aproximado por volume de caracteres quando os participantes estão identificados.',
                'Nenhuma transcrição foi enviada a provedores externos.',
            ],
            'participants' => ['closer' => $closerLabel, 'lead' => $leadLabel],
            'summary' => $summary,
            'spin' => $spin,
            'objections' => $objections,
            'playbook' => $score,
            'sentiment' => $sentiment,
            'highlights' => $highlights,
            'next_steps' => $nextSteps,
            'risks' => $this->risks($spin, $objections, $score, $nextSteps),
        ];

        $call->forceFill([
            'summary' => $summary,
            'spin' => $spin,
            'objections' => $objections,
            'talk_time_closer' => $talk['closer_ratio'],
            'talk_time_lead' => $talk['lead_ratio'],
            'playbook_score' => $score['score'],
            'adherence_score' => $score['score'],
            'sentiment' => $sentiment,
            'highlights' => $highlights,
            'extracted_steps' => $nextSteps,
            'analysis' => $analysis,
            'analysis_status' => 'completed',
            'ai_provider' => 'local-heuristic',
            'analyzed_at' => now(),
        ])->save();

        if ($call->lead_id) {
            LeadInteraction::query()->create([
                'lead_id' => $call->lead_id,
                'kind' => 'analise_call',
                'occurred_at' => now(),
                'author' => $actor?->name ?? 'Análise local',
                'user_id' => $actor?->getKey(),
                'summary' => 'Call analisada localmente. Score de playbook: '.$score['score'].'/100.',
                'call_id' => $call->getKey(),
                'metadata' => ['provider' => 'local-heuristic'],
            ]);
        }

        return $call->fresh(['lead:id,name', 'closer:id,name']);
    }

    private function transcriptText(Call $call): string
    {
        $transcript = $call->transcript;

        if (is_string($transcript)) {
            return $transcript;
        }

        if (! is_array($transcript)) {
            return '';
        }

        if (isset($transcript['text']) && is_string($transcript['text'])) {
            return $transcript['text'];
        }

        if (isset($transcript['lines']) && is_array($transcript['lines'])) {
            return collect($transcript['lines'])->map(function ($line) {
                return is_array($line)
                    ? trim(($line['speaker'] ?? '').': '.($line['text'] ?? ''))
                    : (string) $line;
            })->implode("\n");
        }

        return collect($transcript)->map(function ($line) {
            return is_array($line)
                ? trim(($line['speaker'] ?? '').': '.($line['text'] ?? ''))
                : (string) $line;
        })->implode("\n");
    }

    /**
     * @return array<int, array{speaker:?string,text:string}>
     */
    private function parseLines(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $result = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?? '');

            if ($line === '') {
                continue;
            }

            if (preg_match('/^([A-Za-zÀ-ÿ0-9 .\'-]{1,40}):\s*(.+)$/u', $line, $matches)) {
                $speaker = trim($matches[1]);
                $content = trim($matches[2]);
            } else {
                $speaker = null;
                $content = $line;
            }

            $result[] = ['speaker' => $speaker, 'text' => $content];
        }

        return $result;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function identifySpeakers(array $lines): array
    {
        $speakers = collect($lines)->pluck('speaker')->filter()->unique()->values();

        if ($speakers->isEmpty()) {
            return [null, null];
        }

        $closer = $speakers->first(fn ($name) => Str::contains(Str::lower($name), ['closer', 'vendedor', 'consultor', 'comercial']));
        $lead = $speakers->first(fn ($name) => Str::contains(Str::lower($name), ['lead', 'cliente', 'prospect']));

        if (! $closer && ! $lead && $speakers->count() >= 2) {
            $closer = $speakers[0];
            $lead = $speakers[1];
        }

        return [$closer, $lead];
    }

    /**
     * @return array{closer_ratio:?float,lead_ratio:?float,closer_chars:int,lead_chars:int,caveat:string}
     */
    private function talkTime(array $lines, ?string $closer, ?string $lead): array
    {
        if (! $closer || ! $lead) {
            return [
                'closer_ratio' => null,
                'lead_ratio' => null,
                'closer_chars' => 0,
                'lead_chars' => 0,
                'caveat' => 'Participantes não identificados de forma confiável; talk-time não calculado.',
            ];
        }

        $closerChars = collect($lines)->where('speaker', $closer)->sum(fn ($line) => mb_strlen($line['text']));
        $leadChars = collect($lines)->where('speaker', $lead)->sum(fn ($line) => mb_strlen($line['text']));
        $total = $closerChars + $leadChars;

        return [
            'closer_ratio' => $total > 0 ? round($closerChars / $total, 4) : null,
            'lead_ratio' => $total > 0 ? round($leadChars / $total, 4) : null,
            'closer_chars' => $closerChars,
            'lead_chars' => $leadChars,
            'caveat' => 'Aproximação por caracteres; não é medição exata de tempo de fala.',
        ];
    }

    /**
     * @return array<int, array{speaker:?string,text:string}>
     */
    private function sentences(array $lines): array
    {
        $result = [];

        foreach ($lines as $line) {
            $parts = preg_split('/(?<=[.!?])\s+/u', $line['text']) ?: [$line['text']];

            foreach ($parts as $part) {
                $part = trim($part);

                if (mb_strlen($part) >= 8) {
                    $result[] = ['speaker' => $line['speaker'], 'text' => $part];
                }
            }
        }

        return $result;
    }

    private function spinAnalysis(array $sentences): array
    {
        $config = [
            'situacao' => ['label' => 'Situação', 'keywords' => ['hoje', 'atualmente', 'como funciona', 'quantos', 'me conta', 'cenário', 'situação']],
            'problema' => ['label' => 'Problema', 'keywords' => ['problema', 'dificuldade', 'desafio', 'dor', 'gargalo', 'não consigo', 'perdendo']],
            'implicacao' => ['label' => 'Implicação', 'keywords' => ['impacto', 'consequência', 'o que acontece', 'quanto custa', 'quanto deixa', 'perde', 'deixa de']],
            'necessidade' => ['label' => 'Necessidade de solução', 'keywords' => ['se resolvesse', 'gostaria', 'faria sentido', 'precisa', 'espera', 'benefício', 'objetivo']],
        ];

        $suggestions = [
            'situacao' => ['Como funciona o processo hoje?', 'Quem participa dessa operação?', 'Qual é o volume atual?'],
            'problema' => ['Qual é a maior dificuldade nesse processo?', 'O que mais incomoda hoje?', 'Onde vocês perdem mais tempo?'],
            'implicacao' => ['Qual é o impacto disso no resultado?', 'Quanto isso custa por mês?', 'O que acontece se nada mudar?'],
            'necessidade' => ['Se isso fosse resolvido, o que mudaria?', 'Qual resultado seria ideal?', 'Por que resolver agora?'],
        ];

        $result = [];

        foreach ($config as $key => $item) {
            $evidence = collect($sentences)
                ->filter(fn ($sentence) => $this->containsAny($sentence['text'], $item['keywords']))
                ->take(5)
                ->map(fn ($sentence) => [
                    'speaker' => $sentence['speaker'],
                    'text' => $sentence['text'],
                    'is_question' => str_contains($sentence['text'], '?'),
                ])
                ->values()
                ->all();

            $result[$key] = [
                'label' => $item['label'],
                'identified' => $evidence !== [],
                'questions_identified' => collect($evidence)->where('is_question', true)->count(),
                'evidence' => $evidence,
                'suggested_questions' => $evidence === [] ? $suggestions[$key] : [],
                'investigate' => $evidence === [] ? 'Categoria sem evidência suficiente na transcrição.' : null,
            ];
        }

        return $result;
    }

    private function objectionAnalysis(array $sentences, ?string $closer): array
    {
        $result = [];

        foreach (self::OBJECTIONS as $key => $objection) {
            $evidence = collect($sentences)
                ->filter(fn ($sentence) => $this->containsAny($sentence['text'], $objection['keywords']))
                ->take(4)
                ->values();

            if ($evidence->isEmpty()) {
                continue;
            }

            $response = collect($sentences)
                ->filter(fn ($sentence) => $closer && $sentence['speaker'] === $closer)
                ->filter(fn ($sentence) => $this->containsAny($sentence['text'], ['entendo', 'faz sentido', 'consigo', 'alternativa', 'possibilidade', 'podemos', 'vamos avaliar', 'justamente']))
                ->take(3)
                ->map(fn ($sentence) => ['speaker' => $sentence['speaker'], 'text' => $sentence['text']])
                ->values()
                ->all();

            $result[] = [
                'key' => $key,
                'label' => $objection['label'],
                'raised' => $evidence->count(),
                'evidence' => $evidence->map(fn ($sentence) => ['speaker' => $sentence['speaker'], 'text' => $sentence['text']])->all(),
                'response_evidence' => $response,
                'addressed_in_evidence' => $response !== [],
                'suggested_rebuttal' => $this->rebuttalSuggestion($key),
                'caveat' => 'Indicador textual; não confirma resolução da objeção.',
            ];
        }

        return $result;
    }

    private function playbookScore(array $sentences, array $criteria): array
    {
        $totalWeight = 0;
        $earned = 0;
        $breakdown = [];

        foreach ($criteria as $criterion) {
            $weight = (int) ($criterion['weight'] ?? 1);
            $keywords = $criterion['keywords'] ?? [];

            if (is_string($keywords)) {
                $keywords = array_filter(array_map('trim', explode(',', $keywords)));
            }

            $evidence = collect($sentences)
                ->filter(fn ($sentence) => $this->containsAny($sentence['text'], (array) $keywords))
                ->take(3)
                ->map(fn ($sentence) => ['speaker' => $sentence['speaker'], 'text' => $sentence['text']])
                ->values()
                ->all();

            $found = $evidence !== [];
            $totalWeight += $weight;

            if ($found) {
                $earned += $weight;
            }

            $breakdown[] = [
                'key' => $criterion['key'] ?? Str::slug($criterion['label'] ?? 'criterio'),
                'label' => $criterion['label'] ?? $criterion['key'] ?? 'Critério',
                'weight' => $weight,
                'found' => $found,
                'evidence' => $evidence,
                'explanation' => $found
                    ? 'Evidência textual encontrada na transcrição.'
                    : 'Nenhuma evidência suficiente encontrada; não foi pontuado.',
            ];
        }

        $score = $totalWeight > 0 ? (int) round($earned / $totalWeight * 100) : 0;

        return [
            'score' => $score,
            'earned_weight' => $earned,
            'total_weight' => $totalWeight,
            'breakdown' => $breakdown,
            'explanation' => 'Score calculado apenas por evidências textuais. Critérios sem evidência não recebem pontos.',
        ];
    }

    private function sentiment(array $sentences): array
    {
        $group = function (array $keywords) use ($sentences) {
            return collect($sentences)
                ->filter(fn ($sentence) => $this->containsAny($sentence['text'], $keywords))
                ->take(4)
                ->map(fn ($sentence) => ['speaker' => $sentence['speaker'], 'text' => $sentence['text']])
                ->values()
                ->all();
        };

        return [
            'label' => 'Indicadores aproximados (heurística textual)',
            'interest' => $group(self::BUY_SIGNALS),
            'urgency' => $group(self::URGENCY),
            'positive' => $group(self::POSITIVE),
            'resistance' => $group(self::NEGATIVE),
            'caveat' => 'Sinais de linguagem não comprovam intenção de compra nem estado psicológico.',
        ];
    }

    private function highlights(array $sentences, ?string $lead): array
    {
        $highlights = [];

        foreach ($sentences as $sentence) {
            $types = [];

            if ($this->containsAny($sentence['text'], self::BUY_SIGNALS)) {
                $types[] = 'sinal_de_compra';
            }

            if ($this->containsAny($sentence['text'], self::NEGATIVE)) {
                $types[] = 'barreira';
            }

            if ($this->containsAny($sentence['text'], self::COMMITMENTS)) {
                $types[] = 'compromisso';
            }

            if ($this->containsAny($sentence['text'], self::URGENCY)) {
                $types[] = 'urgencia';
            }

            if ($types === []) {
                continue;
            }

            $highlights[] = [
                'speaker' => $sentence['speaker'],
                'text' => $sentence['text'],
                'types' => array_values(array_unique($types)),
                'from_lead' => $lead !== null && $sentence['speaker'] === $lead,
            ];
        }

        return array_slice($this->dedupeByText($highlights), 0, 10);
    }

    private function nextSteps(array $sentences, ?string $lead, array $spin, array $objections): array
    {
        $steps = [];

        foreach ($sentences as $sentence) {
            if (! $this->containsAny($sentence['text'], self::COMMITMENTS)) {
                continue;
            }

            if ($lead !== null && $sentence['speaker'] !== $lead && ! $this->containsAny($sentence['text'], ['envio', 'mando', 'agendo', 'vou'])) {
                continue;
            }

            $steps[] = [
                'title' => 'Retomar: '.mb_substr($sentence['text'], 0, 120),
                'context' => $sentence['text'],
                'source' => 'transcricao',
                'requires_confirmation' => true,
                'suggested_due_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
            ];
        }

        if ($steps === []) {
            if (empty($spin['necessidade']['identified'])) {
                $steps[] = [
                    'title' => 'Aprofundar necessidade antes de propor solução',
                    'context' => 'A transcrição não apresenta evidência suficiente de necessidade de solução.',
                    'source' => 'heuristica',
                    'requires_confirmation' => true,
                    'suggested_due_at' => now()->addDay()->setTime(9, 0)->toIso8601String(),
                ];
            }

            if (collect($objections)->where('addressed_in_evidence', false)->isNotEmpty()) {
                $steps[] = [
                    'title' => 'Planejar tratamento das objeções em aberto',
                    'context' => 'Há objeções com evidência textual sem resposta identificada na transcrição.',
                    'source' => 'heuristica',
                    'requires_confirmation' => true,
                    'suggested_due_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
                ];
            }

            $steps[] = [
                'title' => 'Confirmar próximo passo com o lead',
                'context' => 'Nenhum compromisso explícito foi identificado na transcrição.',
                'source' => 'heuristica',
                'requires_confirmation' => true,
                'suggested_due_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
            ];
        }

        return array_slice($this->dedupeByText($steps, 'title'), 0, 8);
    }

    private function summary(string $text, array $spin, array $objections, array $nextSteps, array $talk, ?string $closer, ?string $lead): string
    {
        $parts = [];
        $parts[] = sprintf(
            'Resumo heurístico de %d caracteres. Participantes identificados: closer %s, lead %s.',
            mb_strlen($text),
            $closer ?? 'não identificado',
            $lead ?? 'não identificado'
        );

        $needs = $spin['necessidade']['evidence'] ?? [];

        if ($needs !== []) {
            $parts[] = 'Necessidade: '.$needs[0]['text'];
        }

        $problems = $spin['problema']['evidence'] ?? [];

        if ($problems !== []) {
            $parts[] = 'Problema: '.$problems[0]['text'];
        }

        if ($objections !== []) {
            $parts[] = 'Objeções identificadas: '.collect($objections)->pluck('label')->implode(', ').'.';
        }

        if ($talk['closer_ratio'] !== null) {
            $parts[] = sprintf(
                'Talk-time aproximado: closer %d%%, lead %d%% (por caracteres).',
                round($talk['closer_ratio'] * 100),
                round((float) $talk['lead_ratio'] * 100)
            );
        }

        if ($nextSteps !== []) {
            $parts[] = 'Próximo passo sugerido: '.$nextSteps[0]['title'].'.';
        }

        return implode(' ', $parts);
    }

    private function risks(array $spin, array $objections, array $score, array $nextSteps): array
    {
        $risks = [];

        if (! $spin['necessidade']['identified']) {
            $risks[] = 'Necessidade de solução não comprovada na transcrição.';
        }

        if (! $spin['implicacao']['identified']) {
            $risks[] = 'Impacto do problema não foi explorado com evidência.';
        }

        $unaddressed = collect($objections)->where('addressed_in_evidence', false);

        if ($unaddressed->isNotEmpty()) {
            $risks[] = 'Objeções sem resposta identificada: '.$unaddressed->pluck('label')->implode(', ').'.';
        }

        if ($score['score'] < 50) {
            $risks[] = 'Score de playbook abaixo de 50; revisar condução da call.';
        }

        if ($nextSteps === []) {
            $risks[] = 'Nenhum próximo passo identificado.';
        }

        return $risks;
    }

    private function rebuttalSuggestion(string $key): string
    {
        return match ($key) {
            'preco' => 'Reancorar no custo do problema e comparar com o retorno esperado; validar orçamento antes de oferecer condição.',
            'tempo' => 'Entender o que precisa acontecer para o momento virar prioridade e propor um próximo passo pequeno.',
            'confianca' => 'Trazer evidências, casos autorizados e clareza sobre escopo, garantias e responsabilidades.',
            'autoridade' => 'Mapear quem decide e propor uma conversa conjunta; não pressionar decisão unilateral.',
            'necessidade' => 'Aprofundar consequências do problema atual antes de apresentar valor.',
            'concorrencia' => 'Comparar critérios de decisão de forma honesta, sem inventar superioridade.',
            'reflexao' => 'Confirmar o que ainda falta para decidir e agendar retorno com critério claro.',
            default => 'Validar a objeção, explorar impacto e combinar próximo passo.',
        };
    }

    private function containsAny(string $text, array $keywords): bool
    {
        $lower = Str::lower($text);

        foreach ($keywords as $keyword) {
            $keyword = Str::lower(trim((string) $keyword));

            if ($keyword !== '' && str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function dedupeByText(array $items, string $key = 'text'): array
    {
        $seen = [];

        return array_values(array_filter($items, function ($item) use (&$seen, $key) {
            $value = $item[$key] ?? '';

            if (isset($seen[$value])) {
                return false;
            }

            $seen[$value] = true;

            return true;
        }));
    }
}
