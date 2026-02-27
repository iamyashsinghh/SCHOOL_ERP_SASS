<?php

namespace App\Actions\Config\Module;

class StoreTaskConfig
{
    public static function handle(): array
    {
        $input = request()->validate([
            'code_number_prefix' => 'sometimes|max:100',
            'code_number_digit' => 'sometimes|required|integer|min:0|max:9',
            'code_number_suffix' => 'sometimes|max:100',
            'view' => 'sometimes|required|in:card,list,board',
            'is_accessible_to_top_level' => 'sometimes|boolean',
            'is_manageable_by_top_level' => 'sometimes|boolean',
        ], [], [
            'code_number_prefix' => __('task.config.props.number_prefix'),
            'code_number_digit' => __('task.config.props.number_digit'),
            'code_number_suffix' => __('task.config.props.number_suffix'),
            'view' => __('task.config.props.view'),
            'is_accessible_to_top_level' => __('task.config.props.is_accessible_to_top_level'),
            'is_manageable_by_top_level' => __('task.config.props.is_manageable_by_top_level'),
        ]);

        return $input;
    }
}
