<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadInteractionRequest;
use App\Http\Requests\LeadRequest;
use App\Http\Requests\UpdateLeadStageRequest;
use App\Models\Lead;
use App\Models\LeadAttachment;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'stage' => ['nullable', 'string', 'max:32'],
            'niche_id' => ['nullable', 'uuid'],
            'closer_id' => ['nullable', 'integer'],
            'pre_sales_id' => ['nullable', 'integer'],
            'temperature' => ['nullable', 'in:cold,warm,hot'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort' => ['nullable', 'in:recent,priority,next_action,name'],
        ]);

        $query = Lead::query()
            ->visibleTo($request->user())
            ->with(['niche:id,name,color', 'closer:id,name', 'preSales:id,name'])
            ->when($filters['q'] ?? null, function ($builder, $term) {
                $like = '%'.mb_strtolower($term).'%';
                $builder->where(function ($inner) use ($like) {
                    $inner->whereRaw('lower(name) like ?', [$like])
                        ->orWhereRaw('lower(company) like ?', [$like])
                        ->orWhereRaw('lower(email) like ?', [$like])
                        ->orWhereRaw('lower(phone) like ?', [$like]);
                });
            })
            ->when($filters['stage'] ?? null, fn ($builder, $stage) => $builder->where('stage', $stage))
            ->when($filters['niche_id'] ?? null, fn ($builder, $niche) => $builder->where('niche_id', $niche))
            ->when($filters['closer_id'] ?? null, fn ($builder, $closer) => $builder->where('closer_id', $closer))
            ->when($filters['pre_sales_id'] ?? null, fn ($builder, $sdr) => $builder->where('pre_sales_id', $sdr))
            ->when($filters['temperature'] ?? null, fn ($builder, $temperature) => $builder->where('temperature', $temperature))
            ->when($filters['priority'] ?? null, fn ($builder, $priority) => $builder->where('priority', '>=', $priority));

        match ($filters['sort'] ?? 'recent') {
            'priority' => $query->orderByDesc('priority')->latest(),
            'next_action' => $query->orderByRaw('next_action_at is null')->orderBy('next_action_at'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        return response()->json($query->paginate($filters['per_page'] ?? 25)->withQueryString());
    }

    public function kanban(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'niche_id' => ['nullable', 'uuid'],
            'closer_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Lead::query()
            ->visibleTo($request->user())
            ->with(['niche:id,name,color', 'closer:id,name', 'preSales:id,name'])
            ->when($filters['niche_id'] ?? null, fn ($builder, $niche) => $builder->where('niche_id', $niche))
            ->when($filters['closer_id'] ?? null, fn ($builder, $closer) => $builder->where('closer_id', $closer))
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->where(function ($inner) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $inner->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(company) like ?', [$like]);
            }))
            ->orderByDesc('priority')
            ->orderBy('position')
            ->limit(1500)
            ->get();

        $columns = collect(Lead::STAGES)->map(fn ($stage) => [
            'stage' => $stage,
            'label' => Lead::STAGE_LABELS[$stage],
            'leads' => $query->where('stage', $stage)->values(),
            'count' => $query->where('stage', $stage)->count(),
            'value' => (float) $query->where('stage', $stage)->sum(fn (Lead $lead) => (float) ($lead->valor_negociado ?? $lead->ticket_estimado ?? 0)),
        ]);

        return response()->json([
            'data' => ['columns' => $columns, 'total' => $query->count()],
        ]);
    }

    public function store(LeadRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if ($user->isAdmin() || $user->isManager()) {
            $data['closer_id'] = $data['closer_id'] ?? $user->getKey();
        } else {
            $data['closer_id'] = $user->getKey();
        }

        if ($user->isSdr()) {
            $data['pre_sales_id'] = $user->getKey();
        }

        $lead = $this->leads->create($user, $data, $request);

        return response()->json(['data' => $lead, 'message' => 'Lead criado com sucesso.'], 201);
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);

        $lead->load([
            'niche:id,name,color', 'closer:id,name,email', 'preSales:id,name,email',
            'interactions.user:id,name', 'stageHistories.user:id,name',
            'reminders' => fn ($query) => $query->orderBy('due_at'),
            'attachments.uploadedBy:id,name',
            'proposals.createdBy:id,name', 'contracts', 'payments', 'calls',
            'commissions' => fn ($query) => $query->with('user:id,name'),
        ]);

        return response()->json([
            'data' => $lead,
            'meta' => [
                'stages' => collect(Lead::STAGES)->map(fn ($stage) => ['value' => $stage, 'label' => Lead::STAGE_LABELS[$stage]])->values(),
                'can_edit' => $request->user()->isAdmin() || $request->user()->isManager()
                    || $lead->closer_id === $request->user()->getKey()
                    || $lead->pre_sales_id === $request->user()->getKey(),
            ],
        ]);
    }

    public function update(LeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $lead = $this->leads->update($lead, $request->user(), $request->validated(), $request);

        return response()->json(['data' => $lead, 'message' => 'Lead atualizado.']);
    }

    public function updateStage(UpdateLeadStageRequest $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validated();
        $lead = $this->leads->moveStage($lead, $request->user(), $data['stage'], $data['reason'] ?? null, $data, $request);

        return response()->json(['data' => $lead, 'message' => 'Etapa atualizada.']);
    }

    public function destroy(Request $request, Lead $lead): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Somente administradores podem excluir leads.');
        $this->authorizeLead($request, $lead);
        $lead->delete();

        return response()->json(['message' => 'Lead arquivado.']);
    }

    public function interactions(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);

        return response()->json([
            'data' => $lead->interactions()->with('user:id,name')->get(),
        ]);
    }

    public function storeInteraction(LeadInteractionRequest $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $interaction = $this->leads->addInteraction($lead, $request->user(), $request->validated(), $request);

        return response()->json(['data' => $interaction, 'message' => 'Interação registrada.'], 201);
    }

    public function attachments(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);

        return response()->json(['data' => $lead->attachments()->with('uploadedBy:id,name')->latest()->get()]);
    }

    public function uploadAttachment(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,png,jpg,jpeg,txt,csv'],
            'kind' => ['nullable', 'in:document,proposal,contract,receipt,other'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            'lead-attachments/'.$lead->getKey(),
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local'
        );

        $attachment = LeadAttachment::query()->create([
            'lead_id' => $lead->getKey(),
            'kind' => $request->input('kind', 'document'),
            'file_name' => $file->getClientOriginalName(),
            'size_kb' => (int) ceil($file->getSize() / 1024),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by' => $request->user()->getKey(),
            'description' => $request->input('description'),
            'uploaded_at' => now(),
        ]);

        return response()->json(['data' => $attachment, 'message' => 'Anexo enviado.'], 201);
    }

    public function downloadAttachment(Request $request, Lead $lead, LeadAttachment $attachment): StreamedResponse
    {
        $this->authorizeLead($request, $lead);

        abort_unless($attachment->lead_id === $lead->getKey(), 404);
        abort_unless($attachment->path && Storage::disk('local')->exists($attachment->path), 404, 'Arquivo não encontrado.');

        return Storage::disk('local')->download($attachment->path, $attachment->file_name);
    }

    public function destroyAttachment(Request $request, Lead $lead, LeadAttachment $attachment): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        abort_unless($attachment->lead_id === $lead->getKey(), 404);

        if ($attachment->path) {
            Storage::disk('local')->delete($attachment->path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Anexo removido.']);
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless(
            Lead::query()->visibleTo($request->user())->whereKey($lead->getKey())->exists(),
            403,
            'Lead não acessível.'
        );
    }
}
