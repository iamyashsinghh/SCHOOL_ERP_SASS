<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\AccountsListService;
use Illuminate\Http\Request;

class AccountsExportController extends Controller
{
    public function __invoke(Request $request, AccountsListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
