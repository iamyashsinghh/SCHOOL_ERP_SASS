<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ReceiptRequest;
use App\Http\Resources\Finance\ReceiptResource;
use App\Models\Tenant\Finance\Receipt;
use App\Services\Finance\ReceiptListService;
use App\Services\Finance\ReceiptService;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('test.mode.restriction')->only(['destroy']);
    }

    public function preRequisite(ReceiptService $service)
    {
        return response()->ok($service->preRequisite());
    }

    public function index(Request $request, ReceiptListService $service)
    {
        $this->authorize('viewAny', Receipt::class);

        return $service->paginate($request);
    }

    public function store(ReceiptRequest $request, ReceiptService $service)
    {
        $this->authorize('create', Receipt::class);

        $receipt = $service->create($request);

        return response()->success([
            'message' => trans('global.created', ['attribute' => trans('finance.receipt.receipt')]),
            'receipt' => ReceiptResource::make($receipt),
        ]);
    }

    public function show(string $receipt, ReceiptService $service)
    {
        $receipt = Receipt::findByUuidOrFail($receipt);

        $this->authorize('view', $receipt);

        $receipt->load('category', 'payments.ledger', 'payments.method', 'records.ledger', 'transactionable.contact', 'media');

        return ReceiptResource::make($receipt);
    }

    public function update(ReceiptRequest $request, string $receipt, ReceiptService $service)
    {
        $receipt = Receipt::findByUuidOrFail($receipt);

        $this->authorize('update', $receipt);

        $service->update($receipt, $request);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('finance.receipt.receipt')]),
        ]);
    }

    public function destroy(string $receipt, ReceiptService $service)
    {
        $receipt = Receipt::findByUuidOrFail($receipt);

        $this->authorize('delete', $receipt);

        $service->deletable($receipt);

        $service->delete($receipt);

        return response()->success([
            'message' => trans('global.deleted', ['attribute' => trans('finance.receipt.receipt')]),
        ]);
    }

    public function downloadMedia(string $receipt, string $uuid, ReceiptService $service)
    {
        $receipt = Receipt::findByUuidOrFail($receipt);

        $this->authorize('view', $receipt);

        return $receipt->downloadMedia($uuid);
    }

    public function export(Request $request, string $receipt, ReceiptService $service)
    {
        $receipt = Receipt::query()
            ->withRecord()
            ->findByUuidOrFail($receipt);

        $receipt->load('category', 'payments.ledger', 'payments.method', 'transactionable.contact', 'user');

        $receipt = json_decode(ReceiptResource::make($receipt)->toJson(), true);

        return view()->first([config('config.print.custom_path').'finance.receipt', 'print.finance.receipt'], compact('receipt'));
    }
}
