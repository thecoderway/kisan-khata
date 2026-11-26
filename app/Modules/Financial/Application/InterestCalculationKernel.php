<?php

namespace App\\Modules\\Financial\\Application;

use Brick\\Math\\BigDecimal;
use Brick\\Math\\RoundingMode;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Deterministic, side-effect-free interest arithmetic.
 *
 * Policy resolution, event orchestration and persistence deliberately remain
 * outside this kernel so every caller uses the same decimal/date conventions.
 */
class InterestCalculationKernel
{
    /**
     * @return array{
     *   interval_start_date: string,
     *   interval_end_date_exclusive: string,
     *   actual_days: int,
     *   grace_days_applied: int,
     *   eligible_principal_basis: string,
     *   monthly_rate_percent: string,
     *   unrounded_interest: string,
     *   posted_interest: string
     * }
     */
    public function calculate(
        DateTimeInterface $saleDate,
        DateTimeInterface $intervalStartDate,
        DateTimeInterface $intervalEndDateExclusive,
        string $eligiblePrincipal,
        string $monthlyRatePercent,
        int $graceDays,
    ): array {
        $sale = $this->dateOnly($saleDate);
        $requestedStart = $this->dateOnly($intervalStartDate);
        $endExclusive = $this->dateOnly($intervalEndDateExclusive);

        if ($endExclusive < $requestedStart) {
            throw new InvalidArgumentException('The interest interval end must not precede its start.');
        }

        if ($graceDays < 0 || $graceDays > 65535) {
            throw new InvalidArgumentException('Grace days must be between 0 and 65,535.');
        }

        $principal = $this->decimal($eligiblePrincipal, '/^\\d{1,12}(?:\\.\\d{1,8})?$/D', 'Eligible principal');
        $rate = $this->decimal($monthlyRatePercent, '/^\\d{1,4}(?:\\.\\d{1,8})?$/D', 'Monthly interest rate');

        $graceEndExclusive = $sale->modify('+'.($graceDays + 1).' days');
        $interestStart = $requestedStart > $graceEndExclusive ? $requestedStart : $graceEndExclusive;
        $interestStart = $interestStart > $endExclusive ? $endExclusive : $interestStart;

        $actualDays = (int) $interestStart->diff($endExclusive)->format('%a');
        $graceWindowStart = $requestedStart > $sale->modify('+1 day')
            ? $requestedStart
            : $sale->modify('+1 day');
        $graceWindowEnd = $endExclusive < $graceEndExclusive ? $endExclusive : $graceEndExclusive;
        $graceDaysApplied = $graceWindowEnd > $graceWindowStart
            ? min($graceDays, (int) $graceWindowStart->diff($graceWindowEnd)->format('%a'))
            : 0;

        $unrounded = $actualDays === 0 || $principal->isZero() || $rate->isZero()
            ? BigDecimal::zero()
            : $principal
                ->multipliedBy($rate)
                ->dividedBy('100', 8, RoundingMode::HALF_UP)
                ->multipliedBy((string) $actualDays)
                ->dividedBy('30', 8, RoundingMode::HALF_UP);

        return [
            'interval_start_date' => $interestStart->format('Y-m-d'),
            'interval_end_date_exclusive' => $endExclusive->format('Y-m-d'),
            'actual_days' => $actualDays,
            'grace_days_applied' => $graceDaysApplied,
            'eligible_principal_basis' => (string) $principal->toScale(8, RoundingMode::HALF_UP),
            'monthly_rate_percent' => (string) $rate->toScale(8, RoundingMode::HALF_UP),
            'unrounded_interest' => (string) $unrounded->toScale(8, RoundingMode::HALF_UP),
            'posted_interest' => (string) $unrounded->toScale(2, RoundingMode::HALF_UP),
        ];
    }

    private function dateOnly(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
    }

    private function decimal(string $value, string $pattern, string $label): BigDecimal
    {
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException($label.' must be a non-negative decimal string with supported precision.');
        }

        return BigDecimal::of($value);
    }
}
