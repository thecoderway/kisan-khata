<?php

namespace Tests\\Unit\\Financial;

use App\\Modules\\Financial\\Application\\InterestCalculationKernel;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\\Framework\\TestCase;

class InterestCalculationKernelTest extends TestCase
{
    public function test_it_applies_actual_days_over_thirty_after_the_full_grace_period(): void
    {
        $result = (new InterestCalculationKernel)->calculate(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-03'),
            '10000.00',
            '2.00000000',
            30,
        );

        $this->assertSame('2026-02-01', $result['interval_start_date']);
        $this->assertSame('2026-02-03', $result['interval_end_date_exclusive']);
        $this->assertSame(2, $result['actual_days']);
        $this->assertSame(30, $result['grace_days_applied']);
        $this->assertSame('13.33333333', $result['unrounded_interest']);
        $this->assertSame('13.33', $result['posted_interest']);
    }

    public function test_it_does_not_apply_grace_period_twice_to_a_later_interval(): void
    {
        $result = (new InterestCalculationKernel)->calculate(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-01'),
            new DateTimeImmutable('2026-02-04'),
            '10000.00',
            '2.00000000',
            30,
        );

        $this->assertSame('2026-02-01', $result['interval_start_date']);
        $this->assertSame(3, $result['actual_days']);
        $this->assertSame(0, $result['grace_days_applied']);
        $this->assertSame('20.00000000', $result['unrounded_interest']);
        $this->assertSame('20.00', $result['posted_interest']);
    }

    public function test_it_returns_zero_for_zero_rate_or_zero_elapsed_days(): void
    {
        $kernel = new InterestCalculationKernel;
        $common = [
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-01'),
            '10000.00',
        ];

        $zeroRate = $kernel->calculate(...[...$common, '0', 30]);
        $withinGrace = $kernel->calculate(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-01-31'),
            '10000.00',
            '2',
            30,
        );

        $this->assertSame(0, $zeroRate['actual_days']);
        $this->assertSame('0.00', $zeroRate['posted_interest']);
        $this->assertSame(0, $withinGrace['actual_days']);
        $this->assertSame('0.00', $withinGrace['posted_interest']);
    }

    public function test_it_uses_half_up_rounding_for_posted_interest(): void
    {
        $result = (new InterestCalculationKernel)->calculate(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-01'),
            new DateTimeImmutable('2026-02-02'),
            '1.00',
            '15.00000000',
            30,
        );

        $this->assertSame('0.00500000', $result['unrounded_interest']);
        $this->assertSame('0.01', $result['posted_interest']);
    }

    public function test_it_rejects_invalid_ranges_and_decimal_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InterestCalculationKernel)->calculate(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-02'),
            new DateTimeImmutable('2026-02-01'),
            '100.00',
            '2.00',
            30,
        );
    }

    public function test_it_rejects_float_like_and_exponent_decimal_inputs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InterestCalculationKernel)->calculate(
            new DateTimeImmutable('2026-01-01'),
            new DateTimeImmutable('2026-02-01'),
            new DateTimeImmutable('2026-02-02'),
            '1e4',
            '2.00',
            30,
        );
    }
}
