<?php

namespace Cbu\Currency\Console\Commands;

use Cbu\Currency\Exceptions\CbuApiException;
use Cbu\Currency\Services\CbuApiService;
use Illuminate\Console\Command;

class FetchCurrencyRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cbu:fetch-rates {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch currency rates from CBU API and store them in the database';

    /**
     * Execute the console command.
     */
    public function handle(CbuApiService $service): int
    {
        $date = $this->argument('date');

        if ($date && !$this->isValidDate($date)) {
            $this->error('Invalid date format. Please use Y-m-d format (e.g., 2025-01-25)');
            return self::FAILURE;
        }

        $this->info('Fetching currency rates from CBU API...');

        try {
            $service->fetchAndStore($date);

            $this->newLine();
            $this->info('✓ Currency rates fetched and stored successfully');
            $this->newLine();

            return self::SUCCESS;
        } catch (CbuApiException $e) {
            $this->error('✗ ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Validate date format
     *
     * @param string $date
     * @return bool
     */
    protected function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
