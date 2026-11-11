<?php

declare(strict_types=1);

namespace App\Enums;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Yearly = 'yearly';

    /**
     * How many months one billing cycle covers.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Semiannual => 6,
            self::Yearly => 12,
        };
    }

    /**
     * Human-readable name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensal',
            self::Quarterly => 'Trimestral',
            self::Semiannual => 'Semestral',
            self::Yearly => 'Anual',
        };
    }

    /**
     * All periods as select options, in the order they are declared.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $period): array => ['value' => $period->value, 'label' => $period->label()],
            self::cases(),
        );
    }
}
