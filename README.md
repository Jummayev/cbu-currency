# CBU Currency - Central Bank of Uzbekistan Currency Rates

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.2-blue)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-%5E10%7C%5E11%7C%5E12-red)](https://laravel.com/)

A Laravel package for working with Central Bank of Uzbekistan (CBU) currency exchange rates. This package provides easy-to-use methods for fetching, storing, and converting currencies with high precision using BCMath.

[Uzbek version](README_UZ.md) | English version

## Features

- 📊 Fetch and store currency rates from CBU API
- 💱 Currency conversion with BCMath precision
- 🗄️ Database storage for historical rates
- 🎯 Simple and intuitive API
- ⚙️ Configurable precision and settings
- 🔄 Automatic currency synchronization
- 📅 Historical rate support

## Requirements

- PHP ^8.2
- Laravel ^10.0|^11.0|^12.0
- BCMath PHP Extension
- GuzzleHTTP ^7.0

## Installation

Install the package via Composer:

```bash
composer require jummayev/cbu-currency
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=cbu-currency-config
```

Run migrations:

```bash
php artisan migrate
```

## Configuration

The configuration file is located at `config/cbu-currency.php`:

```php
return [
    // CBU API Base URL
    'base_url' => env('CBU_BASE_URL', 'https://cbu.uz/ru/arkhiv-kursov-valyut/json'),

    // Cache duration in minutes
    'cache_duration' => env('CBU_CACHE_DURATION', 60),

    // Default currency code
    'default_currency' => env('CBU_DEFAULT_CURRENCY', 'USD'),

    // BCMath calculation scale (decimal places)
    'scale' => env('CBU_SCALE', 2),

    // Data source: 'database' or 'api'
    'source' => env('CBU_SOURCE', 'database'),
];
```

### Environment Variables

Add these to your `.env` file:

```env
CBU_BASE_URL=https://cbu.uz/ru/arkhiv-kursov-valyut/json
CBU_CACHE_DURATION=60
CBU_DEFAULT_CURRENCY=USD
CBU_SCALE=2
CBU_SOURCE=database
```

#### Data Source Configuration

The `CBU_SOURCE` variable determines where currency rates are fetched from:

- **`database`** (default): Fetches rates from the local database. This is faster and works offline after initial data sync. Requires running `php artisan cbu:fetch-rates` to populate the database.

- **`api`**: Fetches rates directly from the CBU API in real-time. This provides live data but is slower and requires an internet connection for each request.

**Example usage:**

```env
# Use database (recommended for production)
CBU_SOURCE=database

# Use live API (useful for always getting fresh data)
CBU_SOURCE=api
```

## Usage

### Artisan Commands

#### Sync Currencies

Fetch and update currency list from CBU:

```bash
php artisan cbu:sync-currencies
```

#### Fetch Currency Rates

Fetch and store currency rates for a specific date:

```bash
# Fetch today's rates
php artisan cbu:fetch-rates

# Fetch rates for specific date
php artisan cbu:fetch-rates 2025-01-25
```

### Using the Service

#### Get Currency Rate

```php
use Cbu\Currency\Facades\CbuCurrency;

// Get today's USD rate
$rate = CbuCurrency::getRate('USD');

// Get rate for specific date
$rate = CbuCurrency::getRate('USD', '2025-01-25');

// Access rate data
echo $rate->currencyCode;  // "USD"
echo $rate->currencyName;  // "US Dollar"
echo $rate->rate;          // 12750.50
echo $rate->diff;          // 15.25
echo $rate->nominal;       // 1
echo $rate->date;          // "2025-01-25"
```

#### Currency Conversion

##### Convert between any currencies

```php
// Convert 100 USD to EUR
$result = CbuCurrency::convert('USD', 'EUR', 100, '2025-01-25');

echo $result->amount;        // 100
echo $result->fromCurrency;  // "USD"
echo $result->toCurrency;    // "EUR"
echo $result->result;        // 94.44
echo $result->fromRate;      // 12750.00 (1 USD = 12750 UZS)
echo $result->toRate;        // 13500.00 (1 EUR = 13500 UZS)
echo $result->amountInUzs;   // 1275000.00
echo $result->date;          // "2025-01-25"
```

##### Convert to UZS

```php
// Convert 100 USD to UZS
$result = CbuCurrency::toUzs('USD', 100);

echo $result->result;        // 1275000.00
echo $result->fromRate;      // 12750.00
echo $result->amountInUzs;   // 1275000.00
```

##### Convert from UZS

```php
// Convert 1000000 UZS to USD
$result = CbuCurrency::fromUzs('USD', 1000000);

echo $result->result;        // 78.43
echo $result->toRate;        // 12750.00
echo $result->amountInUzs;   // 1000000
```

#### Get All Rates

```php
// Get all rates for today
$rates = CbuCurrency::getAllRates();

// Get all rates for specific date
$rates = CbuCurrency::getAllRates('2025-01-25');

foreach ($rates as $rate) {
    echo "{$rate->currencyCode}: {$rate->rate}\n";
}
```

### Using Models Directly

```php
use Cbu\Currency\Models\Currency;
use Cbu\Currency\Models\CurrencyRate;

// Get all currencies
$currencies = Currency::all();

// Get specific currency
$usd = Currency::where('ccy', 'USD')->first();

// Get currency with rates
$currency = Currency::with('rates')->where('ccy', 'USD')->first();

// Get rates for specific date
$rates = CurrencyRate::where('date', '2025-01-25')
    ->with('currency')
    ->get();

// Get rate for specific currency and date
$rate = CurrencyRate::whereHas('currency', function($query) {
        $query->where('ccy', 'USD');
    })
    ->where('date', '2025-01-25')
    ->first();
```

## Database Structure

### Currencies Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| ccy | string | Currency code (unique) |
| name_uz | string | Name in Uzbek |
| name_oz | string | Name in Uzbek (Cyrillic) |
| name_ru | string | Name in Russian |
| name_en | string | Name in English |
| code | string | Numeric code |
| cbu_id | string | CBU identifier |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

### Currency Rates Table

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| currency_id | bigint | Foreign key to currencies |
| date | date | Rate date (indexed) |
| currency_date | date | Original CBU date |
| rate | decimal(15,4) | Exchange rate |
| diff | decimal(15,4) | Difference from previous |
| nominal | integer | Nominal value |
| created_at | timestamp | Creation time |
| updated_at | timestamp | Update time |

**Unique constraint**: `['currency_id', 'date']`

## DTOs

### CurrencyRateDto

```php
$rate->currencyCode;  // string
$rate->currencyName;  // string
$rate->rate;          // float
$rate->diff;          // float
$rate->nominal;       // int
$rate->date;          // string (Y-m-d)
```

### ConversionResultDto

```php
$result->amount;        // float - Original amount
$result->fromCurrency;  // string - Source currency
$result->toCurrency;    // string - Target currency
$result->result;        // float - Converted amount
$result->fromRate;      // ?float - Source currency rate to UZS
$result->toRate;        // ?float - Target currency rate to UZS
$result->amountInUzs;   // float - Amount in UZS
$result->date;          // string - Date (Y-m-d)
```

## Exceptions

The package throws `CbuApiException` for API-related errors:

```php
use Cbu\Currency\Exceptions\CbuApiException;

try {
    $result = CbuCurrency::convert('USD', 'EUR', 100);
} catch (CbuApiException $e) {
    echo $e->getMessage();
}
```

Exception types:
- `requestFailed()` - HTTP request failed
- `noDataReceived()` - Empty response from API
- `connectionError()` - Connection error
- `dateFormatInvalid()` - Invalid date format

## Testing

```bash
composer test
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## Credits

- [Jummayev Nurbek](https://github.com/Jummayev)
- [All Contributors](../../contributors)

## Support

For support, email jummayevnurbek279@gmail.com or create an issue on GitHub.
