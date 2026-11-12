<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every expense is created as pending and becomes paid once it is settled.
 */
enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    /**
     * Human-readable name shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Paid => 'Pago',
        };
    }
}
