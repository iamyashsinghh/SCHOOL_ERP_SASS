<?php

namespace App\Console\Commands\Finance;

use App\Helpers\SysHelper;
use App\Jobs\Notifications\Finance\SendBatchFeeInstallmentPaymentLinkNotification;
use App\Models\Tenant\Student\Fee;
use App\Models\Tenant\Team;
use App\Models\Tenant\TempStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FeeInstallmentPaymentLink extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:fee-installment-payment-link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate fee installment payment link';

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
        $date = today()->toDateString();

        $teams = Team::query()
            ->get();

        foreach ($teams as $team) {
            SysHelper::setTeam($team->id);

            $insertData = [];

            Fee::query()
                ->select('student_fees.*', 'students.id', 'students.uuid as student_uuid', \DB::raw('total - paid as due_fee'), \DB::raw('COALESCE(student_fees.due_date, fee_installments.due_date) as final_due_date'), 'fee_installments.uuid as fee_installment_uuid')
                ->join('fee_installments', 'fee_installments.id', '=', 'student_fees.fee_installment_id')
                ->join('students', 'students.id', '=', 'student_fees.student_id')
                ->join('contacts', 'contacts.id', '=', 'students.contact_id')
                ->where('contacts.team_id', $team->id)
                ->where('paid', '=', 0)
                ->whereDate(\DB::raw('COALESCE(student_fees.due_date, fee_installments.due_date)'), '=', $date)
                ->chunk(100, function ($studentFees) use (&$insertData, $team) {
                    foreach ($studentFees as $studentFee) {
                        $existing = TempStorage::query()
                            ->where('type', 'student_fee_payment')
                            ->where('values->fee_installment', $studentFee->fee_installment_uuid)
                            ->where('values->student', $studentFee->uuid)
                            ->where('values->date', '=', today()->toDateString())
                            ->where('values->amount', $studentFee->total->value)
                            ->first();

                        if (! $existing) {
                            $insertData[] = [
                                'uuid' => (string) Str::uuid(),
                                'user_id' => null,
                                'type' => 'student_fee_payment',
                                'values' => json_encode([
                                    'student' => $studentFee->student_uuid,
                                    'amount' => $studentFee->total->value,
                                    'date' => today()->toDateString(),
                                    'fee_installment' => $studentFee->uuid,
                                ]),
                                'meta' => json_encode([
                                    'team_id' => $team->id,
                                    'is_system_generated' => true,
                                ]),
                                'created_at' => now()->toDateTimeString(),
                                'updated_at' => now()->toDateTimeString(),
                            ];
                        }
                    }
                });

            if (! empty($insertData)) {
                TempStorage::query()
                    ->insert($insertData);

                SendBatchFeeInstallmentPaymentLinkNotification::dispatch([
                    'payment_link_uuids' => collect($insertData)->pluck('uuid')->toArray(),
                    'team_id' => $team->id,
                ]);
            }
        }
    }
}
