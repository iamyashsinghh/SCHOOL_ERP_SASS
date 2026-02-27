<?php

namespace App\Jobs\Notifications\Finance;

use App\Concerns\SetConfigForJob;
use App\Models\TempStorage;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Throwable;

class SendBatchFeeInstallmentPaymentLinkNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'notification', 'mail', 'sms', 'whatsapp']);

        if (! config('config.notification.enable_notification')) {
            return;
        }

        $paymentLinks = collect(Arr::get($this->params, 'payment_link_uuids', []));

        $jobs = [];

        $paymentLinks->each(function ($linkUuid) use ($teamId, &$jobs) {
            $paymentLink = TempStorage::query()
                ->where('uuid', $linkUuid)
                ->first();

            if (! $paymentLink) {
                return;
            }

            $jobs[] = new SendFeeInstallmentPaymentLinkNotification([
                'team_id' => $teamId,
                'payment_uuid' => $linkUuid,
                'student_uuid' => Arr::get($paymentLink->values, 'student'),
                'fee_installment_uuid' => Arr::get($paymentLink->values, 'fee_installment'),
                'amount' => Arr::get($paymentLink->values, 'amount'),
            ]);
        });

        Bus::batch($jobs)
            ->then(function (Batch $batch) {})
            ->catch(function (Batch $batch, Throwable $e) {})
            ->finally(function (Batch $batch) {})
            ->dispatch();
    }
}
