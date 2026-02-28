<?php

namespace App\Console\Commands\Finance;

use App\Actions\Student\PayOnlineFee;
use App\Enums\Finance\PaymentStatus;
use App\Models\Tenant\Config\Config;
use App\Models\Tenant\Finance\Transaction;
use App\Models\Tenant\Student\Registration;
use App\Models\Tenant\Student\Student;
use App\Support\PaymentGatewayMultiAccountSeparator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BilldeskStatusCheck extends Command
{
    use PaymentGatewayMultiAccountSeparator;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billdesk:status {refnum? : Reference Number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Billdesk Status Check';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $refnum = $this->argument('refnum');

        $transaction = Transaction::query()
            ->where('is_online', 1)
            ->where('payment_gateway->name', 'billdesk')
            ->when(empty($refnum), function ($q) {
                $q->where(function ($q) {
                    $q->whereNull('payment_gateway->status')
                        ->orWhere('payment_gateway->status', '!=', 'updated');
                });
            })
            ->when($refnum, function ($q, $refnum) {
                $q->where('payment_gateway->reference_number', $refnum);
            }, function ($q) {
                $q->whereNull('processed_at')
                    ->where('created_at', '<=', now()->subMinutes(10));
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $transaction) {
            $this->info('No transaction found');

            return;
        }

        $transaction->load('period');

        $config = Config::query()
            ->whereTeamId($transaction->period->team_id)
            ->whereName('finance')
            ->first();

        $pgAccount = Arr::get($transaction->payment_gateway, 'pg_account');

        $pgVersion = Arr::get($config->value, 'billdesk_version');

        if (! in_array($pgVersion, ['1.2', '1.5', 1.2, 1.5])) {
            $this->info('Payment gateway version mismatch');

            return;
        }

        $client = $this->getCredential(Arr::get($config->value, 'billdesk_client'), $pgAccount);
        $secret = $this->getCredential(Arr::get($config->value, 'billdesk_secret'), $pgAccount);
        $merchantId = $this->getCredential(Arr::get($config->value, 'billdesk_merchant'), $pgAccount);
        $mode = (bool) Arr::get($config->value, 'enable_live_billdesk_mode');
        $version = Arr::get($config->value, 'billdesk_version');

        $referenceNumber = Arr::get($transaction->payment_gateway, 'reference_number');

        $data = [
            'mercid' => $merchantId,
            'orderid' => $referenceNumber,
            'refund_details' => true,
        ];

        $encodedPayload = JWT::encode($data, $secret, 'HS256', null, [
            'alg' => 'HS256',
            'clientid' => $client,
        ]);

        $billdeskUrl = 'https://pguat.billdesk.io';

        if (in_array($version, ['1.5', '1.2'])) {
            $billdeskUrl = 'https://uat1.billdesk.com/u2';
        }

        if ($mode) {
            $billdeskUrl = 'https://api.billdesk.com';
        }

        $traceId = strtoupper(Str::random(10));
        $time = time();

        $pgResponse = Http::withHeaders([
            'Content-type' => 'application/jose',
            'accept' => 'application/jose',
            'BD-Traceid' => $traceId,
            'BD-Timestamp' => $time,
        ])->withBody($encodedPayload, 'application/jose')->post($billdeskUrl.'/payments/ve1_2/transactions/get');

        $response = (array) JWT::decode($pgResponse->getBody(), new Key($secret, 'HS256'));

        $additionalInfo = (array) Arr::get($response, 'additional_info', []);

        if (! Arr::get($response, 'orderid')) {
            $this->info('Error desc: Invalid order '.Arr::get($transaction->payment_gateway, 'reference_number'));
            $this->failTransaction($transaction, 'Invalid order');

            return;
        }

        $referenceNumber = Arr::get($response, 'orderid');
        $paymentType = Arr::get($additionalInfo, 'additional_info1');
        $transactionUuid = Arr::get($additionalInfo, 'additional_info2');

        if ($transactionUuid != $transaction->uuid) {
            $this->info('Error desc: Transaction ID Mismatch');
            $this->failTransaction($transaction, 'Transaction ID Mismatch');

            return;
        }

        $status = Arr::get($response, 'auth_status');

        if ($status !== '0300') {
            $this->info('Transaction not completed');
            $this->failTransaction($transaction, Arr::get($response, 'transaction_error_desc', 'failed_transaction'));

            return;
        }

        $amount = Arr::get($response, 'amount');

        if ($amount != $transaction->amount->value) {
            if (abs($amount - $transaction->amount->value) > 1) {
                $this->info('Amount mismatch');
                $this->failTransaction($transaction, 'amount_mismatch');

                return;
            }
        }

        $refundInfo = Arr::get($response, 'refundInfo', []);

        if (count($refundInfo)) {
            $this->info('Amount refunded');
            $this->failTransaction($transaction, 'amount_refunded');

            return;
        }

        if ($transaction->processed_at->value) {
            $this->info('Transaction already processed');

            return;
        }

        $head = $transaction->head;

        if ($head == 'student_fee') {
            \DB::beginTransaction();

            $student = Student::find($transaction->transactionable_id);

            (new PayOnlineFee)->studentFeePayment($student, $transaction);

            $transaction->payment_gateway = array_merge($transaction->payment_gateway, [
                'status' => 'updated',
                'transactionid' => Arr::get($response, 'transactionid'),
                'bankid' => Arr::get($response, 'bankid'),
                'payment_method_type' => Arr::get($response, 'payment_method_type'),
            ]);
            $transaction->processed_at = now()->toDateTimeString();
            $transaction->save();

            \DB::commit();

            $this->info('Fee paid successfully');
        } elseif ($head == 'registration_fee') {
            \DB::beginTransaction();

            $registration = Registration::find($transaction->transactionable_id);

            (new PayOnlineFee)->registrationFeePayment($registration, $transaction);

            $transaction->payment_gateway = array_merge($transaction->payment_gateway, [
                'status' => 'updated',
                'transactionid' => Arr::get($response, 'transactionid'),
                'bankid' => Arr::get($response, 'bankid'),
                'payment_method_type' => Arr::get($response, 'payment_method_type'),
            ]);
            $transaction->processed_at = now()->toDateTimeString();
            $transaction->save();

            $registration->payment_status = PaymentStatus::PAID;
            $registration->save();

            \DB::commit();

            $this->info('Fee paid successfully');
        } else {
            $this->info('Invalid fee');
            $this->failTransaction($transaction, 'invalid_fee');

            return;
        }
    }

    private function failTransaction(Transaction $transaction, $code)
    {
        $transaction->payment_gateway = array_merge($transaction->payment_gateway, [
            'status' => 'updated',
            'code' => $code,
        ]);
        $transaction->save();
    }

    private function getTransactionData(Collection $data, $key)
    {
        $item = $data->first(function ($item) use ($key) {
            return starts_with($item, $key.'=');
        });

        return explode('=', $item)[1] ?? null;
    }
}
