<?php

declare(strict_types=1);

namespace App\Request;

use function Hyperf\Translation\trans;

class OrderQueryRequest extends ApiRequest
{
    public function rules(): array
    {
        $api_rules = parent::rules();

        $added_rules = [
            'order_id' => 'required|string',
        ];

        return array_merge($api_rules, $added_rules);
    }

    public function attributes(): array
    {
        $api_attributes = parent::attributes();

        $added_attributes = [
            'order_id' => trans('local.order_id'),
        ];

        return array_merge($api_attributes, $added_attributes);
    }
}
