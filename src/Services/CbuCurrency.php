<?php

namespace Cbu\Currency;

use Cbu\Currency\DTOs\ConversionResultDto;
use Cbu\Currency\DTOs\CurrencyRateDto;
use Cbu\Currency\Models\Currency;
use Cbu\Currency\Models\CurrencyRate;

class CbuCurrency
{
    protected int $scale;

    public function __construct()
    {
        $this->scale = config('cbu-currency.scale', 2);
    }

    /**
     * Get currency rate by code and date
     *
     * @param string $currencyCode Currency code (e.g., USD, EUR)
     * @param string|null $date Date in Y-m-d format, null for today
     * @return CurrencyRateDto|null
     */
    public function getRate(string $currencyCode, ?string $date = null): ?CurrencyRateDto
    {
        $date = $date ?? now()->format('Y-m-d');

        $currencyRate = CurrencyRate::query()
            ->whereHas('currency', function ($query) use ($currencyCode) {
                $query->where('ccy', strtoupper($currencyCode));
            })
            ->where('date', $date)
            ->with('currency')
            ->first();

        if (!$currencyRate) {
            return null;
        }

        return new CurrencyRateDto(
            currencyCode: $currencyRate->currency->ccy,
            currencyName: $currencyRate->currency->name_en,
            rate: (float) $currencyRate->rate,
            diff: (float) $currencyRate->diff,
            nominal: $currencyRate->nominal,
            date: $currencyRate->date->format('Y-m-d'),
        );
    }

    /**
     * Convert from one currency to another
     *
     * @param string $fromCurrency Source currency code
     * @param string $toCurrency Target currency code
     * @param float $amount Amount to convert
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     */
    public function convert(string $fromCurrency, string $toCurrency, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        // If same currency, return same amount
        if ($fromCurrency === $toCurrency) {
            $rate = null;
            $amountInUzsValue = (string) $amount;

            // If not UZS, get the rate
            if ($fromCurrency !== 'UZS') {
                $rateDto = $this->getRate($fromCurrency, $date);
                if ($rateDto) {
                    $rate = $rateDto->rate;
                    $amountInUzsValue = bcmul((string) $amount, (string) $rate, $this->scale);
                }
            }

            return new ConversionResultDto(
                amount: $amount,
                fromCurrency: $fromCurrency,
                toCurrency: $toCurrency,
                result: $amount,
                fromRate: $rate,
                toRate: $rate,
                amountInUzs: (float) $amountInUzsValue,
                date: $date,
            );
        }

        // If from UZS
        if ($fromCurrency === 'UZS') {
            return $this->fromUzs($toCurrency, $amount, $date);
        }

        // If to UZS
        if ($toCurrency === 'UZS') {
            return $this->toUzs($fromCurrency, $amount, $date);
        }

        // Convert from -> UZS -> to
        $fromRateDto = $this->getRate($fromCurrency, $date);
        $toRateDto = $this->getRate($toCurrency, $date);

        if (!$fromRateDto || !$toRateDto) {
            return null;
        }

        $amountInUzs = bcmul((string) $amount, (string) $fromRateDto->rate, $this->scale);
        $result = bcdiv($amountInUzs, (string) $toRateDto->rate, $this->scale);

        return new ConversionResultDto(
            amount: $amount,
            fromCurrency: $fromCurrency,
            toCurrency: $toCurrency,
            result: (float) $result,
            fromRate: $fromRateDto->rate,
            toRate: $toRateDto->rate,
            amountInUzs: (float) $amountInUzs,
            date: $date,
        );
    }

    /**
     * Convert from foreign currency to UZS
     *
     * @param string $currencyCode Source currency code
     * @param float $amount Amount in foreign currency
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     */
    public function toUzs(string $currencyCode, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCode = strtoupper($currencyCode);

        $rateDto = $this->getRate($currencyCode, $date);

        if (!$rateDto) {
            return null;
        }

        $result = bcmul((string) $amount, (string) $rateDto->rate, $this->scale);

        return new ConversionResultDto(
            amount: $amount,
            fromCurrency: $currencyCode,
            toCurrency: 'UZS',
            result: (float) $result,
            fromRate: $rateDto->rate,
            toRate: null,
            amountInUzs: (float) $result,
            date: $date,
        );
    }

    /**
     * Convert from UZS to foreign currency
     *
     * @param string $currencyCode Target currency code
     * @param float $amount Amount in UZS
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     */
    public function fromUzs(string $currencyCode, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCode = strtoupper($currencyCode);

        $rateDto = $this->getRate($currencyCode, $date);

        if (!$rateDto) {
            return null;
        }

        $result = bcdiv((string) $amount, (string) $rateDto->rate, $this->scale);

        return new ConversionResultDto(
            amount: $amount,
            fromCurrency: 'UZS',
            toCurrency: $currencyCode,
            result: (float) $result,
            fromRate: null,
            toRate: $rateDto->rate,
            amountInUzs: $amount,
            date: $date,
        );
    }

    /**
     * Get all currency rates for a specific date
     *
     * @param string|null $date Date in Y-m-d format, null for today
     * @return array<CurrencyRateDto>
     */
    public function getAllRates(?string $date = null): array
    {
        $date = $date ?? now()->format('Y-m-d');

        $rates = CurrencyRate::query()
            ->where('date', $date)
            ->with('currency')
            ->get();

        return $rates->map(function (CurrencyRate $rate) {
            return new CurrencyRateDto(
                currencyCode: $rate->currency->ccy,
                currencyName: $rate->currency->name_en,
                rate: (float) $rate->rate,
                diff: (float) $rate->diff,
                nominal: $rate->nominal,
                date: $rate->date->format('Y-m-d'),
            );
        })->toArray();
    }
}
