<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProposalRequest;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Services\AuditService;
use App\Services\Documents\ProposalPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ProposalPdfService $pdf,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired'])],
            'lead_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Proposal::query()
            ->with(['lead:id,name,company', 'createdBy:id,name'])
            ->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['lead_id'] ?? null, fn ($builder, $leadId) => $builder->where('lead_id', $leadId))
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->where(function ($inner) use ($term) {
                $like = '%'.mb_strtolower($term).'%';
                $inner->whereRaw('lower(number) like ?', [$like])->orWhereRaw('lower(title) like ?', [$like]);
            }))
            ->latest();

        return response()->json($query->paginate(50)->withQueryString());
    }

    public function store(ProposalRequest $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead);
        $data = $request->validated();

        $proposal = DB::transaction(function () use ($data, $lead, $request) {
            $items = collect($data['items'])->map(fn ($item) => [
                'description' => $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
            ])->values()->all();

            $subtotal = collect($items)->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
            $discount = min((float) ($data['discount'] ?? 0), $subtotal);
            $total = max(0, $subtotal - $discount);

            /** @var Proposal $proposal */
            $proposal = Proposal::query()->create([
                'lead_id' => $lead->getKey(),
                'created_by' => $request->user()->getKey(),
                'number' => $this->number(),
                'title' => $data['title'],
                'status' => 'draft',
                'valid_until' => $data['valid_until'] ?? now()->addDays(7)->toDateString(),
                'currency' => 'BRL',
                'items' => $items,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'payment_terms' => $data['payment_terms'] ?? null,
                'clauses' => $data['clauses'] ?? null,
                'notes' => $data['notes'] ?? null,
                'version' => 1,
            ]);

            ProposalVersion::query()->create([
                'proposal_id' => $proposal->getKey(),
                'version' => 1,
                'snapshot' => $proposal->toArray(),
                'created_by' => $request->user()->getKey(),
            ]);

            $this->audit->record('proposal.created', $request->user(), $proposal, [], $proposal->only(['number', 'total']), [], $request);

            return $proposal;
        });

        return response()->json(['data' => $proposal->load('lead:id,name', 'createdBy:id,name'), 'message' => 'Proposta criada.'], 201);
    }

    public function show(Request $request, Proposal $proposal): JsonResponse
    {
        $this->authorizeProposal($request, $proposal);

        return response()->json([
            'data' => $proposal->load(['lead:id,name,company,email,phone,niche', 'createdBy:id,name', 'versions', 'payments']),
        ]);
    }

    public function update(ProposalRequest $request, Proposal $proposal): JsonResponse
    {
        $this->authorizeProposal($request, $proposal);

        if (in_array($proposal->status, ['accepted', 'rejected'], true)) {
            return response()->json(['message' => 'Proposta aceita ou rejeitada não pode ser editada.'], 422);
        }

        $data = $request->validated();

        DB::transaction(function () use ($proposal, $data, $request) {
            $items = collect($data['items'])->map(fn ($item) => [
                'description' => $item['description'],
                'quantity' => (float) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
            ])->values()->all();

            $subtotal = collect($items)->sum(fn ($item) => $item['quantity'] * $item['unit_price']);
            $discount = min((float) ($data['discount'] ?? 0), $subtotal);

            $proposal->fill([
                'title' => $data['title'],
                'valid_until' => $data['valid_until'] ?? $proposal->valid_until,
                'items' => $items,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => max(0, $subtotal - $discount),
                'payment_terms' => $data['payment_terms'] ?? null,
                'clauses' => $data['clauses'] ?? null,
                'notes' => $data['notes'] ?? null,
                'version' => $proposal->version + 1,
            ])->save();

            ProposalVersion::query()->create([
                'proposal_id' => $proposal->getKey(),
                'version' => $proposal->version,
                'snapshot' => $proposal->fresh()->toArray(),
                'created_by' => $request->user()->getKey(),
            ]);

            $this->audit->record('proposal.updated', $request->user(), $proposal, [], ['version' => $proposal->version, 'total' => (float) $proposal->total], [], $request);
        });

        return response()->json(['data' => $proposal->fresh(['lead:id,name', 'createdBy:id,name', 'versions']), 'message' => 'Proposta atualizada.']);
    }

    public function updateStatus(Request $request, Proposal $proposal): JsonResponse
    {
        $this->authorizeProposal($request, $proposal);
        $data = $request->validate([
            'status' => ['required', Rule::in(['draft', 'sent', 'accepted', 'rejected', 'expired'])],
        ]);

        $old = $proposal->only(['status', 'sent_at', 'accepted_at', 'rejected_at']);
        $proposal->status = $data['status'];

        if ($data['status'] === 'sent' && ! $proposal->sent_at) {
            $proposal->sent_at = now();
        } elseif ($data['status'] === 'accepted') {
            $proposal->accepted_at = now();
        } elseif ($data['status'] === 'rejected') {
            $proposal->rejected_at = now();
        }

        $proposal->save();
        $this->audit->record('proposal.status_changed', $request->user(), $proposal, $old, ['status' => $data['status']], [], $request);

        return response()->json(['data' => $proposal->fresh(['lead:id,name']), 'message' => 'Status da proposta atualizado.']);
    }

    public function pdf(Request $request, Proposal $proposal): Response
    {
        $this->authorizeProposal($request, $proposal);
        $content = $this->pdf->render($proposal);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="proposta-'.$proposal->number.'.pdf"',
        ]);
    }

    public function destroy(Request $request, Proposal $proposal): JsonResponse
    {
        $this->authorizeProposal($request, $proposal);
        abort_unless($request->user()->isAdmin() || $proposal->status === 'draft', 403);
        $proposal->delete();

        return response()->json(['message' => 'Proposta arquivada.']);
    }

    private function authorizeLead(Request $request, Lead $lead): void
    {
        abort_unless(Lead::query()->visibleTo($request->user())->whereKey($lead->getKey())->exists(), 403, 'Lead não acessível.');
    }

    private function authorizeProposal(Request $request, Proposal $proposal): void
    {
        abort_unless(
            Lead::query()->visibleTo($request->user())->whereKey($proposal->lead_id)->exists(),
            403,
            'Proposta não acessível.'
        );
    }

    private function number(): string
    {
        return 'PROP-'.now()->format('Ym').'-'.strtoupper(Str::random(6));
    }
}
