<?php

namespace App\View\Components\Site;

use App\Models\Tenant\Academic\ProgramType;
use Illuminate\View\Component;

class ProgramDetail extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct() {}

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        $programTypes = ProgramType::query()
            ->with('programs', 'team')
            ->get()
            ->map(function ($programType) {
                return [
                    'id' => $programType->id,
                    'name' => $programType->name,
                    'code' => $programType->code,
                    'team' => $programType->team->name,
                    'description' => $programType->description,
                    'programs' => $programType->programs->map(function ($program) {
                        return [
                            'id' => $program->id,
                            'name' => $program->name,
                            'code' => $program->code,
                            'duration' => $program->duration,
                            'eligibility' => $program->eligibility,
                            'benefits' => $program->benefits,
                        ];
                    })->toArray(),
                ];
            })
            ->groupBy('team')
            ->toArray();

        return view()->first(['components.site.custom.program-detail', 'components.site.default.program-detail'], compact('programTypes'));
    }
}
