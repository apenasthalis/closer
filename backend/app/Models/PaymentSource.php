<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PaymentSource extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug', 'name', 'enters_cash', 'active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'enters_cash' => 'boolean',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public static function defaultSources(): array
    {
        return [
            ['slug' => 'boleto', 'name' => 'Boleto', 'enters_cash' => false, 'position' => 1],
            ['slug' => 'pix', 'name' => 'Pix', 'enters_cash' => true, 'position' => 2],
            ['slug' => 'credit_card', 'name' => 'Cartão de crédito', 'enters_cash' => true, 'position' => 3],
            ['slug' => 'debit_card', 'name' => 'Cartão de débito', 'enters_cash' => true, 'position' => 4],
            ['slug' => 'contrato_parcelas', 'name' => 'Contrato com parcelas', 'enters_cash' => true, 'position' => 5],
            ['slug' => 'transfer', 'name' => 'Transferência', 'enters_cash' => true, 'position' => 6],
            ['slug' => 'cash', 'name' => 'Dinheiro', 'enters_cash' => true, 'position' => 7],
            ['slug' => 'other', 'name' => 'Outro', 'enters_cash' => true, 'position' => 8],
        ];
    }
}
