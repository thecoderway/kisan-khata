<?php

namespace Tests\\Feature\\Database;

use App\\Models\\Shop;
use App\\Models\\User;
use Illuminate\\Database\\QueryException;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use Tests\\TestCase;

class GroupZeroCompositeForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_composite_unique_keys_and_customer_child_index_exist(): void
    {
        $userIndexes = collect(Schema::getIndexes('users'))->pluck('name');
        $customerIndexes = collect(Schema::getIndexes('customers'))->pluck('name');

        $this->assertContains('users_shop_id_id_unique', $userIndexes);
        $this->assertContains('customers_shop_id_id_unique', $customerIndexes);
        $this->assertContains('customers_shop_creator_idx', $customerIndexes);
    }

    public function test_customer_creator_foreign_key_has_the_exact_composite_definition(): void
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

        $this->assertCount(2, $definition);
        $this->assertSame('shop_id', $definition[0]->COLUMN_NAME);
        $this->assertSame('shop_id', $definition[0]->REFERENCED_COLUMN_NAME);
        $this->assertSame('created_by', $definition[1]->COLUMN_NAME);
        $this->assertSame('id', $definition[1]->REFERENCED_COLUMN_NAME);
        $this->assertSame('users', $definition[0]->REFERENCED_TABLE_NAME);
        $this->assertContains($definition[0]->DELETE_RULE, ['RESTRICT', 'NO ACTION']);
        $this->assertContains($definition[0]->UPDATE_RULE, ['RESTRICT', 'NO ACTION']);

        $this->assertDatabaseMissing('information_schema.TABLE_CONSTRAINTS', [
            'CONSTRAINT_SCHEMA' => DB::getDatabaseName(),
            'TABLE_NAME' => 'customers',
            'CONSTRAINT_NAME' => 'customers_created_by_foreign',
            'CONSTRAINT_TYPE' => 'FOREIGN KEY',
        ]);
    }

    public function test_customer_can_reference_a_creator_from_the_same_shop(): void
    {
        $shop = Shop::factory()->create();
        $creator = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
        ]);

        $customerId = $this->insertCustomer($shop->id, $creator->id);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'shop_id' => $shop->id,
            'created_by' => $creator->id,
        ]);
    }

    public function test_customer_cannot_reference_a_creator_from_another_shop(): void
    {
        $customerShop = Shop::factory()->create();
        $otherShop = Shop::factory()->create();
        $otherShopUser = User::factory()->create([
            'shop_id' => $otherShop->id,
            'role' => User::ROLE_OWNER,
        ]);

        $this->expectException(QueryException::class);

        $this->insertCustomer($customerShop->id, $otherShopUser->id);
    }

    public function test_customer_cannot_reference_a_platform_user_without_a_shop(): void
    {
        $shop = Shop::factory()->create();
        $platformUser = User::factory()->create([
            'shop_id' => null,
            'role' => User::ROLE_PLATFORM_OWNER,
        ]);

        $this->expectException(QueryException::class);

        $this->insertCustomer($shop->id, $platformUser->id);
    }

    public function test_deactivating_and_reactivating_creator_and_soft_deleting_customer_preserve_relationship(): void
    {
        $shop = Shop::factory()->create();
        $creator = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);
        $customerId = $this->insertCustomer($shop->id, $creator->id);

        DB::table('users')->where('id', $creator->id)->update(['is_active' => false]);
        DB::table('customers')->where('id', $customerId)->update(['deleted_at' => now()]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'created_by' => $creator->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $creator->id,
            'shop_id' => $shop->id,
            'is_active' => false,
        ]);

        DB::table('users')->where('id', $creator->id)->update(['is_active' => true]);
        DB::table('customers')->where('id', $customerId)->update(['deleted_at' => null]);

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'deleted_at' => null,
            'created_by' => $creator->id,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $creator->id,
            'shop_id' => $shop->id,
            'is_active' => true,
        ]);
    }

    public function test_customer_creator_cannot_be_hard_deleted(): void
    {
        $shop = Shop::factory()->create();
        $creator = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
        ]);
        $this->insertCustomer($shop->id, $creator->id);

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $creator->id)->delete();
    }

    private function insertCustomer(int $shopId, int $creatorId): int
    {
        return (int) DB::table('customers')->insertGetId([
            'shop_id' => $shopId,
            'name_original' => 'Test Customer',
            'name_original_language' => 'en',
            'created_by' => $creatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
