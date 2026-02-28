<?php

namespace App\Services\Student;

use App\Models\Tenant\Student\Student;
use Illuminate\Http\Request;

class TransferMediaService
{
    public function create(Request $request, Student $student): void
    {
        $admission = $student->admission;

        $admission->updateMedia($request);
    }
}
