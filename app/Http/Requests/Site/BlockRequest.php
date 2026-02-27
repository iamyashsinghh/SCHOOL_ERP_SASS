<?php

namespace App\Http\Requests\Site;

use App\Enums\Site\BlockType;
use App\Models\Site\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class BlockRequest extends FormRequest
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
        $uuid = $this->route('block.uuid');

        $rules = [
            'name' => ['required', 'alpha_dash', 'max:50', Rule::unique('site_blocks')->ignore($uuid, 'uuid')],
            'type' => ['nullable', new Enum(BlockType::class)],
            'background_color' => ['nullable', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
            'text_color' => ['nullable', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
        ];

        if ($this->type == 'accordion') {
            $rules['accordion_items'] = 'required|array|min:1';
            $rules['accordion_items.*.heading'] = 'required|min:2|max:255|distinct';
            $rules['accordion_items.*.description'] = 'required|min:2|max:1000';
        } elseif ($this->type == 'stat_counter') {
            $rules['max_items_per_row'] = 'required|integer|min:1|max:8';
            $rules['stat_counter_items'] = 'required|array|min:1';
            $rules['stat_counter_items.*.heading'] = 'required|min:2|max:255|distinct';
            $rules['stat_counter_items.*.count'] = 'required|integer|min:0';
        } elseif ($this->type == 'testimonial') {
            $rules['testimonial_items'] = 'required|array|min:1';
            $rules['testimonial_items.*.name'] = 'required|min:2|max:255|distinct';
            $rules['testimonial_items.*.detail'] = 'nullable|max:200';
            $rules['testimonial_items.*.comment'] = 'required|max:1000';
        }

        if ($this->type != 'slider') {
            $rules['title'] = 'required|max:255';
            $rules['sub_title'] = 'nullable|max:255';
            $rules['content'] = 'nullable|max:1000';
            $rules['menu'] = 'nullable|uuid';
            $rules['url'] = 'nullable|max:255|url';
        }

        return $rules;
    }

    public function withValidator($validator)
    {
        if (! $validator->passes()) {
            return;
        }

        $validator->after(function ($validator) {
            $blockUuid = $this->route('block.uuid');

            if (in_array($this->name, ['EVENT_LIST', 'ANNOUNCEMENT_LIST', 'BLOG_LIST', 'CONTACT'])) {
                $validator->errors()->add('name', trans('validation.reserved', ['attribute' => trans('site.block.props.name')]));
            }

            if ($this->type == 'slider') {
                $this->merge([
                    'title' => null,
                    'sub_title' => null,
                    'content' => null,
                ]);
            } else {
                $menu = $this->menu ? Menu::query()
                    ->where('uuid', $this->menu)
                    ->getOrFail(trans('site.menu.menu')) : null;

                if ($menu) {
                    $this->merge([
                        'menu_id' => $menu?->id,
                    ]);
                }
            }
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
            'name' => __('site.block.props.name'),
            'type' => __('site.block.props.type'),
            'title' => __('site.block.props.title'),
            'sub_title' => __('site.block.props.sub_title'),
            'background_color' => __('site.block.props.background_color'),
            'text_color' => __('site.block.props.text_color'),
            'content' => __('site.block.props.content'),
            'url' => __('site.block.props.url'),
            'accordion_items' => __('general.item'),
            'accordion_items.*.heading' => __('site.block.props.heading'),
            'accordion_items.*.description' => __('site.block.props.description'),
            'max_items_per_row' => __('site.block.props.max_items_per_row'),
            'stat_counter_items' => __('general.item'),
            'stat_counter_items.*.heading' => __('site.block.props.heading'),
            'stat_counter_items.*.count' => __('site.block.props.count'),
            'testimonial_items' => __('general.item'),
            'testimonial_items.*.name' => __('site.block.props.name'),
            'testimonial_items.*.detail' => __('site.block.props.detail'),
            'testimonial_items.*.comment' => __('site.block.props.comment'),
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
