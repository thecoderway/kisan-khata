<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertMySql();
        $this->assertExpectedOriginalForeignKey();
        $this->assertCompositeForeignKeyDoesNotExist();

        $hasInvalidCreator = DB::table('customers as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.created_by')
            ->whereNull('u.id')
            ->orWhereNull('u.shop_id')
            ->orWhereColumn('c.shop_id', '<>', 'u.shop_id')
            ->exists();

        if ($hasInvalidCreator) {
            throw new RuntimeException(
                'Cannot enforce the same-Shop customer creator constraint while existing customers '
                .'have a missing creator, a platform-level creator, or a creator from another shop. '
                .'Resolve the data through an explicitly approved remediation plan before retrying.'
            );
        }

        $this->ensureCustomerCreatorIndex();

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign('customers_created_by_foreign');
        });

        try {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign(
                    ['shop_id', 'created_by'],
                    'customers_shop_creator_foreign'
                )
                    ->references(['shop_id', 'id'])
                    ->on('users')
                    ->restrictOnDelete();
            });
        } catch (Throwable $exception) {
            try {
                Schema::table('customers', function (Blueprint $table): void {
                    $table->foreign('created_by', 'customers_created_by_foreign')
                        ->references('id')
                        ->on('users')
                        ->restrictOnDelete();
                });
            } catch (Throwable $recoveryException) {
                throw new RuntimeException(
                    'The composite customer creator foreign key could not be created, and automatic '
                    .'restoration of customers_created_by_foreign also failed. Stop deployment and '
                    .'restore the original foreign key manually before retrying. Recovery error: '
                    .$recoveryException->getMessage(),
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    public function down(): void
    {
        $this->assertMySql();

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign('customers_shop_creator_foreign');
        });

        try {
            Schema::table('customers', function (Blueprint $table): void {
                $table->foreign('created_by', 'customers_created_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
            });
        } catch (Throwable $exception) {
            try {
                Schema::table('customers', function (Blueprint $table): void {
                    $table->foreign(
                        ['shop_id', 'created_by'],
                        'customers_shop_creator_foreign'
                    )
                        ->references(['shop_id', 'id'])
                        ->on('users')
                        ->restrictOnDelete();
                });
            } catch (Throwable $recoveryException) {
                throw new RuntimeException(
                    'Rollback could not restore customers_created_by_foreign, and automatic '
                    .'restoration of customers_shop_creator_foreign also failed. Stop deployment '
                    .'and restore a valid creator foreign key manually. Recovery error: '
                    .$recoveryException->getMessage(),
                    previous: $exception
                );
            }

            throw $exception;
        }

        if ($this->hasIndex('customers', 'customers_shop_creator_idx')) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropIndex('customers_shop_creator_idx');
            });
        }
    }

    private function assertMySql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'The same-Shop customer creator migration requires MySQL metadata and MySQL 8.4.'
            );
        }
    }

    private function assertExpectedOriginalForeignKey(): void
    {
        $definition = DB::table('information_schema.KEY_COLUMN_USAGE as kcu')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as rc', function ($join): void {
                $join->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.CONSTRAINT_SCHEMA')
                    ->on('rc.TABLE_NAME', '=', 'kcu.TABLE_NAME')
                    ->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME');
            })
            ->where('kcu.CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('kcu.TABLE_NAME', 'customers')
            ->where('kcu.CONSTRAINT_NAME', 'customers_created_by_foreign')
            ->get([
                'kcu.COLUMN_NAME',
                'kcu.REFERENCED_TABLE_NAME',
                'kcu.REFERENCED_COLUMN_NAME',
                'rc.DELETE_RULE',
                'rc.UPDATE_RULE',
            ]);

        if (
            $definition->count() !== 1
            || $definition[0]->COLUMN_NAME !== 'created_by'
            || $definition[0]->REFERENCED_TABLE_NAME !== 'users'
            || $definition[0]->REFERENCED_COLUMN_NAME !== 'id'
            || ! in_array($definition[0]->DELETE_RULE, ['RESTRICT', 'NO ACTION'], true)
            || ! in_array($definition[0]->UPDATE_RULE, ['RESTRICT', 'NO ACTION'], true)
        ) {
            throw new RuntimeException(
                'The existing customers_created_by_foreign definition does not match the approved '
                .'single-column users(id) RESTRICT/NO ACTION constraint. No schema changes were made.'
            );
        }
    }

    private function assertCompositeForeignKeyDoesNotExist(): void
    {
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'customers')
            ->where('CONSTRAINT_NAME', 'customers_shop_creator_foreign')
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                'customers_shop_creator_foreign already exists although this migration is pending. '
                .'Inspect the schema and migration history before proceeding.'
            );
        }
    }

    private function ensureCustomerCreatorIndex(): void
    {
        $indexes = collect(Schema::getIndexes('customers'));
        $existing = $indexes->first(
            fn (array $index): bool => $index['name'] === 'customers_shop_creator_idx'
        );

        if ($existing !== null) {
            if ($existing['columns'] !== ['shop_id', 'created_by'] || $existing['unique']) {
                throw new RuntimeException(
                    'customers_shop_creator_idx exists with an unexpected definition.'
                );
            }

            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->index(
                ['shop_id', 'created_by'],
                'customers_shop_creator_idx'
            );
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        return collect(Schema::getIndexes($table))->contains(
            fn (array $index): bool => $index['name'] === $indexName
        );
    }
};
