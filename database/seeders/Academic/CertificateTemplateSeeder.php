<?php

namespace Database\Seeders\Academic;

use App\Models\Tenant\Academic\CertificateTemplate;
use App\Models\Tenant\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CertificateTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $certificateTemplates = Arr::getVar('certificate-templates');

        foreach (Team::get() as $team) {
            foreach ($certificateTemplates as $key => $certificateTemplate) {
                $existingTemplate = CertificateTemplate::query()
                    ->byTeam($team->id)
                    ->where('name', Arr::get($certificateTemplate, 'name'))
                    ->first();

                if (! $existingTemplate) {
                    CertificateTemplate::forceCreate([
                        'team_id' => $team->id,
                        'name' => Arr::get($certificateTemplate, 'name'),
                        'type' => Arr::get($certificateTemplate, 'type'),
                        'for' => Arr::get($certificateTemplate, 'for'),
                        'content' => Arr::get($certificateTemplate, 'content'),
                        'custom_fields' => collect(Arr::get($certificateTemplate, 'custom_fields', []))->map(function ($field) {
                            return [
                                'uuid' => Str::uuid(),
                                ...$field,
                            ];
                        })->toArray(),
                        'config' => array_merge([
                            'number_digit' => 3,
                        ], Arr::get($certificateTemplate, 'config', [])),
                    ]);
                }
            }
        }
    }
}
