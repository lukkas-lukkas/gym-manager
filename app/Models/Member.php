<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'birth_date'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Whether the member is currently active (not deactivated).
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Deactivate the member, keeping the original date if they were already inactive.
     */
    public function deactivate(): void
    {
        $this->deactivated_at ??= now();
        $this->save();
    }

    /**
     * Activate a previously deactivated member.
     */
    public function activate(): void
    {
        $this->deactivated_at = null;
        $this->save();
    }

    /**
     * Only members that are currently active.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Only members that have been deactivated.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inactive(Builder $query): void
    {
        $query->whereNotNull('deactivated_at');
    }

    /**
     * Filter members by ID (with or without a leading "#"), name or e-mail.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $possibleId = ltrim($term, '#');

            if (ctype_digit($possibleId)) {
                $query->orWhere('id', (int) $possibleId);
            }

            $pattern = '%'.addcslashes($term, '\\%_').'%';

            $query->orWhere('name', 'like', $pattern)
                ->orWhere('email', 'like', $pattern);
        });
    }
}
