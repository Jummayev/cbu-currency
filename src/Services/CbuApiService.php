<?php

namespace Cbu\Currency\Services;

use Cbu\Currency\Exceptions\CbuApiException;
use Cbu\Currency\Models\Currency;
use Cbu\Currency\Models\CurrencyRate;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Http;

class CbuApiService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('cbu-currency.base_url');
    }

    /**
     * Fetch data from CBU API
     *
     * @param string $url
     * @return array
     * @throws CbuApiException
     */
    protected function fetchFromApi(string $url): array
    {
        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                throw CbuApiException::requestFailed($url, $response->status());
            }

            $data = $response->json();

            if (empty($data)) {
                throw CbuApiException::noDataReceived($url);
            }

            return $data;
        } catch (CbuApiException $e) {
            throw $e;
        } catch (Exception $e) {
            throw CbuApiException::connectionError($url, $e->getMessage());
        }
    }

    /**
     * Fetch and store currency rates for a specific date
     *
     * @param string|null $date Date in Y-m-d format, null for today
     * @return void
     * @throws CbuApiException
     */
    public function fetchAndStore(string $date): void
    {
        $this->isValidDate($date);
        $url = "{$this->baseUrl}/all/{$date}/";

        $data = $this->fetchFromApi($url);

        foreach ($data as $item) {
            // Find or create currency
            $currency = Currency::query()->firstOrCreate(
                ['ccy' => $item['Ccy']],
                [
                    'cbu_id' => $item['id'],
                    'code' => $item['Code'],
                    'name_uz' => $item['CcyNm_UZ'],
                    'name_oz' => $item['CcyNm_UZC'],
                    'name_ru' => $item['CcyNm_RU'],
                    'name_en' => $item['CcyNm_EN'],
                ]
            );

            // Create or update currency rate
            CurrencyRate::query()->updateOrCreate(
                [
                    'currency_id' => $currency->id,
                    'date' => $date,
                ],
                [
                    'rate' => $item['Rate'],
                    'currency_date' => $item['Date'],
                    'diff' => $item['Diff'],
                    'nominal' => $item['Nominal'],
                ]
            );
        }
    }

    /**
     * Sync currencies only (without rates)
     *
     * @return array
     */
    public function syncCurrencies(): array
    {
        $date = now()->format('Y-m-d');
        $url = "{$this->baseUrl}/all/{$date}/";

        try {
            $data = $this->fetchFromApi($url);

            $currenciesAdded = 0;
            $currenciesUpdated = 0;

            foreach ($data as $item) {
                $currency = Currency::query()->updateOrCreate(
                    ['ccy' => $item['Ccy']],
                    [
                        'cbu_id' => $item['id'],
                        'code' => $item['Code'],
                        'name_uz' => $item['CcyNm_UZ'] ?? '',
                        'name_oz' => $item['CcyNm_UZC'] ?? '',
                        'name_ru' => $item['CcyNm_RU'] ?? '',
                        'name_en' => $item['CcyNm_EN'] ?? '',
                    ]
                );

                if ($currency->wasRecentlyCreated) {
                    $currenciesAdded++;
                } else {
                    $currenciesUpdated++;
                }
            }

            return [
                'success' => true,
                'message' => 'Currencies synced successfully',
                'currencies_added' => $currenciesAdded,
                'currencies_updated' => $currenciesUpdated,
                'total_currencies' => count($data),
            ];
        } catch (CbuApiException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'currencies_added' => 0,
                'currencies_updated' => 0,
            ];
        }
    }

    /**
     * Fetch rate for a specific currency from CBU API without storing
     *
     * @param string $currencyCode Currency code (e.g., USD, EUR)
     * @param string $date Date in Y-m-d format
     * @return array|null
     * @throws CbuApiException
     */
    public function fetchRateFromApi(string $currencyCode, string $date): ?array
    {
        $this->isValidDate($date);
        $url = "{$this->baseUrl}/all/{$date}/";

        $data = $this->fetchFromApi($url);

        foreach ($data as $item) {
            if (strtoupper($item['Ccy']) === strtoupper($currencyCode)) {
                return [
                    'ccy' => $item['Ccy'],
                    'rate' => $item['Rate'],
                    'diff' => $item['Diff'],
                    'nominal' => $item['Nominal'],
                    'date' => $date,
                    'currency_date' => $item['Date'],
                    'name_en' => $item['CcyNm_EN'] ?? '',
                    'name_uz' => $item['CcyNm_UZ'] ?? '',
                    'name_oz' => $item['CcyNm_UZC'] ?? '',
                    'name_ru' => $item['CcyNm_RU'] ?? '',
                ];
            }
        }

        return null;
    }

    /**
     * Fetch all rates from CBU API without storing
     *
     * @param string $date Date in Y-m-d format
     * @return array
     * @throws CbuApiException
     */
    public function fetchAllRatesFromApi(string $date): array
    {
        $this->isValidDate($date);
        $url = "{$this->baseUrl}/all/{$date}/";

        $data = $this->fetchFromApi($url);

        return array_map(function ($item) use ($date) {
            return [
                'ccy' => $item['Ccy'],
                'rate' => $item['Rate'],
                'diff' => $item['Diff'],
                'nominal' => $item['Nominal'],
                'date' => $date,
                'currency_date' => $item['Date'],
                'name_en' => $item['CcyNm_EN'] ?? '',
                'name_uz' => $item['CcyNm_UZ'] ?? '',
                'name_oz' => $item['CcyNm_UZC'] ?? '',
                'name_ru' => $item['CcyNm_RU'] ?? '',
            ];
        }, $data);
    }

    protected function isValidDate(string $date): void
    {
        try {
            $d = DateTime::createFromFormat('Y-m-d', $date);
            if ($d && $d->format('Y-m-d') === $date) {
                return;
            } else {
                CbuApiException::dateFormatInvalid($date, '');
            }
        } catch (Exception $e) {
            CbuApiException::dateFormatInvalid($date, $e->getMessage());
        }
    }
}
