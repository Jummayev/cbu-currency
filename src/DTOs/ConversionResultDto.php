<?php

namespace Cbu\Currency\DTOs;

class ConversionResultDto
{
    public function __construct(
        public readonly float $amount,
        public readonly string $fromCurrency,
        public readonly string $toCurrency,
        public readonly float $result,
        public readonly ?float $fromRate,
        public readonly ?float $toRate,
        public readonly float $amountInUzs,
        public readonly string $date,
    ) {
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'from_currency' => $this->fromCurrency,
            'to_currency' => $this->toCurrency,
            'result' => $this->result,
            'from_rate' => $this->fromRate,
            'to_rate' => $this->toRate,
            'amount_in_uzs' => $this->amountInUzs,
            'date' => $this->date,
        ];
    }
}

