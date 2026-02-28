<?php

namespace App\Services\Student\Config;

use App\Contracts\ListGenerator;
use App\Enums\OptionType;
use App\Http\Resources\Student\Config\DocumentTypeResource;
use App\Models\Tenant\Option;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentTypeListService extends ListGenerator
{
    protected $allowedSorts = ['created_at', 'name'];

    protected $defaultSort = 'name';

    protected $defaultOrder = 'asc';

    public function getHeaders(): array
    {
        $headers = [
            [
                'key' => 'name',
                'label' => trans('student.document_type.props.name'),
                'print_label' => 'name',
                'sortable' => true,
                'visibility' => true,
            ],
            [
                'key' => 'detail',
                'label' => '',
                'print_label' => '',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'description',
                'label' => trans('student.document_type.props.description'),
                'print_label' => 'description_summary',
                'sortable' => false,
                'visibility' => true,
            ],
            [
                'key' => 'createdAt',
                'label' => trans('general.created_at'),
                'print_label' => 'created_at.formatted',
                'sortable' => true,
                'visibility' => true,
            ],
        ];

        if (request()->ajax()) {
            $headers[] = $this->actionHeader;
        }

        return $headers;
    }

    public function filter(Request $request): Builder
    {
        return Option::query()
            ->byTeam()
            ->where('type', OptionType::STUDENT_DOCUMENT_TYPE)
            ->filter([
                'App\QueryFilters\LikeMatch:name',
                'App\QueryFilters\UuidMatch',
            ]);
    }

    public function paginate(Request $request): AnonymousResourceCollection
    {
        return DocumentTypeResource::collection($this->filter($request)
            ->orderBy($this->getSort(), $this->getOrder())
            ->paginate((int) $this->getPageLength(), ['*'], 'current_page'))
            ->additional([
                'headers' => $this->getHeaders(),
                'meta' => [
                    'allowed_sorts' => $this->allowedSorts,
                    'default_sort' => $this->defaultSort,
                    'default_order' => $this->defaultOrder,
                ],
            ]);
    }

    public function list(Request $request): AnonymousResourceCollection
    {
        return $this->paginate($request);
    }
}
