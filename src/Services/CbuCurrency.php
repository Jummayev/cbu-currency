<?php

namespace Cbu\Currency;

use Cbu\Currency\DTOs\ConversionResultDto;
use Cbu\Currency\DTOs\CurrencyRateDto;
use Cbu\Currency\Enums\CurrencyCode;
use Cbu\Currency\Enums\CurrencySource;
use Cbu\Currency\Exceptions\CbuApiException;
use Cbu\Currency\Models\CurrencyRate;
use Cbu\Currency\Services\CbuApiService;
use Illuminate\Support\Collection;

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
     * @param CurrencySource $source
     * @return self
     */
    public function source(CurrencySource $source): self
    {
        $sourceValue = $source->value;
        $instance = clone $this;
        $instance->source = $sourceValue;
        return $instance;
    }

    /**
     * Get currency rate by code and date
     *
     * @param CurrencyCode|string $currencyCode Currency code (e.g., USD, EUR)
     * @param string|null $date Date in Y-m-d format, null for today
     * @return CurrencyRateDto
     * @throws CbuApiException
     */
    public function getRate(CurrencyCode|string $currencyCode, ?string $date = null): CurrencyRateDto
    {
        $date = $date ?? now()->format('Y-m-d');

        // Fetch from API if source is 'api'
        if ($this->source === 'api') {
            return $this->getRateFromApi($currencyCode, $date);
        }

        // Otherwise, fetch from database
        return $this->getRateFromDatabase($currencyCode, $date);
    }

    /**
     * Get currency rate from database
     *
     * @param CurrencyCode|string $currencyCode Currency code
     * @param string $date Date in Y-m-d format
     * @return CurrencyRateDto|null
     * @throws CbuApiException
     */
    protected function getRateFromDatabase(CurrencyCode|string $currencyCode, string $date): ?CurrencyRateDto
    {
        $currencyRate = CurrencyRate::query()
            ->whereHas('currency', function ($query) use ($currencyCode) {
                $query->where('ccy', strtoupper($currencyCode));
            })
            ->where('date', $date)
            ->with('currency')
            ->first();

        if (!$currencyRate) {
            $this->apiService->fetchAndStore($date);
            $currencyRate = CurrencyRate::query()
                ->whereHas('currency', function ($query) use ($currencyCode) {
                    $query->where('ccy', strtoupper($currencyCode));
                })
                ->where('date', $date)
                ->with('currency')
                ->first();
        }


        if (!$currencyRate) {
            throw new CbuApiException("Rate not found for currency $currencyCode on $date");
        }

        return CurrencyRateDto::setDataFromModel($currencyRate);
    }

    /**
     * Get currency rate from CBU API
     *
     * @param CurrencyCode|string $currencyCode Currency code
     * @param string $date Date in Y-m-d format
     * @return CurrencyRateDto
     * @throws CbuApiException
     */
    protected function getRateFromApi(CurrencyCode|string $currencyCode, string $date): CurrencyRateDto
    {
        return $this->apiService->fetchRateFromApi($currencyCode, $date);
    }

    /**
     * Convert from one currency to another
     *
     * @param CurrencyCode|string $fromCurrency Source currency code
     * @param CurrencyCode|string $toCurrency Target currency code
     * @param float $amount Amount to convert
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto|null
     * @throws CbuApiException
     */
    public function convert(CurrencyCode|string $fromCurrency, CurrencyCode|string $toCurrency, float $amount, ?string $date = null): ?ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        // If same currency, return same amount
        if ($fromCurrency === $toCurrency) {
            $rate = null;
            $amountInUzsValue = (string)$amount;

            // If not UZS, get the rate
            if ($fromCurrency !== 'UZS') {
                $rateDto = $this->getRate($fromCurrency, $date);
                $rate = $rateDto->rate;
                $amountInUzsValue = bcmul((string)$amount, (string)$rate, $this->scale);
            }

            return new ConversionResultDto(
                amount: $amount,
                fromCurrency: $fromCurrency,
                toCurrency: $toCurrency,
                result: $amount,
                fromRate: $rate,
                toRate: $rate,
                amountInUzs: (float)$amountInUzsValue,
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

        $amountInUzs = bcmul((string)$amount, (string)$fromRateDto->rate, $this->scale);
        $result = bcdiv($amountInUzs, (string)$toRateDto->rate, $this->scale);

        return new ConversionResultDto(
            amount: $amount,
            fromCurrency: $fromCurrency,
            toCurrency: $toCurrency,
            result: (float)$result,
            fromRate: $fromRateDto->rate,
            toRate: $toRateDto->rate,
            amountInUzs: (float)$amountInUzs,
            date: $date,
        );
    }

    /**
     *
     * Convert from foreign currency to UZS
     *
     * @param CurrencyCode|string $currencyCode
     * @param float $amount Amount in foreign currency
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto
     * @throws CbuApiException
     * @throws CbuApiException
     */
    public function toUzs(CurrencyCode|string $currencyCode, float $amount, ?string $date = null): ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCode = strtoupper($currencyCode);

        $rateDto = $this->getRate($currencyCode, $date);

        $result = bcmul((string)$amount, (string)$rateDto->rate, $this->scale);

        return new ConversionResultDto(
            amount: $amount,
            fromCurrency: $currencyCode,
            toCurrency: 'UZS',
            result: (float)$result,
            fromRate: $rateDto->rate,
            toRate: null,
            amountInUzs: (float)$result,
            date: $date,
        );
    }

    /**
     * Convert from UZS to foreign currency
     *
     * @param CurrencyCode|string $currencyCode Target currency code
     * @param float $amount Amount in UZS
     * @param string|null $date Date in Y-m-d format, null for today
     * @return ConversionResultDto
     * @throws CbuApiException
     */
    public function fromUzs(CurrencyCode|string $currencyCode, float $amount, ?string $date = null): ConversionResultDto
    {
        $date = $date ?? now()->format('Y-m-d');
        $currencyCode = strtoupper($currencyCode);

        $rateDto = $this->getRate($currencyCode, $date);

        $result = bcdiv((string)$amount, (string)$rateDto->rate, $this->scale);

        return new ConversionResultDto(
            amount: $amount,
            fromCurrency: 'UZS',
            toCurrency: $currencyCode,
            result: (float)$result,
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
     * @return Collection
     * @throws CbuApiException
     */
    public function getAllRates(?string $date = null): Collection
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
     */
    protected function getAllRatesFromDatabase(string $date): Collection
    {
        $rates = CurrencyRate::query()
            ->where('date', $date)
            ->with('currency')
            ->get();

        return $rates->map(function (CurrencyRate $rate) {
            return CurrencyRateDto::setDataFromModel($rate);
        });
    }

    /**
     * Get all currency rates from CBU API
     *
     * @param string $date Date in Y-m-d format
     * @return Collection
     * @throws CbuApiException
     */
    protected function getAllRatesFromApi(string $date): Collection
    {
        return $this->apiService->fetchAllRatesFromApi($date);
    }
}
