<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Finance\Transaction;
use App\Services\Finance\TransactionActionService;
use Illuminate\Http\Request;

class TransactionActionController extends Controller
{
    public function updateClearingDate(Request $request, string $transaction, TransactionActionService $service)
    {
        $transaction = Transaction::findByUuidOrFail($transaction);

        $this->authorize('manageClearance', $transaction);

        $transaction = $service->updateClearingDate($request, $transaction);

        return response()->success([
            'message' => trans('global.updated', ['attribute' => trans('finance.transaction.transaction')]),
        ]);
    }
}
