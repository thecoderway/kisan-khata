<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureUniqueIndex('users', 'users_shop_id_id_unique', ['shop_id', 'id']);
        $this->ensureUniqueIndex('customers', 'customers_shop_id_id_unique', ['shop_id', 'id']);
    }

    public function down(): void
    {
        $this->dropIndexIfPresent('customers', 'customers_shop_id_id_unique');
        $this->dropIndexIfPresent('users', 'users_shop_id_id_unique');
    }

    /**
     * Make retries safe after MySQL implicitly commits an earlier DDL statement.
     *
     * @param  list<string>  $columns
     */
    private function ensureUniqueIndex(string $tableName, string $indexName, array $columns): void
    {
        $indexes = collect(Schema::getIndexes($tableName));
        $existing = $indexes->first(
            fn (array $index): bool => $index['name'] === $indexName
        );

        if ($existing !== null) {
            if ($existing['columns'] !== $columns || ! $existing['unique']) {
                throw new RuntimeException(
                    $indexName.' exists with an unexpected definition; no index changes were made.'
                );
            }

            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName): void {
            $table->unique($columns, $indexName);
        });
    }

    private function dropIndexIfPresent(string $tableName, string $indexName): void
    {
        $exists = collect(Schema::getIndexes($tableName))->contains(
            fn (array $index): bool => $index['name'] === $indexName
        );

        if (! $exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropUnique($indexName);
        });
    }
};
