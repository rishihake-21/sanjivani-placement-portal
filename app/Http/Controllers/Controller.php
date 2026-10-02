<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

abstract class Controller
{
    /** The Student row of the logged-in student. Staff accounts have none. */
    protected function currentStudent(Request $request): Student
    {
        $student = $request->user()?->student;
        abort_if($student === null, 403, 'This account has no student profile.');

        return $student;
    }
}
