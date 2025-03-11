<?php

declare(strict_types=1);

namespace App\Request;

use function Hyperf\Translation\trans;

class WithdrawCreateRequest extends ApiRequest
{
    public function rules(): array
    {
        $api_rules = parent::rules();

        $added_rules = [
            'payment_channel' => 'required|string',
            'query_url' => 'required|url',
            'callback_url' => 'required|url',
            'order_id' => 'required|string',
            'amount' => 'required|string',
            'user_name' => 'nullable|string',
            'bank_name' => 'required|string',
            'bank_account' => 'required|string',
        ];

        return array_merge($api_rules, $added_rules);
    }

    public function attributes(): array
    {
        $api_attributes = parent::attributes();

        $added_attributes = [
            'payment_channel' => trans('local.payment_channel'),
            'query_url' => trans('local.query_url'),
            'callback_url' => trans('local.callback_url'),
            'order_id' => trans('local.order_id'),
            'amount' => trans('local.amount'),
            'user_name' => trans('local.user_name'),
            'bank_name' => trans('local.bank_name'),
            'bank_account' => trans('local.bank_account'),
        ];

        return array_merge($api_attributes, $added_attributes);
    }
}
