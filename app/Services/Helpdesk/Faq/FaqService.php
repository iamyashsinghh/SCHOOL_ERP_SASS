<?php

namespace App\Services\Helpdesk\Faq;

use App\Actions\CreateTag;
use App\Enums\Helpdesk\Faq\Status;
use App\Enums\Helpdesk\Faq\Visibility;
use App\Enums\OptionType;
use App\Http\Resources\OptionResource;
use App\Models\Helpdesk\Faq\Faq;
use App\Models\Option;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FaqService
{
    public function preRequisite(): array
    {
        $categories = OptionResource::collection(Option::query()
            ->byTeam()
            ->where('type', OptionType::FAQ_CATEGORY)
            ->get());

        return compact('categories');
    }

    public function findByUuidOrFail(string $uuid): Faq
    {
        return Faq::query()
            ->byTeam()
            ->findIfExists($uuid);
    }

    public function create(Request $request): Faq
    {
        \DB::beginTransaction();

        $faq = Faq::forceCreate($this->formatParams($request));

        $tags = (new CreateTag)->execute($request->input('tags', []));

        $faq->tags()->sync($tags);

        \DB::commit();

        return $faq;
    }

    private function formatParams(Request $request, ?Faq $faq = null): array
    {
        $formatted = [
            'question' => $request->question,
            'category_id' => $request->category_id,
            'answer' => $request->answer,
            'status' => $request->boolean('is_published') ? Status::PUBLISHED : Status::DRAFT,
            'visibility' => Visibility::PUBLIC,
        ];

        if (! $faq) {
            $formatted['team_id'] = auth()->user()?->current_team_id;
        }

        return $formatted;
    }

    public function update(Request $request, Faq $faq): void
    {
        \DB::beginTransaction();

        $faq->forceFill($this->formatParams($request, $faq))->save();

        $tags = (new CreateTag)->execute($request->input('tags', []));

        $faq->tags()->sync($tags);

        \DB::commit();
    }

    public function deletable(Faq $faq, $validate = false): ?bool
    {
        return true;
    }

    private function findMultiple(Request $request): array
    {
        if ($request->boolean('global')) {
            $listService = new FaqListService;
            $uuids = $listService->getIds($request);
        } else {
            $uuids = is_array($request->uuids) ? $request->uuids : [];
        }

        if (! count($uuids)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_find', ['attribute' => trans('general.data')])]);
        }

        return $uuids;
    }

    public function deleteMultiple(Request $request): int
    {
        $uuids = $this->findMultiple($request);

        $faqs = Faq::whereIn('uuid', $uuids)->get();

        $deletable = [];
        foreach ($faqs as $faq) {
            if ($this->deletable($faq, true)) {
                $deletable[] = $faq->uuid;
            }
        }

        if (! count($deletable)) {
            throw ValidationException::withMessages(['message' => trans('global.could_not_delete_any', ['attribute' => trans('helpdesk.faq.faq')])]);
        }

        Faq::whereNull('model_type')->whereIn('uuid', $deletable)->delete();

        return count($deletable);
    }
}
