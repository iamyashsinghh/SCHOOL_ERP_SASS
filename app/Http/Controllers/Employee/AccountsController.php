<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\AccountsRequest;
use App\Http\Resources\Employee\AccountsResource;
use App\Models\Tenant\Employee\Employee;
use App\Services\Employee\AccountsListService;
use App\Services\Employee\AccountsService;
use Illuminate\Http\Request;

class AccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:employee:read');
    }

    public function preRequisite(Request $request, AccountsService $service)
    {
        return response()->ok($service->preRequisite($request));
    }

    public function index(Request $request, AccountsListService $service)
    {
        return $service->paginate($request);
    }

    public function store(AccountsRequest $request, AccountsService $service)
    {
        $employee = Employee::find($request->employee_id);

        $this->authorize('selfService', $employee);

        $account = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('employee.account.account')]),
            'account' => AccountsResource::make($account),
        ]);
    }

    public function show(Request $request, string $account, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $employee = $service->findEmployee($account);

        $this->authorize('selfService', $employee);

        $account->employee = $employee;

        $account->load('media');

        return AccountsResource::make($account);
    }

    public function update(AccountsRequest $request, string $account, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $employee = $service->findEmployee($account);

        $this->authorize('manageEmployeeRecord', $employee);

        $service->update($request, $account);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('employee.account.account')]),
        ]);
    }

    public function destroy(Request $request, string $account, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $employee = $service->findEmployee($account);

        $service->deletable($request, $account);

        $account->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('employee.account.account')]),
        ]);
    }

    public function downloadMedia(string $account, string $uuid, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $employee = $service->findEmployee($account);

        return $account->downloadMedia($uuid);
    }
}
