<?php

namespace Cbu\Currency;

use Cbu\Currency\DTOs\ConversionResultDto;
use Cbu\Currency\DTOs\CurrencyRateDto;
use Cbu\Currency\Enums\CurrencyCode;
use Cbu\Currency\Enums\SourceType;
use Cbu\Currency\Models\Currency;
use Cbu\Currency\Models\CurrencyRate;
use Cbu\Currency\Services\CbuApiService;

class CbuCurrency
{
    protected int $scale;
    protected string $source;
    protected CbuApiService $apiService;

    public function __construct(CbuApiService $apiService)
    {
        $this->scale = config('cbu-currency.scale', 2);
        $this->source = config('cbu-currency.source', 'database');
        $this->apiService = $apiService;
    }

    /**
     * Set the data source for currency operations
     *
     * @param SourceType|string $source Data source ('database' or 'api')
     * @return self
     */
    public function source(SourceType|string $source): self
    {
        $sourceValue = $source instanceof SourceType ? $source->value : $source;

        // Create a new instance with the specified source
        $instance = clone $this;
        $instance->source = $sourceValue;

        return $instance;
    }

    /**
     * Get currency rate by code and date
     *
     * @param CurrencyCode|string $currencyCode Currency code (e.g., USD, EUR) or CurrencyCode enum
     * @param string|null $date Date in Y-m-d format, null for today
     * @return CurrencyRateDto|null
     */
    public function getRate(CurrencyCode|string $currencyCode, ?string $date = null): ?CurrencyRateDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCodeValue = $currencyCode instanceof CurrencyCode ? $currencyCode->value : $currencyCode;

        // Fetch from API if source is 'api'
        if ($this->source === 'api') {
            return $this->getRateFromApi($currencyCodeValue, $date);
        }

        // Otherwise, fetch from database
        return $this->getRateFromDatabase($currencyCodeValue, $date);
    }

    /**
     * Get currency rate from database
     *
     * @param string $currencyCode Currency code
     * @param string $date Date in Y-m-d format
     * @return CurrencyRateDto|null
     */
    protected function getRateFromDatabase(string $currencyCode, string $date): ?CurrencyRateDto
    {
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
     * Get currency rate from CBU API
     *
     * @param string $currencyCode Currency code
     * @param string $date Date in Y-m-d format
     * @return CurrencyRateDto|null
     */
    protected function getRateFromApi(string $currencyCode, string $date): ?CurrencyRateDto
    {
        try {
            $rateData = $this->apiService->fetchRateFromApi($currencyCode, $date);

            if (!$rateData) {
                return null;
            }

            return new CurrencyRateDto(
                currencyCode: $rateData['ccy'],
                currencyName: $rateData['name_en'],
                rate: (float) $rateData['rate'],
                diff: (float) $rateData['diff'],
                nominal: $rateData['nominal'],
                date: $rateData['date'],
            );
        } catch (\Exception $e) {
            // Return null if API request fails
            return null;
        }
    }

    /**
     * Convert from one currency to another
     *
     * @param CurrencyCode|string $fromCurrency Source currency code or CurrencyCode enum
     * @param CurrencyCode|string $toCurrency Target currency code or CurrencyCode enum
     * @param float $amount Amount to convert
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     */
    public function convert(CurrencyCode|string $fromCurrency, CurrencyCode|string $toCurrency, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $fromCurrencyValue = $fromCurrency instanceof CurrencyCode ? $fromCurrency->value : $fromCurrency;
        $toCurrencyValue = $toCurrency instanceof CurrencyCode ? $toCurrency->value : $toCurrency;
        $fromCurrency = strtoupper($fromCurrencyValue);
        $toCurrency = strtoupper($toCurrencyValue);

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
     * @param CurrencyCode|string $currencyCode Source currency code or CurrencyCode enum
     * @param float $amount Amount in foreign currency
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     */
    public function toUzs(CurrencyCode|string $currencyCode, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCodeValue = $currencyCode instanceof CurrencyCode ? $currencyCode->value : $currencyCode;
        $currencyCode = strtoupper($currencyCodeValue);

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
     * @param CurrencyCode|string $currencyCode Target currency code or CurrencyCode enum
     * @param float $amount Amount in UZS
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     */
    public function fromUzs(CurrencyCode|string $currencyCode, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCodeValue = $currencyCode instanceof CurrencyCode ? $currencyCode->value : $currencyCode;
        $currencyCode = strtoupper($currencyCodeValue);

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

        // Fetch from API if source is 'api'
        if ($this->source === 'api') {
            return $this->getAllRatesFromApi($date);
        }

        // Otherwise, fetch from database
        return $this->getAllRatesFromDatabase($date);
    }

    /**
     * Get all currency rates from database
     *
     * @param string $date Date in Y-m-d format
     * @return array<CurrencyRateDto>
     */
    protected function getAllRatesFromDatabase(string $date): array
    {
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

    /**
     * Get all currency rates from CBU API
     *
     * @param string $date Date in Y-m-d format
     * @return array<CurrencyRateDto>
     */
    protected function getAllRatesFromApi(string $date): array
    {
        try {
            $ratesData = $this->apiService->fetchAllRatesFromApi($date);

            return array_map(function ($rateData) {
                return new CurrencyRateDto(
                    currencyCode: $rateData['ccy'],
                    currencyName: $rateData['name_en'],
                    rate: (float) $rateData['rate'],
                    diff: (float) $rateData['diff'],
                    nominal: $rateData['nominal'],
                    date: $rateData['date'],
                );
            }, $ratesData);
        } catch (\Exception $e) {
            // Return empty array if API request fails
            return [];
        }
    }
}
