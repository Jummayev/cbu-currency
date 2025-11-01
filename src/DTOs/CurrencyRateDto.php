<?php

namespace Cbu\Currency\DTOs;

use Cbu\Currency\Models\CurrencyRate;

class CurrencyRateDto
{
    public function __construct(
        public float  $rate,
        public float  $diff,
        public int    $nominal,
        public string $date,
        public string $ccy,
        public string $currency_date,
        public string $name_en,
        public string $name_uz,
        public string $name_oz,
        public string $name_ru,
    )
    {
    }


    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
            'diff' => $this->diff,
            'nominal' => $this->nominal,
            'date' => $this->date,
            'ccy' => $this->ccy,
            'currency_date' => $this->currency_date,
            'name_en' => $this->name_en,
            'name_uz' => $this->name_uz,
            'name_oz' => $this->name_oz,
            'name_ru' => $this->name_ru,
        ];
    }

    public static function setDataFromApi(array $data): self
    {
        return new self(
            rate: $data['rate'],
            diff: $data['diff'],
            nominal: $data['nominal'],
            date: $data['date'],
            ccy: $data['ccy'],
            currency_date: $data['currency_date'],
            name_en: $data['name_en'],
            name_uz: $data['name_uz'],
            name_oz: $data['name_oz'],
            name_ru: $data['name_ru'],
        );
    }

    public static function setDataFromModel(CurrencyRate $model): self
    {
        return new self(
            rate: $model->rate,
            diff: $model->diff,
            nominal: $model->nominal,
            date: $model->date,
            ccy: $model->currency->ccy,
            currency_date: $model->currency_date,
            name_en: $model->currency->name_en,
            name_uz: $model->currency->name_uz,
            name_oz: $model->currency->name_oz,
            name_ru: $model->currency->name_ru,
        );
    }
}
