<?php

namespace App\Services\Documents;

use App\Models\Contract;

class ContractPdfService
{
    public function render(Contract $contract): string
    {
        $contract->loadMissing(['lead', 'proposal']);

        $pdf = new PdfDocument();
        $pdf->title('Contrato '.$contract->number, 'Closer OS — documento gerado eletronicamente');
        $pdf->heading('Partes e objeto');
        $pdf->text('Contratante: '.($contract->lead?->name ?? 'Não informado'), 10, true);
        $pdf->text('Contrato: '.$contract->title);
        $pdf->text('Valor total: R$ '.number_format((float) $contract->total, 2, ',', '.'));
        $pdf->text('Vigência: '.($contract->start_date?->format('d/m/Y') ?? 'a definir').' a '.($contract->end_date?->format('d/m/Y') ?? 'a definir'));
        $pdf->text('Status: '.$contract->status);
        $pdf->heading('Conteúdo');
        $pdf->text($contract->content);
        $pdf->spacer(16);
        $pdf->divider();
        $pdf->text('Documento gerado eletronicamente. Não representa assinatura digital nem validação jurídica automática.', 8);

        return $pdf->output();
    }
}
