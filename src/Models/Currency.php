<?php

namespace Cbu\Currency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    protected $fillable = [
        'cbu_id',
        'code',
        'ccy',
        'name_ru',
        'name_uz',
        'name_oz',
        'name_en',
    ];

    public function rates(): HasMany
    {
        return $this->hasMany(CurrencyRate::class);
    }
}
