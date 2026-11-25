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

        $originalExists = $this->hasForeignKey('customers_created_by_foreign');
        $compositeExists = $this->hasForeignKey('customers_shop_creator_foreign');

        if ($compositeExists && ! $originalExists) {
            $this->assertCompositeForeignKey();
            $this->ensureCustomerCreatorIndex();

            return;
        }

        if (! $originalExists || $compositeExists) {
            throw new RuntimeException(
                'Customer creator foreign-key state is unexpected. Inspect the schema and migration '
                .'history before proceeding; no foreign-key changes were made.'
            );
        }

        $this->assertExpectedOriginalForeignKey();

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

        // MySQL 8.4/InnoDB executes this as one atomic ALTER TABLE operation.
        // The old FK is not left absent if adding the composite FK fails.
        DB::statement(
            'ALTER TABLE `customers` '
            .'DROP FOREIGN KEY `customers_created_by_foreign`, '
            .'ADD CONSTRAINT `customers_shop_creator_foreign` '
            .'FOREIGN KEY (`shop_id`, `created_by`) '
            .'REFERENCES `users` (`shop_id`, `id`) '
            .'ON DELETE RESTRICT ON UPDATE RESTRICT'
        );
    }

    public function down(): void
    {
        $this->assertMySql();

        $originalExists = $this->hasForeignKey('customers_created_by_foreign');
        $compositeExists = $this->hasForeignKey('customers_shop_creator_foreign');

        if ($originalExists && ! $compositeExists) {
            $this->assertExpectedOriginalForeignKey();
            $this->dropCustomerCreatorIndex();

            return;
        }

        if (! $compositeExists || $originalExists) {
            throw new RuntimeException(
                'Customer creator foreign-key state is unexpected during rollback. Inspect the '
                .'schema and migration history before proceeding.'
            );
        }

        $this->assertCompositeForeignKey();

        // Reverse the relationship in one atomic DDL statement as well.
        DB::statement(
            'ALTER TABLE `customers` '
            .'DROP FOREIGN KEY `customers_shop_creator_foreign`, '
            .'ADD CONSTRAINT `customers_created_by_foreign` '
            .'FOREIGN KEY (`created_by`) '
            .'REFERENCES `users` (`id`) '
            .'ON DELETE RESTRICT ON UPDATE RESTRICT'
        );

        $this->dropCustomerCreatorIndex();
    }

    private function assertMySql(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            throw new RuntimeException(
                'The same-Shop customer creator migration requires MySQL metadata and MySQL 8.4.'
            );
        }
    }

    private function hasForeignKey(string $constraintName): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'customers')
            ->where('CONSTRAINT_NAME', $constraintName)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
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
            ->orderBy('kcu.ORDINAL_POSITION')
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
                .'single-column users(id) RESTRICT/NO ACTION constraint.'
            );
        }
    }

    private function assertCompositeForeignKey(): void
    {
        $definition = DB::table('information_schema.KEY_COLUMN_USAGE as kcu')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as rc', function ($join): void {
                $join->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.CONSTRAINT_SCHEMA')
                    ->on('rc.TABLE_NAME', '=', 'kcu.TABLE_NAME')
                    ->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME');
            })
            ->where('kcu.CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('kcu.TABLE_NAME', 'customers')
            ->where('kcu.CONSTRAINT_NAME', 'customers_shop_creator_foreign')
            ->orderBy('kcu.ORDINAL_POSITION')
            ->get([
                'kcu.COLUMN_NAME',
                'kcu.REFERENCED_TABLE_NAME',
                'kcu.REFERENCED_COLUMN_NAME',
                'rc.DELETE_RULE',
                'rc.UPDATE_RULE',
            ]);

        if (
            $definition->count() !== 2
            || $definition[0]->COLUMN_NAME !== 'shop_id'
            || $definition[0]->REFERENCED_TABLE_NAME !== 'users'
            || $definition[0]->REFERENCED_COLUMN_NAME !== 'shop_id'
            || $definition[1]->COLUMN_NAME !== 'created_by'
            || $definition[1]->REFERENCED_TABLE_NAME !== 'users'
            || $definition[1]->REFERENCED_COLUMN_NAME !== 'id'
            || ! in_array($definition[0]->DELETE_RULE, ['RESTRICT', 'NO ACTION'], true)
            || ! in_array($definition[0]->UPDATE_RULE, ['RESTRICT', 'NO ACTION'], true)
        ) {
            throw new RuntimeException(
                'customers_shop_creator_foreign does not match the approved composite users(shop_id, id) '
                .'RESTRICT/NO ACTION definition.'
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

    private function dropCustomerCreatorIndex(): void
    {
        $exists = collect(Schema::getIndexes('customers'))->contains(
            fn (array $index): bool => $index['name'] === 'customers_shop_creator_idx'
        );

        if (! $exists) {
            return;
        }

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_shop_creator_idx');
        });
    }
};
