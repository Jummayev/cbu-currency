# CBU Currency - O'zbekiston Respublikasi Markaziy Banki Valyuta Kurslari

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.2-blue)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-%5E10%7C%5E11%7C%5E12-red)](https://laravel.com/)

O'zbekiston Respublikasi Markaziy Banki (CBU) valyuta kurslar bilan ishlash uchun Laravel paketi. Ushbu paket BCMath yordamida yuqori aniqlikda valyuta kurslarini olish, saqlash va konvertatsiya qilish uchun qulay metodlarni taqdim etadi.

O'zbek versiya | [English version](README.md)

## Xususiyatlari

- 📊 CBU API dan valyuta kurslarini olish va saqlash
- 💱 BCMath aniqligida valyuta konvertatsiyasi
- 🗄️ Tarixiy kurslarni saqlash uchun database
- 🎯 Sodda va tushunarli API
- ⚙️ Sozlanuvchi aniqlik va parametrlar
- 🔄 Avtomatik valyuta sinxronizatsiyasi
- 📅 Tarixiy kurslarni qo'llab-quvvatlash

## Talablar

- PHP ^8.2
- Laravel ^10.0|^11.0|^12.0
- BCMath PHP Extension
- GuzzleHTTP ^7.0

## O'rnatish

Paketni Composer orqali o'rnating:

```bash
composer require jummayev/cbu-currency
```

Konfiguratsiya faylini nashr qiling:

```bash
php artisan vendor:publish --tag=cbu-currency-config
```

Migratsiyalarni ishga tushiring:

```bash
php artisan migrate
```

## Konfiguratsiya

Konfiguratsiya fayli `config/cbu-currency.php` da joylashgan:

```php
return [
    // CBU API Base URL
    'base_url' => env('CBU_BASE_URL', 'https://cbu.uz/ru/arkhiv-kursov-valyut/json'),

    // Cache vaqti (daqiqalarda)
    'cache_duration' => env('CBU_CACHE_DURATION', 60),

    // Default valyuta kodi
    'default_currency' => env('CBU_DEFAULT_CURRENCY', 'USD'),

    // BCMath hisob-kitob aniqligi (o'nlik xonalar)
    'scale' => env('CBU_SCALE', 2),

    // Ma'lumot manbai: 'database' yoki 'api'
    'source' => env('CBU_SOURCE', 'database'),
];
```

### Environment O'zgaruvchilari

`.env` fayliga quyidagilarni qo'shing:

```env
CBU_BASE_URL=https://cbu.uz/ru/arkhiv-kursov-valyut/json
CBU_CACHE_DURATION=60
CBU_DEFAULT_CURRENCY=USD
CBU_SCALE=2
CBU_SOURCE=database
```

#### Ma'lumot Manbai Konfiguratsiyasi

`CBU_SOURCE` o'zgaruvchisi valyuta kurslarini qayerdan olishni belgilaydi:

- **`database`** (standart): Kurslarni lokal ma'lumotlar bazasidan oladi. Bu tezroq ishlaydi va dastlabki sinxronizatsiyadan keyin offline rejimda ham ishlaydi. Ma'lumotlar bazasini to'ldirish uchun `php artisan cbu:fetch-rates` ni ishga tushirish kerak.

- **`api`**: Kurslarni to'g'ridan-to'g'ri CBU API'dan real vaqt rejimida oladi. Bu har doim yangi ma'lumotlarni beradi, lekin sekinroq ishlaydi va har bir so'rov uchun internet aloqasi talab qilinadi.

**Foydalanish misoli:**

```env
# Ma'lumotlar bazasidan foydalanish (production uchun tavsiya etiladi)
CBU_SOURCE=database

# Jonli API dan foydalanish (har doim yangi ma'lumot olish uchun foydali)
CBU_SOURCE=api
```

## Foydalanish

### Enum'lardan Foydalanish (Type-Safe)

Paket ISO 4217 valyuta kodlari va manba turlarini enum sifatida qo'llab-quvvatlaydi:

```php
use Cbu\Currency\Facades\CbuCurrency;
use Cbu\Currency\Enums\CurrencyCode;
use Cbu\Currency\Enums\SourceType;

// Enum bilan kursni olish
$rate = CbuCurrency::getRate(CurrencyCode::USD);

// Enum'lar bilan konvertatsiya
$result = CbuCurrency::convert(CurrencyCode::USD, CurrencyCode::EUR, 100);

// Ma'lumot manbaini dinamik ravishda o'zgartirish (method chaining)
$rate = CbuCurrency::source(SourceType::API)->getRate(CurrencyCode::USD);
$result = CbuCurrency::source('database')->convert(CurrencyCode::USD, CurrencyCode::EUR, 100);

// Enum va string parametrlarni aralashtirish
$result = CbuCurrency::source(SourceType::API)->convert('USD', CurrencyCode::EUR, 100);
```

### Artisan Komandalar

#### Valyutalarni Sinxronizatsiya Qilish

CBU dan valyutalar ro'yxatini olish va yangilash:

```bash
php artisan cbu:sync-currencies
```

#### Valyuta Kurslarini Olish

Muayyan sana uchun valyuta kurslarini olish va saqlash:

```bash
# Bugungi kurslarni olish
php artisan cbu:fetch-rates

# Muayyan sana uchun kurslarni olish
php artisan cbu:fetch-rates 2025-01-25
```

### Service dan Foydalanish

#### Valyuta Kursini Olish

```php
use Cbu\Currency\Facades\CbuCurrency;

// Bugungi USD kursini olish
$rate = CbuCurrency::getRate('USD');

// Muayyan sana uchun kursni olish
$rate = CbuCurrency::getRate('USD', '2025-01-25');

// Kurs ma'lumotlariga murojaat
echo $rate->currencyCode;  // "USD"
echo $rate->currencyName;  // "US Dollar"
echo $rate->rate;          // 12750.50
echo $rate->diff;          // 15.25
echo $rate->nominal;       // 1
echo $rate->date;          // "2025-01-25"
```

#### Valyuta Konvertatsiyasi

##### Istalgan valyutalar o'rtasida konvertatsiya

```php
// 100 USD ni EUR ga konvertatsiya qilish
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

##### UZS ga konvertatsiya

```php
// 100 USD ni UZS ga konvertatsiya qilish
$result = CbuCurrency::toUzs('USD', 100);

echo $result->result;        // 1275000.00
echo $result->fromRate;      // 12750.00
echo $result->amountInUzs;   // 1275000.00
```

##### UZS dan konvertatsiya

```php
// 1000000 UZS ni USD ga konvertatsiya qilish
$result = CbuCurrency::fromUzs('USD', 1000000);

echo $result->result;        // 78.43
echo $result->toRate;        // 12750.00
echo $result->amountInUzs;   // 1000000
```

#### Barcha Kurslarni Olish

```php
// Bugungi barcha kurslarni olish
$rates = CbuCurrency::getAllRates();

// Muayyan sana uchun barcha kurslarni olish
$rates = CbuCurrency::getAllRates('2025-01-25');

foreach ($rates as $rate) {
    echo "{$rate->currencyCode}: {$rate->rate}\n";
}
```

### Modellardan To'g'ridan-To'g'ri Foydalanish

```php
use Cbu\Currency\Models\Currency;
use Cbu\Currency\Models\CurrencyRate;

// Barcha valyutalarni olish
$currencies = Currency::all();

// Muayyan valyutani olish
$usd = Currency::where('ccy', 'USD')->first();

// Valyutani kurslar bilan olish
$currency = Currency::with('rates')->where('ccy', 'USD')->first();

// Muayyan sana uchun kurslarni olish
$rates = CurrencyRate::where('date', '2025-01-25')
    ->with('currency')
    ->get();

// Muayyan valyuta va sana uchun kursni olish
$rate = CurrencyRate::whereHas('currency', function($query) {
        $query->where('ccy', 'USD');
    })
    ->where('date', '2025-01-25')
    ->first();
```

## Ma'lumotlar Bazasi Strukturasi

### Currencies Jadvali

| Ustun | Turi | Tavsif |
|-------|------|--------|
| id | bigint | Asosiy kalit |
| ccy | string | Valyuta kodi (unique) |
| name_uz | string | O'zbek tilida nomi |
| name_oz | string | O'zbek tilida nomi (Kirill) |
| name_ru | string | Rus tilida nomi |
| name_en | string | Ingliz tilida nomi |
| code | string | Raqamli kod |
| cbu_id | string | CBU identifikatori |
| created_at | timestamp | Yaratilgan vaqt |
| updated_at | timestamp | Yangilangan vaqt |

### Currency Rates Jadvali

| Ustun | Turi | Tavsif |
|-------|------|--------|
| id | bigint | Asosiy kalit |
| currency_id | bigint | Currencies jadvaliga foreign key |
| date | date | Kurs sanasi (indekslangan) |
| currency_date | date | CBU ning asl sanasi |
| rate | decimal(15,4) | Kurs qiymati |
| diff | decimal(15,4) | Oldingi kundan farq |
| nominal | integer | Nominal qiymat |
| created_at | timestamp | Yaratilgan vaqt |
| updated_at | timestamp | Yangilangan vaqt |

**Unique cheklov**: `['currency_id', 'date']`

## DTOlar

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
$result->amount;        // float - Asl miqdor
$result->fromCurrency;  // string - Manba valyuta
$result->toCurrency;    // string - Maqsad valyuta
$result->result;        // float - Konvertatsiya qilingan miqdor
$result->fromRate;      // ?float - Manba valyutaning UZS ga kursi
$result->toRate;        // ?float - Maqsad valyutaning UZS ga kursi
$result->amountInUzs;   // float - So'mdagi qiymat
$result->date;          // string - Sana (Y-m-d)
```

## Exceptionlar

Paket API bilan bog'liq xatolar uchun `CbuApiException` ni throw qiladi:

```php
use Cbu\Currency\Exceptions\CbuApiException;

try {
    $result = CbuCurrency::convert('USD', 'EUR', 100);
} catch (CbuApiException $e) {
    echo $e->getMessage();
}
```

Exception turlari:
- `requestFailed()` - HTTP so'rov muvaffaqiyatsiz
- `noDataReceived()` - API dan bo'sh javob
- `connectionError()` - Ulanish xatosi
- `dateFormatInvalid()` - Noto'g'ri sana formati

## Test

```bash
composer test
```

## Hissa Qo'shish

Hissa qo'shish xush kelibsiz! Iltimos, Pull Request yuborishingiz mumkin.

## Litsenziya

MIT litsenziyasi (MIT). Batafsil ma'lumot uchun [License File](LICENSE) ga qarang.

## Mualliflar

- [Jummayev Nurbek](https://github.com/Jummayev)
- [Barcha Hissa Qo'shuvchilar](../../contributors)

## Qo'llab-quvvatlash

Qo'llab-quvvatlash uchun jummayevnurbek279@gmail.com ga email yuboring yoki GitHub da issue yarating.

## Misollar

### To'liq Misol

```php
use Cbu\Currency\Facades\CbuCurrency;

// 1. Valyuta kursini olish
$usdRate = CbuCurrency::getRate('USD');
echo "1 USD = {$usdRate->rate} UZS\n";

// 2. USD dan EUR ga konvertatsiya
$result = CbuCurrency::convert('USD', 'EUR', 100);
echo "100 USD = {$result->result} EUR\n";
echo "So'mda: {$result->amountInUzs} UZS\n";

// 3. USD ni so'mga konvertatsiya
$uzsAmount = CbuCurrency::toUzs('USD', 100);
echo "100 USD = {$uzsAmount->result} UZS\n";

// 4. So'mni USD ga konvertatsiya
$usdAmount = CbuCurrency::fromUzs('USD', 1000000);
echo "1000000 UZS = {$usdAmount->result} USD\n";

// 5. Barcha kurslarni olish
$allRates = CbuCurrency::getAllRates();
foreach ($allRates as $rate) {
    echo "{$rate->currencyCode}: {$rate->rate}\n";
}
```

### Tarixiy Kurslar Bilan Ishlash

```php
use Cbu\Currency\Models\CurrencyRate;

// Oxirgi 7 kunlik USD kurslari
$rates = CurrencyRate::whereHas('currency', function($query) {
        $query->where('ccy', 'USD');
    })
    ->whereBetween('date', [
        now()->subDays(7)->format('Y-m-d'),
        now()->format('Y-m-d')
    ])
    ->orderBy('date', 'desc')
    ->get();

foreach ($rates as $rate) {
    echo "{$rate->date}: {$rate->rate}\n";
}
```

### Scheduler Bilan Avtomatik Yangilanish

`app/Console/Kernel.php` faylida:

```php
protected function schedule(Schedule $schedule)
{
    // Har kuni soat 10:00 da kurslarni yangilash
    $schedule->command('cbu:fetch-rates')
        ->dailyAt('10:00');

    // Har hafta dushanba kuni valyutalarni sinxronizatsiya qilish
    $schedule->command('cbu:sync-currencies')
        ->weeklyOn(1, '09:00');
}
```

## Tez-tez So'raladigan Savollar

### 1. Aniqlikni qanday sozlash mumkin?

`.env` faylida:
```env
CBU_SCALE=4  # 4 xona aniqlik
```

### 2. Kurslar qancha vaqt cache da saqlanadi?

Default 60 daqiqa, sozlash uchun:
```env
CBU_CACHE_DURATION=120  # 120 daqiqa
```

### 3. Offline rejimda ishlash mumkinmi?

Ha, bir marta kurslarni yuklab olganingizdan so'ng, ular ma'lumotlar bazasida saqlanadi va offline rejimda ishlatishingiz mumkin.

### 4. Qanday valyutalar qo'llab-quvvatlanadi?

CBU API da mavjud barcha valyutalar qo'llab-quvvatlanadi (USD, EUR, RUB, GBP va boshqalar).
