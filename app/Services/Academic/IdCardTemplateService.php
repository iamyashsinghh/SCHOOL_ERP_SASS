<?php

namespace App\Services\Academic;

use App\Enums\Academic\IdCardFor;
use App\Models\Tenant\Academic\IdCardTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdCardTemplateService
{
    public function preRequisite(Request $request)
    {
        $for = IdCardFor::getOptions();

        $defaultTemplates = collect(glob(resource_path('views/print/academic/id-card/*.blade.php')))
            ->filter(function ($template) {
                return Str::contains(basename($template), ['-student', '-employee', '-guardian']);
            })
            ->map(function ($template) {
                if (Str::contains(basename($template), 'student')) {
                    $for = 'student';
                } elseif (Str::contains(basename($template), 'employee')) {
                    $for = 'employee';
                } elseif (Str::contains(basename($template), 'guardian')) {
                    $for = 'guardian';
                } else {
                    $for = 'other';
                }

                return [
                    'name' => basename($template, '.blade.php'),
                    'for' => $for,
                    'type' => 'predefined',
                ];
            })
            ->values()
            ->toArray();

        return compact('for', 'defaultTemplates');
    }

    public function create(Request $request): IdCardTemplate
    {
        \DB::beginTransaction();

        $idCardTemplate = IdCardTemplate::forceCreate($this->formatParams($request));

        \DB::commit();

        return $idCardTemplate;
    }

    private function formatParams(Request $request, ?IdCardTemplate $idCardTemplate = null): array
    {
        $formatted = [
            'name' => $request->name,
            'for' => $request->for,
        ];

        $config = $idCardTemplate?->config;

        $config['custom_template_file_name'] = $request->custom_template_file_name;

        $formatted['config'] = $config;

        if (! $idCardTemplate) {
            $formatted['team_id'] = auth()->user()->current_team_id;
        }

        return $formatted;
    }

    public function export(IdCardTemplate $idCardTemplate)
    {
        $content = '';
        $hasCustomTemplateFile = $idCardTemplate->getConfig('custom_template_file_name');

        if ($hasCustomTemplateFile) {
            $customTemplateFileName = $idCardTemplate->getConfig('custom_template_file_name');

            if (view()->exists(config('config.print.custom_path').'academic.id-card.templates.'.$customTemplateFileName)) {
                $content = view(config('config.print.custom_path').'academic.id-card.templates.'.$customTemplateFileName)->render();
            }
        }

        return view('print.academic.id-card.index', compact('content'));
    }

    public function update(Request $request, IdCardTemplate $idCardTemplate): void
    {
        \DB::beginTransaction();

        $idCardTemplate->forceFill($this->formatParams($request, $idCardTemplate))->save();

        \DB::commit();
    }

    public function deletable(IdCardTemplate $idCardTemplate): bool
    {
        return true;
    }
}
