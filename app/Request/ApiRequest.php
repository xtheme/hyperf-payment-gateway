<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

use function Hyperf\Translation\trans;

/**
 * 驗證 API 標準請求參數
 */
class ApiRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'site_id' => 'required|string',
            'payment_platform' => 'required|string',
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'endpoint_url' => 'required|url',
        ];
    }

    public function attributes(): array
    {
        return [
            'site_id' => trans('local.site_id'),
            'payment_platform' => trans('local.payment_platform'),
            'merchant_id' => trans('local.merchant_id'),
            'merchant_key' => trans('local.merchant_key'),
            'endpoint_url' => trans('local.endpoint_url'),
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
