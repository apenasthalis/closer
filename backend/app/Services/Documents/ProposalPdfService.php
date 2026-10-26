<?php

namespace App\Services\Documents;

use App\Models\Proposal;

class ProposalPdfService
{
    public function render(Proposal $proposal): string
    {
        $proposal->loadMissing(['lead.niche', 'lead.closer', 'createdBy']);
        $lead = $proposal->lead;

        $pdf = new PdfDocument();
        $pdf->title('Proposta Comercial '.$proposal->number, 'Closer OS — documento gerado eletronicamente');

        $pdf->heading('Dados da proposta');
        $pdf->row(['Número', $proposal->number, 'Versão', 'v'.$proposal->version], [90, 150, 60, 195]);
        $pdf->row(['Emissão', now()->format('d/m/Y'), 'Validade', $proposal->valid_until?->format('d/m/Y') ?? 'Não informada'], [90, 150, 60, 195]);
        $pdf->row(['Status', $proposal->status, 'Responsável', $proposal->createdBy?->name ?? $lead?->closer?->name ?? '—'], [90, 150, 80, 175]);

        $pdf->heading('Cliente');
        $pdf->text($lead?->name ?? 'Cliente não informado', 11, true);
        $pdf->text(collect([
            $lead?->company,
            $lead?->email,
            $lead?->phone,
            $lead?->niche?->name,
        ])->filter()->implode(' • ') ?: 'Sem dados complementares.');

        $pdf->heading('Serviços e produtos');
        $pdf->divider();
        $pdf->row(['Descrição', 'Qtd.', 'Unitário', 'Total'], [295, 50, 75, 75], 9, true);
        $pdf->divider();

        foreach ($proposal->items ?? [] as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unit = (float) ($item['unit_price'] ?? 0);
            $pdf->row([
                (string) ($item['description'] ?? 'Item'),
                number_format($quantity, 2, ',', '.'),
                'R$ '.number_format($unit, 2, ',', '.'),
                'R$ '.number_format($quantity * $unit, 2, ',', '.'),
            ], [295, 50, 75, 75]);
        }

        $pdf->divider();
        $pdf->row(['Subtotal', '', '', 'R$ '.number_format((float) $proposal->subtotal, 2, ',', '.')], [295, 50, 75, 75], 9, true);
        $pdf->row(['Desconto', '', '', '- R$ '.number_format((float) $proposal->discount, 2, ',', '.')], [295, 50, 75, 75]);
        $pdf->row(['Total', '', '', 'R$ '.number_format((float) $proposal->total, 2, ',', '.')], [295, 50, 75, 75], 11, true);

        if ($proposal->payment_terms) {
            $pdf->heading('Condições de pagamento');
            $pdf->text($proposal->payment_terms);
        }

        if (! empty($proposal->clauses)) {
            $pdf->heading('Cláusulas');
            foreach ($proposal->clauses as $index => $clause) {
                $pdf->text(($index + 1).'. '.(string) $clause);
            }
        }

        if ($proposal->notes) {
            $pdf->heading('Observações');
            $pdf->text($proposal->notes);
        }

        $pdf->spacer(16);
        $pdf->divider();
        $pdf->text('Este documento foi gerado eletronicamente e não possui assinatura digital. A validade jurídica depende de assinatura das partes e das condições aplicáveis.', 8);
        $pdf->text('Valores e condições estão sujeitos à confirmação comercial e à disponibilidade descrita nesta proposta.', 8);

        return $pdf->output();
    }
}
