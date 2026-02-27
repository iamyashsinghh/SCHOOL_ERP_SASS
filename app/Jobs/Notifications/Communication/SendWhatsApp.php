<?php

namespace App\Jobs\Notifications\Communication;

use App\Actions\SendWhatsApp as SendWhatsAppAction;
use App\Concerns\SetConfigForJob;
use App\Support\HasAudience;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class SendWhatsApp implements ShouldQueue
{
    use Batchable, Dispatchable, HasAudience, InteractsWithQueue, Queueable, SerializesModels, SetConfigForJob;

    protected $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        $teamId = Arr::get($this->params, 'team_id');

        $this->setConfig($teamId, ['general', 'assets', 'system', 'social_network', 'notification', 'whatsapp']);

        $type = Arr::get($this->params, 'type');
        $data = Arr::get($this->params, 'data');

        $templateCode = Arr::get($this->params, 'template_code');
        $content = Arr::get($this->params, 'content');

        $userVariables = Arr::get($this->params, 'variables', []);

        if ($type == 'student') {
            $variables = [
                'name' => Arr::get($data, 'name'),
                'course_name' => Arr::get($data, 'course_name'),
                'batch_name' => Arr::get($data, 'batch_name'),
                'course_batch_name' => Arr::get($data, 'course_name').' '.Arr::get($data, 'batch_name'),
            ];
        } elseif ($type == 'employee') {
            $variables = [
                'name' => Arr::get($data, 'name'),
                'designation_name' => Arr::get($data, 'designation_name'),
                'department_name' => Arr::get($data, 'department_name'),
                'designation_department_name' => Arr::get($data, 'designation_name').' '.Arr::get($data, 'department_name'),
            ];
        }

        $variables['app_name'] = config('config.general.app_name');
        $variables['team_name'] = config('config.team.name');

        $variables = collect($userVariables)
            ->pluck('value', 'name')
            ->mapWithKeys(function ($value, $key) use ($variables) {
                if (! empty($value)) {
                    return [$key => $value];
                }

                return [$key => $variables[$key] ?? $value];
            })
            ->union($variables)
            ->toArray();

        $params = [
            'template_code' => $templateCode,
            'recipients' => [
                [
                    'mobile' => Arr::get($data, 'contact_number'),
                    'message' => $content,
                    'variables' => $variables,
                ],
            ],
        ];

        (new SendWhatsAppAction)->execute($params);
    }
}
