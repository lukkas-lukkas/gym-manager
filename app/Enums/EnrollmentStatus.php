<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A member can have many inactive enrollments (their history), but only one active at a time.
 */
enum EnrollmentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
