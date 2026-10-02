<?php

namespace App\Models;

use App\Enums\AdmissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterListEntry extends Model
{
    protected $table = 'student_master_list';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'admission_type' => AdmissionType::class,
            'claimed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isClaimed(): bool
    {
        return $this->claimed_at !== null;
    }
}
