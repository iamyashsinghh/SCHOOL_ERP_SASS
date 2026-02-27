<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AccountsRequest;
use App\Http\Resources\Student\AccountsResource;
use App\Services\Student\AccountsListService;
use App\Services\Student\AccountsService;
use Illuminate\Http\Request;

class AccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
        $this->middleware('permission:student:read');
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
        $account = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('student.account.account')]),
            'account' => AccountsResource::make($account),
        ]);
    }

    public function show(Request $request, string $account, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $student = $service->findStudent($account);

        $account->student = $student;

        $account->load('media');

        return AccountsResource::make($account);
    }

    public function update(AccountsRequest $request, string $account, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $student = $service->findStudent($account);

        $service->update($request, $account);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('student.account.account')]),
        ]);
    }

    public function destroy(Request $request, string $account, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $student = $service->findStudent($account);

        $service->deletable($request, $account);

        $account->delete();

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('student.account.account')]),
        ]);
    }

    public function downloadMedia(string $account, string $uuid, AccountsService $service)
    {
        $account = $service->findByUuidOrFail($account);

        $student = $service->findStudent($account);

        return $account->downloadMedia($uuid);
    }
}
