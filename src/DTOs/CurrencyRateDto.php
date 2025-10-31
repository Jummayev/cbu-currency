<?php

namespace Cbu\Currency\DTOs;

class CurrencyRateDto
{
    public function __construct(
        public readonly string $currencyCode,
        public readonly string $currencyName,
        public readonly float $rate,
        public readonly float $diff,
        public readonly int $nominal,
        public readonly string $date,
    ) {
    }

    public function toArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'currency_name' => $this->currencyName,
            'rate' => $this->rate,
            'diff' => $this->diff,
            'nominal' => $this->nominal,
            'date' => $this->date,
        ];
    }
}
