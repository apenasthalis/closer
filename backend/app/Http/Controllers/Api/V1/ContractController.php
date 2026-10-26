<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Proposal;
use App\Services\AuditService;
use App\Services\Documents\ContractPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContractController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ContractPdfService $pdf,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'pending_signature', 'signed', 'active', 'completed', 'cancelled'])],
            'lead_id' => ['nullable', 'uuid'],
        ]);

        $query = Contract::query()
            ->with(['lead:id,name,company', 'proposal:id,number,title'])
            ->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['lead_id'] ?? null, fn ($builder, $leadId) => $builder->where('lead_id', $leadId))
            ->latest();

        return response()->json($query->paginate(50)->withQueryString());
    }

    public function store(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validate([
            'proposal_id' => ['nullable', 'uuid', 'exists:proposals,id'],
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'total' => ['required', 'numeric', 'min:0'],
            'content' => ['required', 'string', 'min:20', 'max:100000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $contract = Contract::query()->create([
            'proposal_id' => $data['proposal_id'] ?? null,
            'lead_id' => $lead->getKey(),
            'created_by' => $request->user()->getKey(),
            'number' => 'CTR-'.now()->format('Ym').'-'.strtoupper(Str::random(6)),
            'title' => $data['title'],
            'status' => 'draft',
            'total' => $data['total'],
            'content' => $data['content'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]);

        $this->audit->record('contract.created', $request->user(), $contract, [], $contract->only(['number', 'total']), [], $request);

        return response()->json(['data' => $contract->load('lead:id,name'), 'message' => 'Contrato criado.'], 201);
    }

    public function fromProposal(Request $request, Proposal $proposal): JsonResponse
    {
        abort_unless(Lead::query()->visibleTo($request->user())->whereKey($proposal->lead_id)->exists(), 403);
        abort_unless(in_array($proposal->status, ['accepted', 'sent'], true), 422, 'A proposta precisa estar enviada ou aceita.');

        $contract = Contract::query()->firstOrCreate(
            ['proposal_id' => $proposal->getKey()],
            [
                'lead_id' => $proposal->lead_id,
                'created_by' => $request->user()->getKey(),
                'number' => 'CTR-'.now()->format('Ym').'-'.strtoupper(Str::random(6)),
                'title' => 'Contrato — '.$proposal->title,
                'status' => 'pending_signature',
                'total' => $proposal->total,
                'content' => 'Contrato referente à proposta '.$proposal->number.'. '.($proposal->payment_terms ?? ''),
                'start_date' => now()->toDateString(),
            ]
        );

        return response()->json(['data' => $contract->load('lead:id,name', 'proposal:id,number'), 'message' => 'Contrato gerado a partir da proposta.'], 201);
    }

    public function show(Request $request, Contract $contract): JsonResponse
    {
        $this->authorizeContract($request, $contract);

        return response()->json(['data' => $contract->load(['lead:id,name,company', 'proposal', 'payments'])]);
    }

    public function updateStatus(Request $request, Contract $contract): JsonResponse
    {
        $this->authorizeContract($request, $contract);
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'pending_signature', 'signed', 'active', 'completed', 'cancelled'])],
        ]);

        $old = $contract->only(['status', 'signed_at']);
        $contract->status = $data['status'];

        if ($data['status'] === 'signed' && ! $contract->signed_at) {
            $contract->signed_at = now();
        }

        $contract->save();
        $this->audit->record('contract.status_changed', $request->user(), $contract, $old, ['status' => $data['status']], [], $request);

        return response()->json(['data' => $contract->fresh(['lead:id,name']), 'message' => 'Status do contrato atualizado.']);
    }

    public function pdf(Request $request, Contract $contract): Response
    {
        $this->authorizeContract($request, $contract);
        $content = $this->pdf->render($contract);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="contrato-'.$contract->number.'.pdf"',
        ]);
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless(Lead::query()->visibleTo($request->user())->whereKey($lead->getKey())->exists(), 403, 'Lead não acessível.');
    }

    private function authorizeContract(Request $request, Contract $contract): void
    {
        abort_unless(Lead::query()->visibleTo($request->user())->whereKey($contract->lead_id)->exists(), 403, 'Contrato não acessível.');
    }
}
