<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Employee\AccountsListService;
use Illuminate\Http\Request;

class AccountsExportController extends Controller
{
    public function __invoke(Request $request, AccountsListService $service)
    {
        $list = $service->list($request);

        return $service->export($list);
    }
}
