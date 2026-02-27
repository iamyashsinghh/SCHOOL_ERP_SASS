<?php

namespace App\Jobs\Notifications\Communication;

use App\Concerns\SetConfigForJob;
use App\Models\Communication\Communication;
use App\Support\HasAudience;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Throwable;

class SendBatchWhatsApp implements ShouldQueue
{
    use Dispatchable, HasAudience, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'mail']);

        $communication = Communication::query()
            ->findOrFail(Arr::get($this->params, 'communication_id'));

        $communication->load('audiences');

        $studentAudienceType = Arr::get($communication->audience, 'student_type', 'all');
        $employeeAudienceType = Arr::get($communication->audience, 'employee_type', 'all');
        $studentAudiences = $communication->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Division', 'Course', 'Batch']);
        })->pluck('audienceable_id');
        $employeeAudiences = $communication->audiences->filter(function ($audience) {
            return in_array($audience->audienceable_type, ['Department', 'Designation']);
        })->pluck('audienceable_id');

        $data = $this->getAudienceSummary([
            'student_audience_type' => $studentAudienceType,
            'employee_audience_type' => $employeeAudienceType,
            'student_audiences' => $studentAudiences,
            'employee_audiences' => $employeeAudiences,
            'period_id' => $communication->period_id,
            'team_id' => 1,
        ]);

        $students = collect(Arr::get($data, 'students'));
        $employees = collect(Arr::get($data, 'employees'));

        $jobs = [];

        foreach ($students->chunk(100) as $chunk) {
            foreach ($chunk as $item) {
                $jobs[] = new SendWhatsApp([
                    'type' => 'student',
                    'data' => $item->toArray(),
                    'team_id' => $teamId,
                    'template_code' => $communication->getMeta('template_code'),
                    'content' => $communication->content,
                    'variables' => $communication->getMeta('variables', []),
                ]);
            }
        }

        foreach ($employees->chunk(100) as $chunk) {
            foreach ($chunk as $item) {
                $jobs[] = new SendWhatsApp([
                    'type' => 'employee',
                    'data' => $item->toArray(),
                    'team_id' => $teamId,
                    'template_code' => $communication->getMeta('template_code'),
                    'content' => $communication->content,
                    'variables' => $communication->getMeta('variables', []),
                ]);
            }
        }

        Bus::batch($jobs)
            ->then(function (Batch $batch) {})
            ->catch(function (Batch $batch, Throwable $e) {})
            ->finally(function (Batch $batch) {})
            ->dispatch();
    }
}
