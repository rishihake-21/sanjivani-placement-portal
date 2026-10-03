<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveEligibility extends Model
{
    protected $table = 'drive_eligibility';

    protected $primaryKey = 'drive_id';

    public $incrementing = false;

    protected $guarded = [];
}
