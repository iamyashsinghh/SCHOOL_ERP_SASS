<?php

namespace App\Http\Requests\Helpdesk\Faq;

use App\Enums\OptionType;
use App\Models\Helpdesk\Faq\Faq;
use App\Models\Option;
use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uuid = $this->route('faq');

        $rules = [
            'question' => 'required|string|max:255',
            'category' => 'required|uuid',
            'answer' => 'required|string|max:2000',
            'is_published' => 'boolean',
            'tags' => 'array',
            'tags.*' => 'required|string|distinct',
        ];

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $uuid = $this->route('faq');

            $category = Option::query()
                ->where('type', OptionType::FAQ_CATEGORY)
                ->whereUuid($this->category)
                ->getOrFail(trans('helpdesk.faq.faq'), 'category');

            $existingQuestions = Faq::query()
                ->whereCategoryId($category->id)
                ->when($uuid, function ($q, $uuid) {
                    $q->where('uuid', '!=', $uuid);
                })
                ->whereQuestion($this->question)
                ->exists();

            if ($existingQuestions) {
                $validator->errors()->add('question', trans('validation.unique', ['attribute' => __('helpdesk.faq.props.question')]));
            }

            $this->merge([
                'category_id' => $category?->id,
            ]);
        });
    }

    /**
     * Translate fields with user friendly name.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'question' => __('helpdesk.faq.props.question'),
            'category' => __('helpdesk.faq.category.category'),
            'answer' => __('helpdesk.faq.props.answer'),
            'is_published' => __('helpdesk.faq.props.publish'),
            'tags' => trans('general.tag'),
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [];
    }
}
