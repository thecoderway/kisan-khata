<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ImportVillages extends Command
{
    protected $signature = 'lgd:import-villages
        {--source=imports/mp_villages.csv : Source CSV path relative to project root}
        {--date=21-04-2026 : Snapshot date to import}
        {--expected=57484 : Expected number of records}
        {--chunk=500 : Number of records per insert batch}
        {--execute : Actually insert records; without this option only validate}';

    protected $description = 'Validate and import an LGD village CSV snapshot';

    private const COLUMNS = [
        'villageCode',
        'villageNameEnglish',
        'villageNameLocal',
        'villageCensus2011Code',
        'subdistrictCode',
        'subdistrictNameEnglish',
        'subdistrictNameLocal',
        'subdistrictCensus2011Code',
        'districtCode',
        'districtNameEnglish',
        'districtNameLocal',
        'districtCensus2011Code',
        'stateCode',
        'stateNameEnglish',
        'stateNameLocal',
        'stateCensus2011Code',
        'data_gov_update_date',
    ];

    private const REQUIRED = [
        'villageCode',
        'villageNameEnglish',
        'subdistrictCode',
        'subdistrictNameEnglish',
        'districtCode',
        'districtNameEnglish',
        'stateCode',
        'stateNameEnglish',
        'data_gov_update_date',
    ];

    public function handle(): int
    {
        try {
            $source = base_path($this->option('source'));
            $date = (string) $this->option('date');
            $expected = (int) $this->option('expected');
            $chunk = (int) $this->option('chunk');
            $execute = (bool) $this->option('execute');

            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date) !== 1) {
                throw new RuntimeException('Date must use DD-MM-YYYY format.');
            }

            if ($expected < 1 || $chunk < 1 || $chunk > 5000) {
                throw new RuntimeException('Invalid expected count or chunk size.');
            }

            if (is_file($source) === false || is_readable($source) === false) {
                throw new RuntimeException("CSV file not found or unreadable: {$source}");
            }

            $this->info('Validating CSV...');
            $sourceHash = hash_file('sha256', $source);
            $count = $this->scanCsv($source, $date);

            if ($count !== $expected) {
                throw new RuntimeException(
                    "Expected {$expected} records for {$date}, found {$count}."
                );
            }

            $this->info("CSV validation successful: {$count} unique records.");
            $this->line("Snapshot date: {$date}");
            $this->line("Chunk size: {$chunk}");

            $existing = DB::table('villages')->count();
            $this->line("Existing villages in database: {$existing}");

            if ($execute === false) {
                $this->warn('DRY RUN ONLY. No database records were inserted.');

                if ($existing > 0) {
                    $this->warn('Actual import is blocked because the table is not empty.');
                } else {
                    $this->info('Ready for actual import after your approval.');
                }

                return self::SUCCESS;
            }

            if ($existing > 0) {
                throw new RuntimeException(
                    'Import blocked: villages table is not empty. No records were changed.'
                );
            }

            if (hash_file('sha256', $source) !== $sourceHash) {
                throw new RuntimeException('CSV changed after validation. Import cancelled.');
            }

            $this->warn('Starting database transaction...');

            DB::transaction(function () use (
                $source,
                $sourceHash,
                $date,
                $expected,
                $chunk
            ): void {
                if (DB::table('villages')->count() !== 0) {
                    throw new RuntimeException('Villages table is no longer empty.');
                }

                if (hash_file('sha256', $source) !== $sourceHash) {
                    throw new RuntimeException('CSV changed before import.');
                }

                $inserted = $this->insertCsv($source, $date, $chunk);

                if ($inserted !== $expected) {
                    throw new RuntimeException(
                        "Inserted {$inserted} records; expected {$expected}."
                    );
                }

                if (DB::table('villages')->count() !== $expected) {
                    throw new RuntimeException('Database record count verification failed.');
                }

                if (hash_file('sha256', $source) !== $sourceHash) {
                    throw new RuntimeException('CSV changed during import.');
                }
            });

            $this->info("Import successful: {$expected} villages inserted.");

            return self::SUCCESS;

        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->error('Operation failed. Any active database transaction was rolled back.');

            return self::FAILURE;
        }
    }

    private function openCsv(string $source)
    {
        $handle = fopen($source, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV file.');
        }

        $header = fgetcsv($handle, 0, ',', '"', '');

        if ($header === false) {
            fclose($handle);
            throw new RuntimeException('CSV header is missing.');
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(
            static fn ($value) => trim((string) $value),
            $header
        );

        if ($header !== self::COLUMNS) {
            fclose($handle);
            throw new RuntimeException(
                'CSV headers do not match the expected 17 LGD columns or their order.'
            );
        }

        return $handle;
    }

    private function scanCsv(string $source, string $date): int
    {
        $handle = $this->openCsv($source);
        $codes = [];
        $count = 0;
        $line = 1;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;

                if (count($row) === 1 && $row[0] === null) {
                    continue;
                }

                if (count($row) !== count(self::COLUMNS)) {
                    throw new RuntimeException("Invalid column count at CSV line {$line}.");
                }

                $row = array_map(
                    static fn ($value) => trim((string) ($value ?? '')),
                    $row
                );

                $record = array_combine(self::COLUMNS, $row);

                if ($record['data_gov_update_date'] !== $date) {
                    continue;
                }

                foreach (self::REQUIRED as $column) {
                    if ($record[$column] === '') {
                        throw new RuntimeException(
                            "Required value '{$column}' is empty at CSV line {$line}."
                        );
                    }
                }

                $code = $record['villageCode'];

                if (isset($codes[$code])) {
                    throw new RuntimeException(
                        "Duplicate villageCode {$code} found in selected snapshot."
                    );
                }

                $codes[$code] = true;
                $count++;
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }

    private function insertCsv(string $source, string $date, int $chunk): int
    {
        $handle = $this->openCsv($source);
        $batch = [];
        $inserted = 0;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($row) === 1 && $row[0] === null) {
                    continue;
                }

                $row = array_map(
                    static fn ($value) => trim((string) ($value ?? '')),
                    $row
                );

                if ($row[16] !== $date) {
                    continue;
                }

                $record = array_combine(self::COLUMNS, $row);

                foreach ($record as $column => $value) {
                    if ($value === '') {
                        $record[$column] = null;
                    }
                }

                $batch[] = $record;

                if (count($batch) >= $chunk) {
                    DB::table('villages')->insert($batch);
                    $inserted += count($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table('villages')->insert($batch);
                $inserted += count($batch);
            }
        } finally {
            fclose($handle);
        }

        return $inserted;
    }
}
