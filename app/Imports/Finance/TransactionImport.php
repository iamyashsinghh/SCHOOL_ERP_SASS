<?php

namespace App\Imports\Finance;

use App\Concerns\ItemImport;
use App\Enums\Finance\TransactionType;
use App\Enums\OptionType;
use App\Helpers\CalHelper;
use App\Models\Finance\Ledger;
use App\Models\Finance\PaymentMethod;
use App\Models\Finance\Transaction;
use App\Models\Finance\TransactionPayment;
use App\Models\Finance\TransactionRecord;
use App\Models\Option;
use App\Models\Team;
use App\Support\FormatCodeNumber;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class TransactionImport implements ToCollection, WithHeadingRow
{
    use FormatCodeNumber, ItemImport;

    protected $limit = 1000;

    private function codeNumber(string $type, array $params = []): array
    {
        $numberPrefix = config('config.finance.'.$type.'_number_prefix');
        $numberSuffix = config('config.finance.'.$type.'_number_suffix');
        $digit = config('config.finance.'.$type.'_number_digit');

        $numberFormat = $numberPrefix.'%NUMBER%'.$numberSuffix;

        $numberFormat = $this->preFormatForDate($numberFormat, Arr::get($params, 'date'));

        $shortcode = [
            'payment_method' => Arr::get($params, 'payment_method'),
            'ledger' => Arr::get($params, 'ledger'),
        ];

        $numberFormat = $this->preFormatForTransaction($numberFormat, $shortcode);

        $codeNumber = (int) Transaction::query()
            ->join('periods', 'periods.id', '=', 'transactions.period_id')
            ->where('periods.team_id', auth()->user()->current_team_id)
            ->whereNumberFormat($numberFormat)
            ->max('number') + 1;

        return $this->getCodeNumber(number: $codeNumber, digit: $digit, format: $numberFormat);
    }

    public function collection(Collection $rows)
    {
        $this->validateHeadings($rows);

        if (count($rows) > $this->limit) {
            throw ValidationException::withMessages(['message' => trans('general.errors.max_import_limit_crossed', ['attribute' => $this->limit])]);
        }

        $logFile = $this->getLogFile('transaction');

        [$errors, $rows] = $this->validate($rows);

        $this->checkForErrors('transaction', $errors);

        if (! request()->boolean('validate') && ! \Storage::disk('local')->exists($logFile)) {
            $this->import($rows);
        }
    }

    private function import(Collection $rows)
    {
        $importBatchUuid = (string) Str::uuid();

        activity()->disableLogging();

        \DB::beginTransaction();

        foreach ($rows as $row) {

            $date = Arr::get($row, 'date');

            if (empty($date)) {
                $date = null;
            } elseif (is_int(Arr::get($row, 'date'))) {
                $date = Date::excelToDateTimeObject(Arr::get($row, 'date'))->format('Y-m-d');
            } else {
                $date = Carbon::parse(Arr::get($row, 'date'))->toDateString();
            }

            $instrumentDate = Arr::get($row, 'instrument_date');

            if (empty($instrumentDate)) {
                $instrumentDate = null;
            } elseif (is_int(Arr::get($row, 'instrument_date'))) {
                $instrumentDate = Date::excelToDateTimeObject(Arr::get($row, 'instrument_date'))->format('Y-m-d');
            } else {
                $instrumentDate = Carbon::parse(Arr::get($row, 'instrument_date'))->toDateString();
            }

            $clearingDate = Arr::get($row, 'clearing_date');

            if (empty($clearingDate)) {
                $clearingDate = null;
            } elseif (is_int(Arr::get($row, 'clearing_date'))) {
                $clearingDate = Date::excelToDateTimeObject(Arr::get($row, 'clearing_date'))->format('Y-m-d');
            } else {
                $clearingDate = Carbon::parse(Arr::get($row, 'clearing_date'))->toDateString();
            }

            $type = strtolower(Arr::get($row, 'type'));

            $codeNumberDetail = $this->codeNumber($type, [
                'payment_method' => Arr::get($row, 'payment_method_code'),
                'ledger' => Arr::get($row, 'ledger_code'),
                'date' => $date,
            ]);

            $transaction = Transaction::forceCreate([
                'number_format' => Arr::get($codeNumberDetail, 'number_format'),
                'number' => Arr::get($codeNumberDetail, 'number'),
                'code_number' => Arr::get($codeNumberDetail, 'code_number'),
                'period_id' => auth()->user()->current_period_id,
                'user_id' => auth()->id(),
                'type' => $type,
                'date' => $date,
                'amount' => Arr::get($row, 'amount'),
                'category_id' => Arr::get($row, 'category_id'),
                'description' => Arr::get($row, 'description'),
                'remarks' => Arr::get($row, 'remarks'),
                'meta' => [
                    'import_batch' => $importBatchUuid,
                    'is_imported' => true,
                ],
            ]);

            TransactionPayment::forceCreate([
                'transaction_id' => $transaction->id,
                'payment_method_id' => Arr::get($row, 'payment_method_id'),
                'ledger_id' => Arr::get($row, 'primary_ledger_id'),
                'amount' => Arr::get($row, 'amount'),
                'details' => [
                    'instrument_number' => Arr::get($row, 'instrument_number'),
                    'instrument_date' => $instrumentDate,
                    'clearing_date' => $clearingDate,
                    'bank_detail' => Arr::get($row, 'bank_detail'),
                    'branch_detail' => Arr::get($row, 'branch_detail'),
                    'reference_number' => Arr::get($row, 'reference_number'),
                    'card_provider' => Arr::get($row, 'card_provider'),
                ],
            ]);

            $secondaryLedgerId = Arr::get($row, 'secondary_ledger_id');

            TransactionRecord::forceCreate([
                'transaction_id' => $transaction->id,
                'ledger_id' => $secondaryLedgerId,
                'amount' => Arr::get($row, 'amount'),
                'remarks' => Arr::get($row, 'remarks'),
            ]);
        }

        $team = Team::query()
            ->whereId(auth()->user()->current_team_id)
            ->first();

        $meta = $team->meta ?? [];
        $imports['transaction'] = Arr::get($meta, 'imports.transaction', []);
        $imports['transaction'][] = [
            'uuid' => $importBatchUuid,
            'total' => count($rows),
            'created_at' => now()->toDateTimeString(),
        ];

        $meta['imports'] = $imports;
        $team->meta = $meta;
        $team->save();

        \DB::commit();

        activity()->enableLogging();
    }

    private function validate(Collection $rows)
    {
        $types = TransactionType::getKeys();

        $categories = Option::query()
            ->byTeam()
            ->where('type', OptionType::TRANSACTION_CATEGORY)
            ->get();

        $paymentMethods = PaymentMethod::query()
            ->byTeam()
            ->get();

        $primaryLedgers = Ledger::query()
            ->byTeam()
            ->subType('primary')
            ->get();

        $secondaryLedgers = Ledger::query()
            ->byTeam()
            ->subType('secondary')
            ->get();

        $errors = [];

        $newNames = [];
        foreach ($rows as $index => $row) {
            $rowNo = $index + 2;

            $type = Arr::get($row, 'type');
            $category = Arr::get($row, 'category');
            $primaryLedger = Arr::get($row, 'primary_ledger');
            $date = Arr::get($row, 'date');
            $secondaryLedger = Arr::get($row, 'secondary_ledger');
            $amount = Arr::get($row, 'amount');
            $paymentMethod = Arr::get($row, 'payment_method');
            $instrumentNumber = Arr::get($row, 'instrument_number');
            $instrumentDate = Arr::get($row, 'instrument_date');
            $clearingDate = Arr::get($row, 'clearing_date');
            $bankDetail = Arr::get($row, 'bank_detail');
            $branchDetail = Arr::get($row, 'branch_detail');
            $referenceNumber = Arr::get($row, 'reference_number');
            $cardProvider = Arr::get($row, 'card_provider');
            $description = Arr::get($row, 'description');
            $remarks = Arr::get($row, 'remarks');

            if (! $type) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.type'), 'required');
            } elseif (! in_array(strtolower($type), $types)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.type'), 'invalid');
            }

            if ($category && ! in_array($category, $categories->pluck('name')->all())) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.category'), 'invalid');
            }

            if (! $primaryLedger) {
                $errors[] = $this->setError($rowNo, trans('finance.ledger.primary_ledger'), 'required');
            } elseif (! in_array($primaryLedger, $primaryLedgers->pluck('name')->all())) {
                $errors[] = $this->setError($rowNo, trans('finance.ledger.secondary_ledger'), 'invalid');
            }

            if (! $date) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.date'), 'required');
            }

            if (is_int($date)) {
                $date = Date::excelToDateTimeObject($date)->format('Y-m-d');
            }

            if ($date && ! CalHelper::validateDate($date)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.date'), 'invalid');
            }

            if (! $secondaryLedger) {
                $errors[] = $this->setError($rowNo, trans('finance.ledger.secondary_ledger'), 'required');
            } elseif (! in_array($secondaryLedger, $secondaryLedgers->pluck('name')->all())) {
                $errors[] = $this->setError($rowNo, trans('finance.ledger.secondary_ledger'), 'invalid');
            }

            if (! $amount) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.amount'), 'required');
            } elseif (! is_numeric($amount)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.amount'), 'invalid');
            } elseif ($amount <= 0) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.amount'), 'min_max', ['min' => 0]);
            }

            if (! $paymentMethod) {
                $errors[] = $this->setError($rowNo, trans('finance.payment_method.payment_method'), 'required');
            } elseif (! in_array($paymentMethod, $paymentMethods->pluck('name')->all())) {
                $errors[] = $this->setError($rowNo, trans('finance.payment_method.payment_method'), 'invalid');
            }

            if ($instrumentNumber && (strlen($instrumentNumber) < 2 || strlen($instrumentNumber) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.instrument_number'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($instrumentDate) {
                if (is_int($instrumentDate)) {
                    $instrumentDate = Date::excelToDateTimeObject($instrumentDate)->format('Y-m-d');
                }

                if (! CalHelper::validateDate($instrumentDate)) {
                    $errors[] = $this->setError($rowNo, trans('finance.transaction.props.instrument_date'), 'invalid');
                }
            }

            if ($clearingDate) {
                if (is_int($clearingDate)) {
                    $clearingDate = Date::excelToDateTimeObject($clearingDate)->format('Y-m-d');
                }

                if (! CalHelper::validateDate($clearingDate)) {
                    $errors[] = $this->setError($rowNo, trans('finance.transaction.props.clearing_date'), 'invalid');
                }
            }

            if ($bankDetail && (strlen($bankDetail) < 2 || strlen($bankDetail) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.bank_detail'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($branchDetail && (strlen($branchDetail) < 2 || strlen($branchDetail) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.branch_detail'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($referenceNumber && (strlen($referenceNumber) < 2 || strlen($referenceNumber) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.reference_number'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($cardProvider && (strlen($cardProvider) < 2 || strlen($cardProvider) > 100)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.card_provider'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($description && (strlen($description) < 2 || strlen($description) > 1000)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.description'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            if ($remarks && (strlen($remarks) < 2 || strlen($remarks) > 1000)) {
                $errors[] = $this->setError($rowNo, trans('finance.transaction.props.remarks'), 'min_max', ['min' => 2, 'max' => 100]);
            }

            $primaryLedger = $primaryLedgers->firstWhere('name', $primaryLedger);
            $secondaryLedger = $secondaryLedgers->firstWhere('name', $secondaryLedger);

            $category = $categories->firstWhere('name', $category);
            $paymentMethod = $paymentMethods->firstWhere('name', $paymentMethod);

            $row['category_id'] = $category?->id;
            $row['payment_method_id'] = $paymentMethod?->id;
            $row['payment_method_code'] = $paymentMethod?->code;
            $row['primary_ledger_id'] = $primaryLedger?->id;
            $row['primary_ledger_code'] = $primaryLedger?->code;
            $row['secondary_ledger_id'] = $secondaryLedger?->id;

            $newRows[] = $row;
        }

        $rows = collect($newRows);

        return [$errors, $rows];
    }
}
