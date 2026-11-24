<?php

namespace Tests\Feature\UserManagement;

use App\Models\Shop;
use App\Models\User;
use App\Models\UserAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AppendOnlyAndRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_cannot_be_deleted_through_instance_or_bulk_eloquent_paths(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
            'is_active' => false,
        ]);

        try {
            $user->delete();
            $this->fail('Individual user deletion must be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }

        try {
            User::query()->whereKey($user->id)->delete();
            $this->fail('Bulk user deletion must be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }

        try {
            User::query()->truncate();
            $this->fail('The users table must not be truncated through Eloquent.');
        } catch (LogicException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }

    public function test_audit_records_cannot_be_updated_or_deleted_through_model_events(): void
    {
        $audit = $this->createAudit();

        try {
            $audit->forceFill(['reason' => 'tampered'])->save();
            $this->fail('Audit model updates must be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('user_audit_logs', [
                'id' => $audit->id,
                'reason' => null,
            ]);
        }

        try {
            $audit->delete();
            $this->fail('Audit model deletes must be blocked.');
        } catch (LogicException) {
            $this->assertDatabaseHas('user_audit_logs', ['id' => $audit->id]);
        }
    }

    public function test_audit_records_cannot_be_mutated_through_bulk_eloquent_paths(): void
    {
        $audit = $this->createAudit();

        foreach ([
            fn () => UserAuditLog::query()->whereKey($audit->id)->update(['reason' => 'tampered']),
            fn () => UserAuditLog::query()->whereKey($audit->id)->delete(),
            fn () => UserAuditLog::query()->whereKey($audit->id)->increment('id'),
            fn () => UserAuditLog::query()->upsert(
                [['id' => $audit->id, 'reason' => 'tampered']],
                ['id'],
                ['reason'],
            ),
            fn () => UserAuditLog::query()->updateOrInsert(
                ['id' => $audit->id],
                ['reason' => 'tampered'],
            ),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Bulk Eloquent audit mutation must be blocked.');
            } catch (LogicException) {
                $this->assertDatabaseHas('user_audit_logs', [
                    'id' => $audit->id,
                    'reason' => null,
                ]);
            }
        }
    }

    private function createAudit(): UserAuditLog
    {
        $shop = Shop::factory()->create();
        $actor = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
        ]);
        $target = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_STAFF,
        ]);

        $id = DB::table('user_audit_logs')->insertGetId([
            'shop_id' => $shop->id,
            'actor_user_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => 'user.deactivated',
            'old_values' => json_encode(['is_active' => true], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['is_active' => false], JSON_THROW_ON_ERROR),
            'reason' => null,
            'created_at' => now(),
        ]);

        return UserAuditLog::query()->findOrFail($id);
    }
}
