<?php

namespace Cbu\Currency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyRate extends Model
{
    protected $fillable = [
        'currency_id',
        'date',
        'currency_date',
        'rate',
        'diff',
        'nominal',
    ];

    protected $casts = [
        'date' => 'date',
        'currency_date' => 'date',
        'rate' => 'decimal:4',
        'diff' => 'decimal:4',
    ];

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
