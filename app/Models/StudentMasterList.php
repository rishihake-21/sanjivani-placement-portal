<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentMasterList extends Model
{
    use HasFactory;

    protected $table = 'student_master_list';

    protected $fillable = [
        'university_id',
        'full_name',
        'institutional_email',
    ];
}