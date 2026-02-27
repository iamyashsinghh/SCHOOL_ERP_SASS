<?php

namespace App\Actions\Config\Module;

class StoreContactConfig
{
    public static function handle(): array
    {
        $input = request()->validate([
            'enable_middle_name_field' => 'sometimes|boolean',
            'enable_third_name_field' => 'sometimes|boolean',
            'enable_locality_field' => 'sometimes|boolean',
            'enable_category_field' => 'sometimes|boolean',
            'enable_caste_field' => 'sometimes|boolean',
        ], [], [
            'enable_middle_name_field' => __('contact.config.props.enable_middle_name_field'),
            'enable_third_name_field' => __('contact.config.props.enable_third_name_field'),
            'enable_locality_field' => __('contact.props.locality'),
            'enable_category_field' => __('contact.category.category'),
            'enable_caste_field' => __('contact.caste.caste'),
        ]);

        return $input;
    }
}
