<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A company's recruiter login. Everything they may see is derived from `company_id`. */
class Recruiter extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'active_from' => 'date',
            'active_until' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Both the account and the company must be switched on. */
    public function canAccess(): bool
    {
        return $this->is_active && $this->user?->is_active && $this->company?->is_active;
    }
}
