<?php

namespace Tests\Feature\Tenancy;

use App\Contracts\TenantAwareJob;
use App\Models\Shop;
use App\Tenancy\CurrentShop;
use App\Tenancy\Middleware\ResolveTenantForJob;
use App\Tenancy\TenantCacheKey;
use App\Tenancy\TenantPrivatePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class TenantInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_keys_are_namespaced_by_the_current_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $currentShop = app(CurrentShop::class);
        $keys = app(TenantCacheKey::class);

        $currentShop->set($shopA);
        $keyA = $keys->make('dashboard:summary');

        $currentShop->set($shopB);
        $keyB = $keys->make('dashboard:summary');

        $this->assertSame("shop:{$shopA->id}:dashboard:summary", $keyA);
        $this->assertSame("shop:{$shopB->id}:dashboard:summary", $keyB);
        $this->assertNotSame($keyA, $keyB);
    }

    public function test_cache_keys_fail_closed_without_tenant_context(): void
    {
        $this->expectException(LogicException::class);

        app(TenantCacheKey::class)->make('dashboard:summary');
    }

    public function test_private_file_paths_are_isolated_by_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $currentShop = app(CurrentShop::class);
        $paths = app(TenantPrivatePath::class);

        $currentShop->set($shopA);
        $pathA = $paths->make('customers/42/receipt.pdf');

        $currentShop->set($shopB);
        $pathB = $paths->make('customers/42/receipt.pdf');

        $this->assertSame("shops/{$shopA->id}/customers/42/receipt.pdf", $pathA);
        $this->assertSame("shops/{$shopB->id}/customers/42/receipt.pdf", $pathB);
        $this->assertNotSame($pathA, $pathB);
    }

    public function test_private_file_paths_reject_traversal_and_absolute_paths(): void
    {
        $shop = Shop::factory()->create();
        app(CurrentShop::class)->set($shop);
        $paths = app(TenantPrivatePath::class);

        foreach (['../secrets.txt', 'customers/../../secrets.txt', '/etc/passwd', ''] as $path) {
            try {
                $paths->make($path);
                $this->fail("Unsafe path was accepted: {$path}");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_private_file_paths_fail_closed_without_tenant_context(): void
    {
        $this->expectException(LogicException::class);

        app(TenantPrivatePath::class)->make('customers/42/receipt.pdf');
    }

    public function test_tenant_job_context_is_established_and_cleared_after_execution(): void
    {
        $shop = Shop::factory()->create();
        $currentShop = app(CurrentShop::class);
        $middleware = app(ResolveTenantForJob::class);
        $job = new class($shop->id) implements TenantAwareJob
        {
            public function __construct(private readonly int $id) {}

            public function tenantShopId(): int
            {
                return $this->id;
            }
        };

        $result = $middleware->handle($job, function () use ($currentShop, $shop): string {
            $this->assertSame($shop->id, $currentShop->id());

            return 'processed';
        });

        $this->assertSame('processed', $result);
        $this->assertNull($currentShop->id());
    }

    public function test_tenant_job_context_is_cleared_when_job_execution_fails(): void
    {
        $shop = Shop::factory()->create();
        $currentShop = app(CurrentShop::class);
        $middleware = app(ResolveTenantForJob::class);
        $job = new class($shop->id) implements TenantAwareJob
        {
            public function __construct(private readonly int $id) {}

            public function tenantShopId(): int
            {
                return $this->id;
            }
        };

        try {
            $middleware->handle($job, function () use ($currentShop): void {
                $this->assertNotNull($currentShop->id());
                throw new \RuntimeException('Expected test failure.');
            });
            $this->fail('The job exception should propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Expected test failure.', $exception->getMessage());
        }

        $this->assertNull($currentShop->id());
    }

    public function test_tenant_job_rejects_suspended_shops(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $job = new class($shop->id) implements TenantAwareJob
        {
            public function __construct(private readonly int $id) {}

            public function tenantShopId(): int
            {
                return $this->id;
            }
        };

        $this->expectException(LogicException::class);

        app(ResolveTenantForJob::class)->handle($job, fn () => null);
    }
}
